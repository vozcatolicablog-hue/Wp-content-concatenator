# Desarrollo

## Requisitos

* WordPress 6.0 o superior.
* PHP 7.4 o superior.
* Composer para ejecutar lint local.

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

El archivo `Wp Content Concatenator.zip` se mantiene como artefacto de distribucion rapida en la raiz del repo. Regenerarlo despues de cambios de release:

```powershell
Compress-Archive -LiteralPath 'readme.txt','weekly-content-concatenator.php','assets' -DestinationPath 'Wp Content Concatenator.zip' -Force
```

## Checklist de release

* Actualizar `Version` y `WCC_VERSION` en `weekly-content-concatenator.php`.
* Actualizar `Stable tag`, `Changelog` y `Upgrade Notice` en `readme.txt`.
* Regenerar `Wp Content Concatenator.zip`.
* Ejecutar lint si PHP y Composer estan disponibles.
