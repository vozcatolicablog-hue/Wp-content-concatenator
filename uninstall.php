<?php
/**
 * Script de desinstalación. Limpia opciones de configuración al eliminar el plugin.
 *
 * @package WeeklyContentConcatenator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Borrar post_meta de todos los posts
$post_ids = get_posts(
	array(
		'post_type'      => 'post',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_wcc_entries',
	)
);

if ( is_array( $post_ids ) && ! empty( $post_ids ) ) {
	// Requerir wcc-cron para limpiar eventos programados
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wcc-cron.php';

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
}

// Borrar la opción global de estilos configurada por el plugin
delete_option( 'wcc_styles' );

// Limpiar transients de avisos (patrón)
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcc_bump_notice_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wcc_bump_notice_%'" );
