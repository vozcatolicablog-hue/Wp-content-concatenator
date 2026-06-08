<?php
/**
 * Clase encargada de la lógica de WP-Cron y publicación programada de entregas.
 *
 * @package WeeklyContentConcatenator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCC_Cron {

	/**
	 * Constructor. Registra los hooks necesarios.
	 */
	public function __construct() {
		add_action( 'wcc_publish_scheduled_entry', array( $this, 'publish_scheduled_entry' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'cleanup_post_cron_events' ) );
		add_action( 'before_delete_post', array( $this, 'cleanup_post_cron_events' ) );
	}

	/**
	 * Limpia eventos de WP-Cron programados para las entregas de un post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $entries Lista de entregas.
	 */
	public static function clear_scheduled_entries( $post_id, $entries ) {
		if ( ! is_array( $entries ) ) {
			return;
		}
		foreach ( $entries as $entry ) {
			if ( empty( $entry['id'] ) ) {
				continue;
			}
			wp_clear_scheduled_hook( 'wcc_publish_scheduled_entry', array( $post_id, $entry['id'] ) );
		}
	}

	/**
	 * Sincroniza eventos de WP-Cron programados para entregas programadas.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $entries Lista de entregas.
	 */
	public static function sync_scheduled_entries( $post_id, $entries ) {
		self::clear_scheduled_entries( $post_id, $entries );

		if ( ! is_array( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( empty( $entry['id'] ) ) {
				continue;
			}

			if ( isset( $entry['status'] ) && 'scheduled' === $entry['status'] ) {
				$timestamp = wcc_get_entry_timestamp( $entry );
				if ( $timestamp > time() ) {
					wp_schedule_single_event( $timestamp, 'wcc_publish_scheduled_entry', array( $post_id, $entry['id'] ) );
				}
			}
		}
	}

	/**
	 * Publica una entrega programada cuando llega su fecha y hora.
	 *
	 * @param int    $post_id  ID del post.
	 * @param string $entry_id ID de la entrega.
	 */
	public function publish_scheduled_entry( $post_id, $entry_id ) {
		$entries = get_post_meta( $post_id, '_wcc_entries', true );
		$entries = is_array( $entries ) ? $entries : array();
		$updated = false;

		foreach ( $entries as $key => $entry ) {
			if (
				isset( $entry['id'], $entry['status'] ) &&
				$entry_id === $entry['id'] &&
				'scheduled' === $entry['status'] &&
				wcc_get_entry_timestamp( $entry ) <= time()
			) {
				$entries[ $key ]['status'] = 'publish';
				$updated                  = true;
				break;
			}
		}

		if ( ! $updated ) {
			return;
		}

		update_post_meta( $post_id, '_wcc_entries', $entries );

		if ( 'publish' === get_post_status( $post_id ) ) {
			wp_update_post(
				array(
					'ID'            => $post_id,
					'post_date'     => current_time( 'mysql' ),
					'post_date_gmt' => current_time( 'mysql', true ),
				)
			);
		}
	}

	/**
	 * Callback para limpiar crons cuando un post se envía a papelera o se elimina definitivamente.
	 *
	 * @param int $post_id ID del post.
	 */
	public function cleanup_post_cron_events( $post_id ) {
		// Asegurar que solo limpie para posts normales
		if ( 'post' !== get_post_type( $post_id ) ) {
			return;
		}

		$entries = get_post_meta( $post_id, '_wcc_entries', true );
		if ( is_array( $entries ) ) {
			self::clear_scheduled_entries( $post_id, $entries );
		}
	}
}
