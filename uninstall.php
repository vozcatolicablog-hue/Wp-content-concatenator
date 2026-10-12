<?php
/**
 * Script de desinstalación. Limpia opciones, metadatos y eventos programados al eliminar el plugin.
 *
 * @package WeeklyContentConcatenator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Requerir wcc-cron para limpiar eventos programados
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wcc-cron.php';

// Borrar post_meta de todos los posts (en cualquier estado, también borradores y privados), en bloques
// para no agotar memoria en sitios grandes.
$batch_size = 200;
do {
	$post_ids = get_posts(
		array(
			'post_type'        => 'post',
			'post_status'      => array_keys( get_post_stati() ),
			'posts_per_page'   => $batch_size,
			'fields'           => 'ids',
			'meta_key'         => '_wcc_entries', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);

	foreach ( $post_ids as $post_id ) {
		$entries = get_post_meta( $post_id, '_wcc_entries', true );
		if ( is_array( $entries ) ) {
			WCC_Cron::clear_scheduled_entries( $post_id, $entries );
		}
		delete_post_meta( $post_id, '_wcc_entries' );
		delete_post_meta( $post_id, '_wcc_enabled' );
		delete_post_meta( $post_id, '_wcc_order' );
		delete_post_meta( $post_id, '_wcc_hide_index' );
		delete_post_meta( $post_id, '_wcc_title' );
	}
} while ( count( $post_ids ) === $batch_size );

// Borrar la opción global de estilos configurada por el plugin
delete_option( 'wcc_styles' );

// Limpiar transients de avisos (patrón)
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcc_bump_notice_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wcc_bump_notice_%'" );
