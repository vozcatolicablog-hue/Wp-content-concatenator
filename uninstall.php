<?php
/**
 * Script de desinstalación. Limpia opciones de configuración al eliminar el plugin.
 *
 * @package WeeklyContentConcatenator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Borrar la opción global de estilos configurada por el plugin
delete_option( 'wcc_styles' );
