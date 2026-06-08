=== Concatenador de Contenido Semanal ===
Contributors: vozcatolica
Tags: series, cursos, contenido semanal, publicaciones, shortcodes
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organiza entregas semanales dentro de posts normales y actualiza la fecha del post cuando publicas una entrega nueva.

== Descripción ==

Concatenador de Contenido Semanal agrega un panel de "Entregas semanales" al editor de posts de WordPress.

El plugin está pensado para cursos, series, meditaciones, catequesis, publicaciones por capítulos o cualquier contenido que crece semana a semana sin crear un tipo de contenido nuevo.

Cada entrega permite definir:

* Título.
* Fecha.
* Hora, interpretada según la zona horaria configurada en WordPress.
* Estado: borrador, programada o publicada.
* Contenido compatible con HTML permitido y shortcodes.

Las entregas publicadas pueden mostrarse automáticamente al final del post o insertarse manualmente con el shortcode `[contenido_semanal]`.

Cuando guardas una entrega nueva con estado "Publicada" dentro de un post ya publicado, el plugin actualiza la fecha del post. Así, el post vuelve a aparecer como el más reciente en listados ordenados por fecha.

== Características ==

* Panel de entregas semanales en posts normales.
* Entregas en borrador o publicadas.
* Entregas programadas con fecha y hora según la zona horaria de WordPress.
* Visualización tipo acordeón con elementos nativos `<details>` y `<summary>`.
* Índice colapsable enlazado a cada entrega.
* Orden visible configurable: más recientes primero o más antiguas primero.
* Opción para ocultar el índice.
* Shortcode para controlar la ubicación manual.
* Guardado individual de entregas mediante AJAX.
* Actualización automática de la fecha del post al publicar una entrega nueva.
* Estilos separados para admin y frontend.

== Instalación ==

1. Sube la carpeta `weekly-content-concatenator` a `/wp-content/plugins/`.
2. Activa "Concatenador de Contenido Semanal" desde el menú Plugins.
3. Abre o crea un post normal.
4. Busca el panel "Entregas semanales" debajo del editor.
5. Agrega una entrega y, si corresponde, cambia su estado a "Publicada".
6. Marca "Mostrar las entregas automáticamente al final de este post" si quieres que aparezcan al final del contenido.

== Uso ==

Para mostrar las entregas automáticamente, activa la opción:

`Mostrar las entregas automáticamente al final de este post`

Para controlar manualmente dónde aparecen, desactiva la visualización automática e inserta:

`[contenido_semanal]`

También puedes mostrar las entregas de otro post indicando su ID:

`[contenido_semanal id="123"]`

Para forzar que el índice se oculte o se muestre desde el shortcode:

`[contenido_semanal hide_index="true"]`

`[contenido_semanal hide_index="false"]`

== Comportamiento de la fecha ==

El post vuelve al inicio solo cuando el plugin detecta por primera vez una entrega publicada.

Editar una entrega que ya estaba publicada no vuelve a actualizar la fecha del post. Publicar una entrega nueva sí actualiza la fecha, siempre que el post principal ya esté publicado y la fecha de la entrega no sea pasada (anterior a hoy).

== Preguntas frecuentes ==

= ¿El plugin crea un nuevo tipo de contenido? =

No. Trabaja sobre posts normales de WordPress.

= ¿Puedo usar shortcodes dentro de una entrega? =

Sí. El contenido de cada entrega admite shortcodes y HTML permitido según los permisos del usuario.

= ¿Puedo dejar entregas preparadas sin publicarlas? =

Sí. Las entregas en estado "Borrador" se guardan, pero no aparecen en el frontend.

= ¿Qué pasa si publico una entrega dentro de un post en borrador? =

La entrega se guarda, pero la fecha del post no se actualiza para listados públicos hasta que el post principal esté publicado.

= ¿Puedo ocultar el índice de entregas? =

Sí. Puedes ocultarlo desde el panel del post o con el atributo `hide_index` del shortcode.

== Capturas ==

1. Panel de entregas semanales en el editor de posts.
2. Visualización frontend en acordeón con índice de entregas.

== Changelog ==

= 2.0.1 =

* Corrección: Corrige un problema que impedía la visualización de iframes y elementos embed de video en el frontend al eliminar el filtrado incorrecto de wp_kses_post sobre el contenido ya procesado.

