# Desarrollo

## Requisitos

* WordPress 6.0 o superior.
* PHP 7.4 o superior.
* Composer para ejecutar lint local.

## Estructura

```
weekly-content-concatenator.php   Bootstrap y funciones globales (fechas, orden, numeración, estilos, caché)
includes/class-wcc-admin.php      Meta box «Entregas semanales», guardado, AJAX, página de ajustes
includes/class-wcc-cron.php       Publicación programada con WP-Cron
includes/class-wcc-frontend.php   Marcado público, shortcode [contenido_semanal], assets
assets/frontend.css / .js         Sección pública (sistema de diseño)
assets/admin.css / .js            Meta box
assets/settings.js                Selector de color de la página de ajustes
```

## Sistema de diseño

La sección pública cuelga de la clase raíz `.wcc-ds` y usa tokens `--wcc-*` que leen las variables
globales del sitio (`--azul`, `--pergamino`, `--tinta`, `--linea`, `--oro-texto`…) con el valor del
sistema de Voz Católica como reserva. No se autoalojan fuentes: la sección vive dentro de una entrada,
donde el sitio ya carga Cormorant Garamond e Inter.

Reglas que se siguen: acciones y controles con radio 4 px, contenedores 8 px, insignias 2 px; el oro es
puntuación (numerales en `oro-texto`), nunca fondo; sin cursivas; foco visible de 3 px separado 2 px;
objetivos táctiles de 44 px; sin animación con `prefers-reduced-motion`; nada que parezca un control
deja de serlo (`<details>`/`<summary>` nativos).

Gotchas con Elementor: el Kit estiliza `h2`, `a` y `summary` a 0,1,1 (y `:hover`/`:focus` a 0,2,1), y
la plantilla de entrada individual fuerza los `h2` del cuerpo a Cormorant 600 34/40 con `!important`
(28/33 en móvil); el CSS usa 0,2,0 o más y adopta esos mismos tamaños para no pelear.

Los colores de la pantalla de ajustes (`wcc_styles`) solo se imprimen si `usar_sistema` es `'0'`; sus
variables antiguas `--wcc-entry-*` / `--wcc-index-*` siguen leyéndose en el CSS con reserva al sistema.

## Lint PHP

Instalar dependencias:

```bash
composer install
```

Ejecutar WordPress Coding Standards:

```bash
composer lint
```

Aplicar correcciones automaticas cuando sea posible:

```bash
composer lint:fix
```

## Empaquetado

El ZIP de distribución se guarda en `000 Versiones/` (ignorado por git). Debe incluir `includes/` y
`uninstall.php`, no solo el archivo principal y los assets:

No usar `Compress-Archive` de PowerShell: escribe las rutas con `\` y al extraer en Linux (ZipArchive) se
crean archivos con el nombre literal `weekly-content-concatenator\readme.txt`. Empaquetar con Python, que
escribe `/` y mete todo bajo la carpeta `weekly-content-concatenator/`:

```bash
python - <<'EOF'
import zipfile, os
src = '.'; out = '000 Versiones/weekly-content-concatenator 2.1.1.zip'
files = ['readme.txt','weekly-content-concatenator.php','uninstall.php','languages/.gitkeep'] \
    + ['assets/'+f for f in os.listdir('assets') if f.endswith(('.css','.js'))] \
    + ['includes/'+f for f in os.listdir('includes') if f.endswith('.php')]
with zipfile.ZipFile(out, 'w', zipfile.ZIP_DEFLATED) as z:
    for f in sorted(files):
        z.write(os.path.join(src, f), 'weekly-content-concatenator/' + f)
EOF
```

## Checklist de release

* Actualizar `Version` y `WCC_VERSION` en `weekly-content-concatenator.php`.
* Actualizar `Stable tag`, `Changelog` y `Upgrade Notice` en `readme.txt`.
* Regenerar el ZIP en `000 Versiones/`.
* Ejecutar lint si PHP y Composer estan disponibles.
* Probar en el WordPress local (XAMPP `E:\XAMPP\htdocs\Tester-site`) con un post con entregas antes de subir.
