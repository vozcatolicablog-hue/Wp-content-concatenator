# Análisis Exhaustivo — Concatenador de Contenido Semanal v1.5.0

> **Fecha del análisis:** 2026-06-08  
> **Plugin analizado:** `weekly-content-concatenator`  
> **Versión:** 1.5.0  
> **Autor:** Voz Católica  
> **Licencia:** GPLv2 or later  

---

## Tabla de contenidos

1. [Visión general de la arquitectura](#1-visión-general-de-la-arquitectura)
2. [Inventario de archivos](#2-inventario-de-archivos)
3. [Bugs y errores encontrados](#3-bugs-y-errores-encontrados)
4. [Vulnerabilidades de seguridad](#4-vulnerabilidades-de-seguridad)
5. [Problemas de rendimiento](#5-problemas-de-rendimiento)
6. [Archivos y código huérfano](#6-archivos-y-código-huérfano)
7. [Deuda técnica y código mejorable](#7-deuda-técnica-y-código-mejorable)
8. [Mejoras funcionales sugeridas](#8-mejoras-funcionales-sugeridas)
9. [Accesibilidad](#9-accesibilidad)
10. [Internacionalización (i18n)](#10-internacionalización-i18n)
11. [Compatibilidad y estándares](#11-compatibilidad-y-estándares)
12. [Desinstalación incompleta](#12-desinstalación-incompleta)
13. [Resumen de prioridades](#13-resumen-de-prioridades)

---

## 1. Visión general de la arquitectura

El plugin sigue un patrón modular con tres clases separadas más el archivo principal:

```
weekly-content-concatenator.php   ← Bootstrap, funciones globales
includes/
  class-wcc-admin.php             ← Meta box, AJAX, página de ajustes
  class-wcc-cron.php              ← WP-Cron para publicación programada
  class-wcc-frontend.php          ← Render frontend, shortcode, assets
assets/
  admin.css / admin.js            ← UI del editor
  frontend.css / frontend.js      ← Acordeón público
  settings.js                     ← Inicialización del color picker
```

**Aspectos positivos destacados:**
- Separación de responsabilidades correcta (Admin / Cron / Frontend).
- Nonce correctamente verificado en el guardado normal y AJAX.
- Uso de `wp_kses_post()` y `sanitize_text_field()` en la mayoría de entradas.
- El cron limpia sus eventos al eliminar/papelerar un post.
- Los assets solo se cargan cuando son necesarios (`is_singular`, tipo de pantalla).
- Uso de `DateTimeImmutable` con `wp_timezone()` para manejar zonas horarias correctamente.
- Lazy-loading de iframes mediante `data-src` en acordeones cerrados.
- Soporte a accesibilidad básica con `<details>`/`<summary>` nativos.

---

## 2. Inventario de archivos

| Archivo | Líneas | Bytes | Estado |
|---|---|---|---|
| `weekly-content-concatenator.php` | 164 | 4 245 | ✅ Activo |
| `includes/class-wcc-admin.php` | 655 | 24 148 | ✅ Activo |
| `includes/class-wcc-cron.php` | 126 | 3 264 | ✅ Activo |
| `includes/class-wcc-frontend.php` | 207 | 6 620 | ✅ Activo |
| `assets/admin.css` | 168 | 3 056 | ✅ Activo |
| `assets/admin.js` | 239 | 7 769 | ✅ Activo |
| `assets/frontend.css` | 179 | 3 871 | ✅ Activo |
| `assets/frontend.js` | 55 | 1 569 | ✅ Activo |
| `assets/settings.js` | 8 | 136 | ✅ Activo |
| `uninstall.php` | 14 | 293 | ⚠️ Incompleto |
| `composer.json` | 20 | 624 | ⚠️ Parcial |
| `readme.txt` | 171 | 6 570 | ⚠️ Sin tildes |
| `DEVELOPMENT.md` | — | 938 | ✅ Activo |
| `.gitignore` | — | 95 | ✅ Activo |
| `weekly-content-concatenator 1.5.0.zip` | — | 20 320 | ⚠️ Huérfano |
| `analisis.md` | — | — | ⚠️ Huérfano vacío |

---

## 3. Bugs y errores encontrados

### ~~🔴 BUG-01 — Condición de carrera en `save_post` con la variable estática `$is_bumping`~~

**Archivo:** `includes/class-wcc-admin.php`, línea 270  
**Severidad:** Alta

```php
static $is_bumping = false;
if ( $is_bumping ) {
    return;
}
```

El uso de una variable estática local a la función como semáforo anti-reentrada es frágil. Si `wp_update_post()` lanza una excepción entre las líneas 329 y 337, `$is_bumping` quedará en `true` permanentemente para el resto de la petición. Cualquier guardado posterior del mismo post en la misma request (p.ej., hooks encadenados) será silenciosamente ignorado.

**Fix recomendado:** Usar un array de IDs ya procesados en vez de un booleano simple, o envolver el bloque en un try/finally:

```php
static $bumping_ids = [];
if ( in_array( $post_id, $bumping_ids, true ) ) {
    return;
}
$bumping_ids[] = $post_id;
// ... wp_update_post() ...
$bumping_ids = array_diff( $bumping_ids, [ $post_id ] );
```

---

### ~~🔴 BUG-02 — `ajax_save_single_entry` no protege contra reentrada en el bump~~

**Archivo:** `includes/class-wcc-admin.php`, líneas 429–438  
**Severidad:** Alta

En `save_post` hay un semáforo `$is_bumping`, pero en `ajax_save_single_entry` se llama directamente a `wp_update_post()` sin ningún semáforo. Si el hook `save_post_post` vuelve a dispararse dentro del AJAX (por ejemplo, si otro plugin llama `wp_update_post` en ese hook), puede producirse un bucle o doble bump.

**Fix recomendado:** Reutilizar el mismo mecanismo de semáforo (idealmente extraído a un método privado compartido).

---

### ~~🔴 BUG-03 — `sync_scheduled_entries` limpia y re-programa en la misma llamada, doble trabajo~~

**Archivo:** `includes/class-wcc-cron.php`, líneas 47–66  
**Severidad:** Media

`sync_scheduled_entries` llama a `clear_scheduled_entries` internamente **y además** el llamador en `save_post` ya llama previamente a `clear_scheduled_entries`:

```php
// class-wcc-admin.php líneas 325-326
WCC_Cron::clear_scheduled_entries( $post_id, $old_entries );
WCC_Cron::sync_scheduled_entries( $post_id, $entries );

// Dentro de sync_scheduled_entries:
self::clear_scheduled_entries( $post_id, $entries );  // ← se ejecuta dos veces
```

Esto provoca que todos los eventos se limpien **dos veces** en cada guardado, lo cual es redundante e ineficiente. La segunda limpieza (dentro de `sync`) usa el array de `$entries` nuevas (no las viejas), por lo que técnicamente puede dejar eventos huérfanos de entradas que se eliminaron.

**Fix:** Eliminar la llamada a `clear_scheduled_entries` del llamador externo en `save_post` y `ajax_save_single_entry`, dejando que `sync_scheduled_entries` lo gestione internamente con las entradas viejas como parámetro adicional.

---

### ~~🟡 BUG-04 — La fecha del post se actualiza con `current_time('mysql')` en vez de la fecha de la entrega~~

**Archivo:** `includes/class-wcc-admin.php`, líneas 333–334; `class-wcc-cron.php`, líneas 102–103  
**Severidad:** Media

Cuando una entrega programada se publica (ya sea manual o vía cron), el post principal se bump-ea con `current_time('mysql')` (= ahora). Si la entrega tenía fecha/hora en el futuro y el cron se ejecuta puntualmente, esto es correcto. Pero si el cron se retrasa (lo normal en sitios con poco tráfico), el post aparecerá más reciente de lo esperado.

**Fix sugerido:** Usar la fecha/hora de la propia entrega como `post_date` y `post_date_gmt` para que el post aparezca exactamente en el momento que el usuario configuró.

---

### ~~🟡 BUG-05 — El orden de entrada en el índice del frontend no coincide con el orden del acordeón~~

**Archivo:** `includes/class-wcc-frontend.php`, líneas 107–109  
**Severidad:** Baja

El índice de entregas usa el mismo array `$entries` ordenado (por `wcc_sort_entries`), pero la variable `$number` en el bucle del índice no refleja el número de posición real porque se usan los índices del array después del `array_values`. Cuando el orden es `desc`, la entrega `0` (primera en pantalla) es la más reciente, pero el índice también la lista primera. Esto es consistente, pero puede ser confuso si el usuario espera ver el índice siempre cronológicamente ascendente.

---

### ~~🟡 BUG-06 — Validación de `active_ids` incompleta en el AJAX~~

**Archivo:** `includes/class-wcc-admin.php`, línea 379  
**Severidad:** Media

```php
$active_ids = isset( $_POST['active_ids'] ) && is_array( $_POST['active_ids'] )
    ? array_map( 'sanitize_key', $_POST['active_ids'] )
    : array();
```

El array `active_ids` recibe `sanitize_key()` que transforma todo a minúsculas y elimina caracteres no permitidos. Los IDs son UUIDs generados con `wp_generate_uuid4()` (guiones incluidos). `sanitize_key()` **elimina los guiones**, haciendo que los IDs procesados no coincidan con los almacenados.

**Fix:** Usar `sanitize_text_field()` + validación con regex UUID para cada elemento:
```php
$active_ids = array_filter(
    array_map( 'sanitize_text_field', wp_unslash( $_POST['active_ids'] ) ),
    fn( $id ) => preg_match( '/^[0-9a-f\-]{36}$/', $id )
);
```

---

### ~~🟡 BUG-07 — `wp_unslash` faltante en `$_POST['entry']` dentro del AJAX~~

**Archivo:** `includes/class-wcc-admin.php`, línea 355  
**Severidad:** Media

```php
$entry_data = isset( $_POST['entry'] ) && is_array( $_POST['entry'] )
    ? wp_unslash( $_POST['entry'] )
    : null;
```

Aquí `wp_unslash` sí está, pero en el campo de contenido HTML (que puede contener comillas y barras), la sanitización posterior con `wp_kses_post()` podría recibir el contenido ya con las barras removidas correctamente. Sin embargo, si el contenido tiene secuencias de escape válidas, el `wp_unslash` anidado dentro de `sanitize_entry` podría no estar garantizado. El flujo es correcto pero debería documentarse explícitamente.

---

### ~~🟢 BUG-08 — Mensajes hardcodeados en español en admin.js~~

**Archivo:** `assets/admin.js`, líneas 158, 166, 231, 232  
**Severidad:** Baja

```js
alert( 'El título y el contenido no pueden estar vacíos.' );
alert( 'No se pudo obtener el ID del post.' );
alert( 'Ocurrió un error en la comunicación con el servidor.' );
showNotice( 'Ocurrió un error en la comunicación con el servidor.', 'error' );
```

Estos mensajes están hardcodeados en español, ignorando el objeto `wccAdmin` localizado. Si el plugin se traduce al inglés u otro idioma, estos mensajes no cambiarán.

**Fix:** Agregar estas cadenas al array `wp_localize_script` en `admin_assets()` y referenciarlas desde `wccAdmin.*`.

---

## 4. Vulnerabilidades de seguridad

### ~~🔴 SEC-01 — CSS injection a través de variables CSS personalizadas~~

**Archivo:** `weekly-content-concatenator.php`, línea 151  
**Severidad:** Alta

```php
$custom_css .= '  ' . $css_var . ': ' . esc_attr( $styles[ $key ] ) . ';' . "\n";
```

`esc_attr()` escapa correctamente para atributos HTML pero **no es la función correcta para sanitizar valores CSS**. Un valor como `red; } body { display:none; } :root {` pasaría `esc_attr()` e inyectaría CSS arbitrario en la página.

La sanitización correcta ya ocurre al guardar mediante `sanitize_styles()` + `is_valid_color()`, pero si los datos de la base de datos fueron insertados por otra vía (import de opciones, migración, etc.), la función de salida no tiene una segunda línea de defensa.

**Fix doble:**
1. Al renderizar, re-validar el color con `is_valid_color()` antes de emitirlo.
2. Cambiar `esc_attr()` por una función de escape CSS o aplicar `wp_strip_all_tags()` como mínimo.

```php
if ( self::is_valid_color( $styles[ $key ] ) ) {
    $custom_css .= '  ' . $css_var . ': ' . wp_strip_all_tags( $styles[ $key ] ) . ';' . "\n";
}
```

---

### ~~🟡 SEC-02 — SSRF potencial en el shortcode con `id` de un post privado~~

**Archivo:** `includes/class-wcc-frontend.php`, líneas 194–196  
**Severidad:** Baja-Media

```php
if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
    return '';
}
```

La validación es correcta, pero `is_post_publicly_viewable()` puede devolver `true` para posts en estado `future`. Un usuario podría usar `[contenido_semanal id="X"]` para acceder al contenido de un post programado antes de su publicación si el tema no aplica restricciones adicionales.

**Fix:** Añadir verificación de `post_status === 'publish'` para usuarios no autenticados.

---

### ~~🟡 SEC-03 — Falta de limitación de tasa en el endpoint AJAX~~

**Archivo:** `includes/class-wcc-admin.php`, línea 345  
**Severidad:** Media

El endpoint `wcc_save_single_entry` solo requiere un nonce válido y permisos de edición. No existe ningún rate limiting. Un editor malintencionado podría enviar miles de entregas en loop para saturar la base de datos con post_meta enormes.

**Fix:** Limitar el número máximo de entregas por post (p.ej., 500) en `ajax_save_single_entry`.

---

## 5. Problemas de rendimiento

### ~~🟡 PERF-01 — `get_post_meta` múltiple sin caché explícita~~

**Archivo:** `includes/class-wcc-frontend.php`, líneas 73–74 y 91  
**Severidad:** Baja-Media

```php
$entries    = get_post_meta( $post_id, '_wcc_entries', true );
$order      = get_post_meta( $post_id, '_wcc_order', true );
// ...
$hide_index = get_post_meta( $post_id, '_wcc_hide_index', true );
```

Se realizan 3 llamadas separadas a `get_post_meta`. WordPress cachea internamente el post meta, por lo que el impacto es bajo, pero se puede mejorar agrupando con una llamada sin clave para cargar todo el meta en un paso.

---

### ~~🟡 PERF-02 — `get_entries_html` encola assets **aunque se llame desde shortcode en página de archivo**~~

**Archivo:** `includes/class-wcc-frontend.php`, líneas 94–96  
**Severidad:** Media

```php
wp_enqueue_style( 'wcc-frontend', ... );
wp_enqueue_script( 'wcc-frontend', ... );
wcc_add_custom_styles();
```

Estos llamados dentro de `get_entries_html` ocurren en el momento del filtro `the_content` (o shortcode), que puede ser después de que `wp_head` ya se ejecutó. En ese caso, WordPress encolará los scripts y estilos en el footer, lo cual puede causar FOUC (flash of unstyled content).

**Fix:** En `enqueue_frontend_assets()` ya se hace la detección temprana correcta para el caso del meta `_wcc_enabled`. Para shortcodes en páginas de archivo o widgets, se podría usar `wp_register_style/script` + imprimir en el footer con prioridad alta.

---

### ~~🟡 PERF-03 — `usort` con `wcc_get_entry_timestamp` ejecuta `DateTimeImmutable::createFromFormat` N² veces~~

**Archivo:** `weekly-content-concatenator.php`, líneas 108–118  
**Severidad:** Baja

`wcc_sort_entries` usa `usort` con comparador que llama `wcc_get_entry_timestamp` para ambos elementos. `wcc_get_entry_timestamp` crea un objeto `DateTimeImmutable` en cada llamada. Para listas de 10+ entregas, se crean objetos innecesariamente en cada comparación.

**Fix:** Pre-calcular los timestamps en un array asociativo antes del sort:

```php
function wcc_sort_entries( $entries, $order ) {
    $timestamps = array_map( 'wcc_get_entry_timestamp', $entries );
    usort( $entries, function( $a, $b ) use ( $timestamps, $entries, $order ) {
        $ia = array_search( $a, $entries, true );
        $ib = array_search( $b, $entries, true );
        $cmp = $timestamps[$ia] <=> $timestamps[$ib];
        return 'asc' === $order ? $cmp : -$cmp;
    } );
    return $entries;
}
```

O más limpiamente, usar `array_map` + `array_multisort`.

---

### ~~🟢 PERF-04 — `static $added = false` en `wcc_add_custom_styles` no es thread-safe entre múltiples shortcodes~~

**Archivo:** `weekly-content-concatenator.php`, línea 124  
**Severidad:** Muy baja

La variable estática funciona correctamente en el modelo de ejecución single-threaded de PHP. Sin embargo, si en una página hay múltiples shortcodes `[contenido_semanal]`, la función se ejecuta solo la primera vez. Esto es el comportamiento correcto, pero el comentario en el código no documenta este comportamiento intencional.

---

## 6. Archivos y código huérfano

### ~~📦 HUÉRFANO-01 — `weekly-content-concatenator 1.5.0.zip`~~

**Ruta:** raíz del plugin  
El archivo ZIP de distribución no debería estar dentro del repositorio de código fuente. Su presencia puede confundir a colaboradores y aumenta el tamaño del repositorio sin valor de versionado.

**Acción recomendada:** Agregar `*.zip` al `.gitignore` y moverlo a una release de GitHub.

---

### ~~📄 HUÉRFANO-02 — `analisis.md` (vacío)~~

**Ruta:** raíz del plugin  
Existe un archivo `analisis.md` vacío (el documento activo abierto en el editor). Fue creado como borrador previo a este análisis.

**Acción recomendada:** Eliminar o reemplazar con el presente documento.

---

### ~~🔤 HUÉRFANO-03 — Directorio `languages/` inexistente pero referenciado~~

**Archivo:** `weekly-content-concatenator.php`, línea 35  
```php
load_plugin_textdomain( 'weekly-content-concatenator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
```

El directorio `languages/` no existe en el proyecto. El text domain se carga pero no hay ningún archivo `.po`/`.mo`. Si alguien intenta traducir el plugin, WordPress buscará ahí los archivos de traducción y no los encontrará.

**Acción recomendada:** Crear el directorio `languages/` y al menos incluir el archivo `.pot` (template de traducción). También se puede aprovechar las traducciones automáticas del repositorio de WordPress.org.

---

### ~~⚙️ HUÉRFANO-04 — `composer.json` incompleto~~

**Ruta:** `composer.json`  
El lint configurado solo analiza el archivo principal:
```json
"lint": "phpcs --standard=WordPress --extensions=php weekly-content-concatenator.php"
```

Los archivos en `includes/` y los assets no están incluidos en el lint. Además, no hay script de test automatizado y no existe `composer.lock` en el repositorio (posiblemente excluido por el `.gitignore`).

**Acción recomendada:**
```json
"lint": "phpcs --standard=WordPress --extensions=php . --ignore=vendor/",
"lint:fix": "phpcbf --standard=WordPress --extensions=php . --ignore=vendor/"
```

---

### ~~🌍 HUÉRFANO-05 — Carpeta `vendor/` ausente pero implícita~~

Si un colaborador clona el repositorio y ejecuta `composer install`, se creará la carpeta `vendor/`. El `.gitignore` debería incluir `vendor/` si no lo hace ya. (No se puede verificar el `.gitignore` completo, pero merece comprobarse.)

---

## 7. Deuda técnica y código mejorable

### ~~🔧 DEUDA-01 — Duplicación de lógica "bump" entre `save_post` y `ajax_save_single_entry`~~

**Archivos:** `class-wcc-admin.php` líneas 309–338 y 420–438  

La detección de si es una entrega nueva publicada y el `wp_update_post` subsiguiente están duplicados en ambos métodos. Si se necesita modificar la lógica de bump, hay que hacerlo en dos lugares.

**Fix:** Extraer a un método privado `maybe_bump_post_date( $post_id, $sanitized_entry, $old_ids )`.

---

### ~~🔧 DEUDA-02 — `render_entry_fields` mezcla lógica y presentación~~

**Archivo:** `class-wcc-admin.php`, líneas 48–118  

El método `render_entry_fields` contiene lógica de negocio (construcción de `$status_class`, `$status_label`) mezclada con HTML. Siguiendo el estándar WordPress, sería mejor separar en un método `get_entry_status_meta()` y una plantilla.

---

### ~~🔧 DEUDA-03 — El template de entrega (`#tmpl-wcc-entry`) se renderiza vía PHP pero usa `__INDEX__` como placeholder de JavaScript~~

**Archivo:** `class-wcc-admin.php`, línea 174  
```php
$this->render_entry_fields( array(), '__INDEX__' );
```

Este `__INDEX__` es reemplazado en JavaScript con el índice numérico del contador. El enfoque es funcional pero mezcla las capas de presentación PHP y JS. Si `render_entry_fields` alguna vez necesita un índice numérico para lógica PHP, este string causará problemas.

**Sugerencia:** Usar el sistema de templates de WordPress (`wp_add_inline_script` con JSON de defaults) o el underscore templating propio de WordPress admin.

---

### ~~🔧 DEUDA-04 — `is_valid_color()` es un método estático privado pero es útil en otros contextos~~

**Archivo:** `class-wcc-admin.php`, líneas 553–571  

`is_valid_color()` validaría bien en el output de `wcc_add_custom_styles()` (que está en el archivo principal), pero como es `private static` de `WCC_Admin`, no es accesible desde allí.

**Fix:** Moverla a una función global `wcc_is_valid_color()` o a una clase utilitaria `WCC_Utils`.

---

### ~~🔧 DEUDA-05 — Falta documentación PHPDoc en funciones globales~~

**Archivo:** `weekly-content-concatenator.php`  

Las funciones `wcc_add_custom_styles()`, `wcc_load_textdomain()` tienen docblocks incompletos o ausentes (no documentan el hook donde se ejecutan ni los efectos secundarios).

---

### ~~🔧 DEUDA-06 — `updateHeaderMeta` en admin.js formatea la fecha con string concatenation en vez de Intl.DateTimeFormat~~

**Archivo:** `assets/admin.js`, línea 46  
```js
$dateEl.text( time ? date + ' ' + time : date );
```

La fecha se muestra como `YYYY-MM-DD HH:MM`, que es el formato ISO pero no el formato configurado en WordPress. El backend ya formatea la fecha con `wcc_format_entry_datetime()`, pero el frontend JavaScript usa el valor crudo del `<input type="date">`.

Esto provoca inconsistencia: al guardar una entrega, el badge de fecha muestra `2026-06-08 14:00`, pero al recargar la página muestra la fecha en el formato del sitio (e.g., `8 de junio de 2026 14:00`).

**Fix:** Al guardar con AJAX, devolver también la `formatted_date` desde el servidor y usarla para actualizar el UI.

---

## 8. Mejoras funcionales sugeridas

### 💡 MEJORA-01 — Soporte para entradas tipo "página" (`page`)

Actualmente el plugin solo funciona para `post_type = 'post'`. Hay dos lugares donde está hardcodeado:
- `add_meta_boxes_post` (solo posts)
- `save_post_post` (solo posts)
- `is_singular('post')` en el frontend
- `get_post_type($post_id) === 'post'` en cron

Un ajuste en la página de configuración para elegir los post types soportados haría el plugin mucho más versátil.

---

### 💡 MEJORA-02 — Exportar/Importar entregas en JSON

No existe ningún mecanismo para exportar el contenido de las entregas. Si se migra un sitio o se duplica un post, las entregas se pierden si no se migra el post_meta correctamente.

---

### 💡 MEJORA-03 — Vista previa del frontend desde el admin

Actualmente no hay forma de previsualizar cómo se verán las entregas en el frontend sin salir del editor. Un botón de "Preview" que abra un modal sería útil.

---

### 💡 MEJORA-04 — Contador de palabras por entrega

Para blogs de contenido educativo (que es el caso de uso principal), un contador de palabras por entrega y para el total del post ayudaría al editor.

---

### 💡 MEJORA-05 — Soporte para el editor Gutenberg (bloques)

El plugin usa el editor clásico (meta box debajo del editor). En sitios que usan exclusivamente Gutenberg, la experiencia es subóptima. Sería ideal crear un bloque de Gutenberg nativo `<WCCBlock>` que renderice la misma UI.

---

### 💡 MEJORA-06 — Notificación por email cuando una entrega programada se publica

Cuando el cron publica una entrega, no hay notificación al autor/editor del post. Un email opcional informando que "la entrega X se publicó automáticamente" mejoraría la experiencia.

---

### 💡 MEJORA-07 — Limite configurable de número de entregas por post

No hay validación del número máximo de entregas. Un post podría acumular cientos, lo que impactaría en el rendimiento de carga del meta box y del frontend.

---

##### 9. Accesibilidad

### ~~♿ ACC-01 — `tabindex="0"` innecesario en `<summary>`~~

**Archivo:** `includes/class-wcc-frontend.php`, línea 117  
**Severidad:** Baja

```html
<summary class="wcc-weekly-entry__summary" tabindex="0">
```

El elemento `<summary>` es focusable por defecto en todos los browsers modernos. Agregar `tabindex="0"` es redundante y puede crear problemas de doble foco en algunos lectores de pantalla.

**Fix:** Eliminar `tabindex="0"`.

---

### ~~♿ ACC-02 — El indicador expand/collapse (+ / −) solo es visual~~

**Archivo:** `assets/frontend.css`, líneas 96–111  
**Severidad:** Baja

El indicador `+` / `−` se agrega vía CSS `::after`, por lo que no es legible por lectores de pantalla. Los elementos nativos `<details>`/`<summary>` ya manejan el estado abierto/cerrado semánticamente, pero el indicador visual no tiene equivalente accesible.

**Fix:** No es crítico ya que `<details>` es semánticamente correcto, pero se podría agregar `aria-label` dinámico vía JS para enriquecer la experiencia.

---

### ~~♿ ACC-03 — Sin `aria-live` en las notificaciones AJAX~~

**Archivo:** `assets/admin.js`, líneas 60–68  
**Severidad:** Media

Las notificaciones de éxito/error que se muestran tras guardar no tienen `role="alert"` ni `aria-live`, por lo que los lectores de pantalla no las anunciarán automáticamente.

**Fix:**
```js
$notice.attr( 'role', 'alert' ).attr( 'aria-live', 'polite' );
```

---

### ~~♿ ACC-04 — El botón "Abrir/cerrar" no describe su estado~~

**Archivo:** `class-wcc-admin.php`, línea 83  
```html
<button type="button" class="button-link wcc-toggle-entry">Abrir/cerrar</button>
```

El botón no tiene `aria-expanded` ni `aria-controls`, por lo que un usuario de lector de pantalla no puede saber si la entrega está abierta o cerrada.

**Fix:**
```html
<button type="button" class="button-link wcc-toggle-entry" aria-expanded="false" aria-controls="wcc-body-{id}">
    Abrir/cerrar
</button>
```

---

## 10. Internacionalización (i18n)

### ~~🌐 I18N-01 — `readme.txt` no tiene tildes ni caracteres especiales del español~~

El `readme.txt` está escrito sin tildes ("Descripcion" en lugar de "Descripción", "Caracteristicas" en lugar de "Características"). Si el plugin se publica en el repositorio de WordPress.org, esto aparecerá como texto defectuoso en la página del plugin.

**Fix:** Reescribir el `readme.txt` con ortografía correcta en español.

---

### ~~🌐 I18N-02 — El text domain correcto NO coincide con el slug del plugin~~

El header del plugin declara `Text Domain: weekly-content-concatenator` ✅. Todas las llamadas `__()`, `esc_html_e()` etc. usan correctamente `'weekly-content-concatenator'` ✅. Este ítem es correcto, solo se documenta para confirmación.

---

### ~~🌐 I18N-03 — Ausencia de plurales correctos en inglés si el plugin se traduce~~

**Archivo:** `class-wcc-admin.php`, líneas 166–167  
```php
( 1 === $wcc_total_entries ? __( 'entrega' ) : __( 'entregas' ) )
```

La pluralización manual con ternario no funciona para idiomas con reglas de plural complejas (ruso, árabe). WordPress provee `_n()` para esto.

**Fix:**
```php
_n( 'entrega', 'entregas', $wcc_total_entries, 'weekly-content-concatenator' )
```

---

### ~~🌐 I18N-04 — La cadena `'✓'` y `'...'` están localizadas innecesariamente~~

**Archivo:** `class-wcc-admin.php`, líneas 208–209  
```php
'labelSaving' => __( '...', 'weekly-content-concatenator' ),
'labelSaved'  => __( '✓', 'weekly-content-concatenator' ),
```

Estos símbolos son universales y no necesitan traducción. Localizar `'...'` y `'✓'` solo agrega entradas al archivo `.pot` sin beneficio real.

---

## 11. Compatibilidad y estándares

### ~~🔩 COMP-01 — PHP mínimo 7.4 pero se usa sintaxis compatible con 8.x~~

El plugin declara `Requires PHP: 7.4`. El código usa `DateTimeImmutable`, `fn()` (arrow functions PHP 7.4+) y el operador spaceship `<=>` (PHP 7.0+). La compatibilidad declarada es correcta.

Sin embargo, si en el futuro se añaden named arguments, `match()`, o `readonly` properties, la compatibilidad mínima subiría a PHP 8.0 sin actualizar el `readme.txt`.

---

### ~~🔩 COMP-02 — WordPress mínimo 6.0 pero `wp_generate_uuid4()` existe desde 4.7~~

`wp_generate_uuid4()` está disponible desde WP 4.7, por lo que no hay problema de compatibilidad. ✅

---

### ~~🔩 COMP-03 — `is_post_publicly_viewable()` existe desde WP 5.7~~

El requisito mínimo es WP 6.0, así que `is_post_publicly_viewable()` está disponible. ✅

---

### ~~🔩 COMP-04 — `FILTER_VALIDATE_BOOLEAN` deprecado en PHP 8.2+ para valores `null`~~

**Archivo:** `class-wcc-frontend.php`, línea 199  
```php
$hide_index = filter_var( $hide_index, FILTER_VALIDATE_BOOLEAN );
```

`filter_var()` con `FILTER_VALIDATE_BOOLEAN` en strings como `"true"` o `"false"` funciona correctamente. Sin embargo, en PHP 8.2+ con valores como `""` (vacío), el comportamiento es ligeramente diferente. El código ya protege esto con la condición `'' !== $hide_index` en línea 198.  ✅ (Análisis correcto)

---

### ~~🔩 COMP-05 — `<details>`/`<summary>` en WordPress Classic Editor (IE11)~~

El elemento `<details>` no es compatible con IE11. WordPress 6.0+ ya no soporta IE11 oficialmente, por lo que esto es aceptable. Sin embargo, si el sitio tiene usuarios con navegadores muy viejos, el acordeón simplemente mostrará todo el contenido expandido (degradación elegante).

---

## 12. Desinstalación incompleta

### ~~🗑️ UNINST-01 — `uninstall.php` no elimina los post_meta de las entregas~~

**Archivo:** `uninstall.php`  

```php
delete_option( 'wcc_styles' );
```

Solo se borra la opción global `wcc_styles`. Los post_meta `_wcc_entries`, `_wcc_enabled`, `_wcc_order`, `_wcc_hide_index` quedan en la base de datos para **todos los posts** que usaron el plugin.

Además, los transients `wcc_bump_notice_{user_id}` y los eventos de WP-Cron programados no se limpian.

**Fix recomendado:**

```php
// Borrar post_meta de todos los posts
$post_ids = get_posts( [
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_key'       => '_wcc_entries',
] );

foreach ( $post_ids as $post_id ) {
    $entries = get_post_meta( $post_id, '_wcc_entries', true );
    if ( is_array( $entries ) ) {
        WCC_Cron::clear_scheduled_entries( $post_id, $entries );
    }
    delete_post_meta( $post_id, '_wcc_entries' );
    delete_post_meta( $post_id, '_wcc_enabled' );
    delete_post_meta( $post_id, '_wcc_order' );
    delete_post_meta( $post_id, '_wcc_hide_index' );
}

delete_option( 'wcc_styles' );

// Limpiar transients (patrón)
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcc_bump_notice_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wcc_bump_notice_%'" );
```

> **⚠️ Advertencia:** La limpieza masiva de post_meta en `uninstall.php` puede ser muy lenta en sitios grandes. Considerar hacerlo en un proceso en background o mostrar un aviso al admin antes de desinstalar.

---

## 13. Resumen de prioridades

| ID | Descripción | Severidad | Esfuerzo | Prioridad | Estado |
|---|---|---|---|---|---|
| BUG-06 | `sanitize_key` destruye UUIDs en `active_ids` | 🔴 Alta | Bajo | **1** | Corregido |
| SEC-01 | CSS injection en salida de variables CSS | 🔴 Alta | Bajo | **2** | Corregido |
| BUG-01 | Semáforo anti-reentrada frágil en `save_post` | 🔴 Alta | Medio | **3** | Corregido |
| BUG-02 | Sin semáforo en `ajax_save_single_entry` | 🔴 Alta | Medio | **4** | Corregido |
| UNINST-01 | Desinstalación no limpia post_meta ni crons | 🟡 Media | Medio | **5** | Corregido |
| BUG-03 | Doble limpieza de cron events | 🟡 Media | Bajo | **6** | Corregido |
| BUG-08 | Mensajes JS hardcodeados en español | 🟢 Baja | Bajo | **7** | Corregido |
| I18N-03 | Plurales con ternario en vez de `_n()` | 🟢 Baja | Bajo | **8** | Corregido |
| ACC-03 | Sin `aria-live` en las notificaciones AJAX | 🟡 Media | Bajo | **9** | Corregido |
| ACC-04 | Botón toggle sin `aria-expanded` | 🟡 Media | Bajo | **10** | Corregido |
| DEUDA-01 | Lógica bump duplicada (refactor) | 🟡 Media | Medio | **11** | Corregido |
| PERF-02 | Assets encolados tarde en shortcode | 🟡 Media | Medio | **12** | Corregido |
| DEUDA-06 | Fecha en JS no coincide con formato del sitio | 🟡 Media | Medio | **13** | Corregido |
| HUÉRFANO-01 | ZIP en raíz del repositorio | 🟢 Baja | Muy bajo | **14** | Corregido |
| HUÉRFANO-03 | Directorio `languages/` inexistente | 🟢 Baja | Bajo | **15** | Corregido |
| I18N-01 | `readme.txt` sin tildes | 🟢 Baja | Muy bajo | **16** | Corregido |
| COMP-04 | lint solo cubre el archivo principal | 🟢 Baja | Muy bajo | **17** | Corregido |
| MEJORA-05 | Soporte Gutenberg | 💡 Mejora | Alto | **Backlog** | Pendiente |
| MEJORA-01 | Soporte para otros post types | 💡 Mejora | Medio | **Backlog** | Pendiente |
| MEJORA-06 | Notificación por email al publicar | 💡 Mejora | Medio | **Backlog** | Pendiente |

---

*Análisis realizado el 2026-06-08. Versión analizada: 1.5.0.*