= 2.0.0 =

* Versión 2.0.0: Actualización mayor del plugin.

= 1.5.1 =

* Seguridad: Implementa validación de colores (RGB/RGBA, HSL/HSLA, HEX) robusta para evitar inyección CSS.
* Rendimiento: Optimiza la ordenación de entregas usando array_multisort en lugar de usort con creación repetitiva de DateTimeImmutable.
* Estabilidad: Previene condiciones de carrera y bucles de reentrada al guardar o realizar bump de posts mediante una variable estática de IDs activos.
* Límite de Entregas: Añade límite preventivo de un máximo de 500 entregas por post para evitar sobrecarga de la base de datos.
* Desinstalación: El desinstalador ahora elimina todos los post_meta y transients asociados creados por el plugin.
* AJAX y Correcciones: Filtra IDs de entregas eliminadas mediante regex de UUID v4 para conservar guiones, reordena comprobaciones de seguridad e integra formato localizado de fecha en la respuesta.
* Accesibilidad: Añade roles alert y aria-live a avisos AJAX, remueve tabindex redundante en summary y añade aria-expanded y aria-controls al botón toggle de entregas.
* Internacionalización: Traduce avisos JS hardcodeados y utiliza _n() de WordPress para un correcto manejo de plurales.

= 1.5.0 =

* Agrega estado "Programada" para publicar entregas en fecha y hora usando la zona horaria configurada en WordPress.
* Programa eventos de WP-Cron para publicar automáticamente las entregas cuando llega su horario.
* Quita el número visible de cada acordeón en el frontend.
* Agrega campo de hora al panel de entregas.
* Actualiza el orden y visualización de fechas para considerar fecha y hora.

= 1.4.0 =

* Agrega un panel de configuración en Ajustes > Concatenador Semanal para personalizar colores y estilos.
* Carga diferida de iframes en acordeones cerrados para optimizar el rendimiento y velocidad del post.

= 1.3.0 =

* Evita duplicar entregas cuando el post tiene visualización automática y también usa el shortcode.
* Mejora la carga temprana de assets cuando el shortcode se usa en contenido singular.
* Valida mejor el atributo `id` del shortcode.
* El guardado individual también sincroniza las opciones generales del panel.
* Agrega aviso visual cuando una entrega guardada por AJAX actualiza la fecha del post.
* Agrega documentación técnica y configuración básica de lint.

= 1.2.1 =

* Ajustes menores de mantenimiento.

= 1.2.0 =

* Agregado guardado individual de entregas mediante AJAX.
* Mejorada la carga de assets frontend para posts con entregas semanales.
* Validación de fechas con `checkdate()`.
* Prevención de reentrada al actualizar la fecha del post.
* Mejoras menores en avisos, estados y metadatos visibles en el panel de admin.

= 1.1.0 =

* Reemplazado el listado largo de entregas por una visualización en acordeón accesible.
* Agregado índice colapsable de entregas.
* Mejorado el diseño mobile-first del frontend.
* Mejorado el panel de admin con entregas colapsables, badges de estado y fecha visible.

= 1.0.0 =

* Versión inicial.
* Panel de entregas semanales para posts.
* Rúnder automático al final del contenido.
* Shortcode `[contenido_semanal]`.
* Actualización de fecha del post al publicar una entrega nueva.

== Upgrade Notice ==

= 2.0.1 =

Corrige la visualización de iframes y embeds de video (como videos de YouTube) en el frontend.

= 2.0.0 =

Lanzamiento de la versión 2.0.0.

= 1.5.1 =

Corrige problemas de seguridad por inyección CSS, condiciones de carrera de guardado, limpia la base de datos al desinstalar, limita a 500 entregas por post y mejora la accesibilidad y traducciones.

= 1.5.0 =

Actualiza para programar publicaciones de entregas con la zona horaria de WordPress y remover el número visible de los acordeones.

= 1.4.0 =

Actualiza para acceder al panel de configuración de estilos y mejorar el rendimiento de la página con la carga diferida de iframes.

= 1.3.0 =

Actualiza para evitar duplicados con shortcode, mejorar la carga de assets y sincronizar opciones durante el guardado individual.
