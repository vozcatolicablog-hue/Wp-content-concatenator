=== Concatenador de Contenido Semanal ===
Contributors: vozcatolica
Tags: series, cursos, contenido semanal, publicaciones, shortcodes
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organiza entregas semanales dentro de posts normales y actualiza la fecha del post cuando publicas una entrega nueva.

== Descripcion ==

Concatenador de Contenido Semanal agrega un panel de "Entregas semanales" al editor de posts de WordPress.

El plugin esta pensado para cursos, series, meditaciones, catequesis, publicaciones por capitulos o cualquier contenido que crece semana a semana sin crear un tipo de contenido nuevo.

Cada entrega permite definir:

* Titulo.
* Fecha.
* Estado: borrador o publicada.
* Contenido compatible con HTML permitido y shortcodes.

Las entregas publicadas pueden mostrarse automaticamente al final del post o insertarse manualmente con el shortcode `[contenido_semanal]`.

Cuando guardas una entrega nueva con estado "Publicada" dentro de un post ya publicado, el plugin actualiza la fecha del post. Asi, el post vuelve a aparecer como el mas reciente en listados ordenados por fecha.

== Caracteristicas ==

* Panel de entregas semanales en posts normales.
* Entregas en borrador o publicadas.
* Visualizacion tipo acordeon con elementos nativos `<details>` y `<summary>`.
* Indice colapsable enlazado a cada entrega.
* Orden visible configurable: mas recientes primero o mas antiguas primero.
* Opcion para ocultar el indice.
* Shortcode para controlar la ubicacion manual.
* Guardado individual de entregas mediante AJAX.
* Actualizacion automatica de la fecha del post al publicar una entrega nueva.
* Estilos separados para admin y frontend.

== Instalacion ==

1. Sube la carpeta `weekly-content-concatenator` a `/wp-content/plugins/`.
2. Activa "Concatenador de Contenido Semanal" desde el menu Plugins.
3. Abre o crea un post normal.
4. Busca el panel "Entregas semanales" debajo del editor.
5. Agrega una entrega y, si corresponde, cambia su estado a "Publicada".
6. Marca "Mostrar las entregas automaticamente al final de este post" si quieres que aparezcan al final del contenido.

== Uso ==

Para mostrar las entregas automaticamente, activa la opcion:

`Mostrar las entregas automaticamente al final de este post`

Para controlar manualmente donde aparecen, desactiva la visualizacion automatica e inserta:

`[contenido_semanal]`

Tambien puedes mostrar las entregas de otro post indicando su ID:

`[contenido_semanal id="123"]`

Para forzar que el indice se oculte o se muestre desde el shortcode:

`[contenido_semanal hide_index="true"]`

`[contenido_semanal hide_index="false"]`

== Comportamiento de la fecha ==

El post vuelve al inicio solo cuando el plugin detecta por primera vez una entrega publicada.

Editar una entrega que ya estaba publicada no vuelve a actualizar la fecha del post. Publicar una entrega nueva si actualiza la fecha, siempre que el post principal ya este publicado y la fecha de la entrega no sea pasada (anterior a hoy).

== Preguntas frecuentes ==

= El plugin crea un nuevo tipo de contenido? =

No. Trabaja sobre posts normales de WordPress.

= Puedo usar shortcodes dentro de una entrega? =

Si. El contenido de cada entrega admite shortcodes y HTML permitido segun los permisos del usuario.

= Puedo dejar entregas preparadas sin publicarlas? =

Si. Las entregas en estado "Borrador" se guardan, pero no aparecen en el frontend.

= Que pasa si publico una entrega dentro de un post en borrador? =

La entrega se guarda, pero la fecha del post no se actualiza para listados publicos hasta que el post principal este publicado.

= Puedo ocultar el indice de entregas? =

Si. Puedes ocultarlo desde el panel del post o con el atributo `hide_index` del shortcode.

== Capturas ==

1. Panel de entregas semanales en el editor de posts.
2. Visualizacion frontend en acordeon con indice de entregas.

== Changelog ==

= 1.3.0 =

* Evita duplicar entregas cuando el post tiene visualizacion automatica y tambien usa el shortcode.
* Mejora la carga temprana de assets cuando el shortcode se usa en contenido singular.
* Valida mejor el atributo `id` del shortcode.
* El guardado individual tambien sincroniza las opciones generales del panel.
* Agrega aviso visual cuando una entrega guardada por AJAX actualiza la fecha del post.
* Agrega documentacion tecnica y configuracion basica de lint.

= 1.2.1 =

* Ajustes menores de mantenimiento.

= 1.2.0 =

* Agregado guardado individual de entregas mediante AJAX.
* Mejorada la carga de assets frontend para posts con entregas semanales.
* Validacion de fechas con `checkdate()`.
* Prevencion de reentrada al actualizar la fecha del post.
* Mejoras menores en avisos, estados y metadatos visibles en el panel de admin.

= 1.1.0 =

* Reemplazado el listado largo de entregas por una visualizacion en acordeon accesible.
* Agregado indice colapsable de entregas.
* Mejorado el diseno mobile-first del frontend.
* Mejorado el panel de admin con entregas colapsables, badges de estado y fecha visible.

= 1.0.0 =

* Version inicial.
* Panel de entregas semanales para posts.
* Render automatico al final del contenido.
* Shortcode `[contenido_semanal]`.
* Actualizacion de fecha del post al publicar una entrega nueva.

== Upgrade Notice ==

= 1.3.0 =

Actualiza para evitar duplicados con shortcode, mejorar la carga de assets y sincronizar opciones durante el guardado individual.
