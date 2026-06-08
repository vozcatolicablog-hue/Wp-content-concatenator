<?php
/**
 * Plugin Name: Concatenador de Contenido Semanal
 * Description: Organiza entregas semanales dentro de posts normales y devuelve el post al inicio cuando se publica una entrega nueva.
 * Version: 1.5.0
 * Author: Voz Catolica
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: weekly-content-concatenator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCC_VERSION', '1.5.0' );
define( 'WCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueue frontend assets early for singular content that uses weekly content.
 * Ensures CSS/JS land in <head> instead of being injected mid-content.
 */
function wcc_enqueue_frontend_assets() {
	if ( ! is_singular() ) {
		return;
	}

	$post_id = get_queried_object_id();
	if ( ! $post_id ) {
		return;
	}

	$post_content = get_post_field( 'post_content', $post_id );
	if (
		! ( 'post' === get_post_type( $post_id ) && get_post_meta( $post_id, '_wcc_enabled', true ) ) &&
		! has_shortcode( $post_content, 'contenido_semanal' )
	) {
		return;
	}

	wp_enqueue_style( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.css', array(), WCC_VERSION );
	wp_enqueue_script( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.js', array(), WCC_VERSION, true );
	wcc_add_custom_styles();
}
add_action( 'wp_enqueue_scripts', 'wcc_enqueue_frontend_assets' );

/**
 * Register the weekly content meta box.
 */
function wcc_add_meta_box() {
	add_meta_box(
		'wcc_weekly_content',
		__( 'Entregas semanales', 'weekly-content-concatenator' ),
		'wcc_render_meta_box',
		'post',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_post', 'wcc_add_meta_box' );

/**
 * Render a single entry form.
 *
 * @param array  $entry Entry values.
 * @param string $index Entry index or __INDEX__ template placeholder.
 */
function wcc_render_entry_fields( $entry, $index ) {
	$defaults = array(
		'id'      => '',
		'title'   => '',
		'date'    => current_time( 'Y-m-d' ),
		'time'    => '00:00',
		'status'  => 'draft',
		'content' => '',
	);
	$entry    = wp_parse_args( $entry, $defaults );
	$status_class = 'is-draft';
	$status_label = __( 'Borrador', 'weekly-content-concatenator' );

	if ( 'publish' === $entry['status'] ) {
		$status_class = 'is-publish';
		$status_label = __( 'Publicada', 'weekly-content-concatenator' );
	} elseif ( 'scheduled' === $entry['status'] ) {
		$status_class = 'is-scheduled';
		$status_label = __( 'Programada', 'weekly-content-concatenator' );
	}
	?>
	<div class="wcc-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>" data-status="<?php echo esc_attr( $entry['status'] ); ?>">
		<input type="hidden" name="wcc_entries[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $entry['id'] ); ?>">
		<div class="wcc-entry__header">
			<strong class="wcc-entry__heading">
				<?php echo esc_html( $entry['title'] ? $entry['title'] : __( 'Nueva entrega', 'weekly-content-concatenator' ) ); ?>
			</strong>
			<div class="wcc-entry__header-meta">
				<span class="wcc-entry__header-date"><?php echo esc_html( wcc_format_entry_datetime( $entry ) ); ?></span>
				<span class="wcc-entry__status-badge <?php echo esc_attr( $status_class ); ?>">
					<?php echo esc_html( $status_label ); ?>
				</span>
			</div>
			<div>
				<button type="button" class="button button-small wcc-save-single-entry"><?php esc_html_e( 'Guardar', 'weekly-content-concatenator' ); ?></button>
				<button type="button" class="button-link wcc-toggle-entry"><?php esc_html_e( 'Abrir/cerrar', 'weekly-content-concatenator' ); ?></button>
				<button type="button" class="button-link-delete wcc-remove-entry"><?php esc_html_e( 'Eliminar', 'weekly-content-concatenator' ); ?></button>
			</div>
		</div>
		<div class="wcc-entry__body">
			<div class="wcc-entry__row">
				<label>
					<span><?php esc_html_e( 'Título', 'weekly-content-concatenator' ); ?></span>
					<input type="text" class="widefat wcc-title" name="wcc_entries[<?php echo esc_attr( $index ); ?>][title]" value="<?php echo esc_attr( $entry['title'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Fecha de la entrega', 'weekly-content-concatenator' ); ?></span>
					<input type="date" name="wcc_entries[<?php echo esc_attr( $index ); ?>][date]" value="<?php echo esc_attr( $entry['date'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Hora', 'weekly-content-concatenator' ); ?></span>
					<input type="time" name="wcc_entries[<?php echo esc_attr( $index ); ?>][time]" value="<?php echo esc_attr( $entry['time'] ); ?>">
				</label>
				<label>
					<span><?php esc_html_e( 'Estado', 'weekly-content-concatenator' ); ?></span>
					<select name="wcc_entries[<?php echo esc_attr( $index ); ?>][status]">
						<option value="draft" <?php selected( $entry['status'], 'draft' ); ?>><?php esc_html_e( 'Borrador', 'weekly-content-concatenator' ); ?></option>
						<option value="scheduled" <?php selected( $entry['status'], 'scheduled' ); ?>><?php esc_html_e( 'Programada', 'weekly-content-concatenator' ); ?></option>
						<option value="publish" <?php selected( $entry['status'], 'publish' ); ?>><?php esc_html_e( 'Publicada', 'weekly-content-concatenator' ); ?></option>
					</select>
				</label>
			</div>
			<label>
				<span><?php esc_html_e( 'Contenido', 'weekly-content-concatenator' ); ?></span>
				<textarea class="widefat wcc-content" rows="9" name="wcc_entries[<?php echo esc_attr( $index ); ?>][content]"><?php echo esc_textarea( $entry['content'] ); ?></textarea>
				<small><?php esc_html_e( 'Acepta texto, HTML permitido y shortcodes, incluidos embeds de audio o video.', 'weekly-content-concatenator' ); ?></small>
			</label>
		</div>
	</div>
	<?php
}

/**
 * Render the full weekly content meta box.
 *
 * @param WP_Post $post Current post.
 */
function wcc_render_meta_box( $post ) {
	$enabled    = (bool) get_post_meta( $post->ID, '_wcc_enabled', true );
	$hide_index = (bool) get_post_meta( $post->ID, '_wcc_hide_index', true );
	$order      = get_post_meta( $post->ID, '_wcc_order', true );
	$entries    = get_post_meta( $post->ID, '_wcc_entries', true );

	$order   = in_array( $order, array( 'asc', 'desc' ), true ) ? $order : 'desc';
	$entries = is_array( $entries ) ? $entries : array();

	wp_nonce_field( 'wcc_save_weekly_content', 'wcc_nonce' );
	?>
	<div class="wcc-settings">
		<label>
			<input type="checkbox" name="wcc_enabled" value="1" <?php checked( $enabled ); ?>>
			<strong><?php esc_html_e( 'Mostrar las entregas automáticamente al final de este post', 'weekly-content-concatenator' ); ?></strong>
		</label>
		<label>
			<input type="checkbox" name="wcc_hide_index" value="1" <?php checked( $hide_index ); ?>>
			<strong><?php esc_html_e( 'Ocultar el índice de entregas', 'weekly-content-concatenator' ); ?></strong>
		</label>
		<label>
			<?php esc_html_e( 'Orden visible:', 'weekly-content-concatenator' ); ?>
			<select name="wcc_order">
				<option value="desc" <?php selected( $order, 'desc' ); ?>><?php esc_html_e( 'Más recientes primero', 'weekly-content-concatenator' ); ?></option>
				<option value="asc" <?php selected( $order, 'asc' ); ?>><?php esc_html_e( 'Más antiguas primero', 'weekly-content-concatenator' ); ?></option>
			</select>
		</label>
	</div>
	<p class="description">
		<?php esc_html_e( 'Cuando guardes una entrega nueva con estado "Publicada" (con fecha de hoy o futura), la fecha del post se actualizará y volverá a aparecer como el post más reciente.', 'weekly-content-concatenator' ); ?>
	</p>
	<div id="wcc-entries">
		<?php
		foreach ( $entries as $index => $entry ) {
			wcc_render_entry_fields( $entry, (string) $index );
		}
		?>
	</div>
	<?php
	$wcc_total_entries     = count( $entries );
	$wcc_published_entries = count( array_filter( $entries, function ( $e ) { return isset( $e['status'] ) && 'publish' === $e['status']; } ) );
	$wcc_count_label       = $wcc_total_entries . ' ' . ( 1 === $wcc_total_entries ? __( 'entrega', 'weekly-content-concatenator' ) : __( 'entregas', 'weekly-content-concatenator' ) );
	$wcc_count_label      .= ', ' . $wcc_published_entries . ' ' . ( 1 === $wcc_published_entries ? __( 'publicada', 'weekly-content-concatenator' ) : __( 'publicadas', 'weekly-content-concatenator' ) );
	?>
	<p>
		<button type="button" class="button button-primary" id="wcc-add-entry"><?php esc_html_e( 'Añadir entrega', 'weekly-content-concatenator' ); ?></button>
		<span id="wcc-entry-count" class="wcc-entry-count">(<?php echo esc_html( $wcc_count_label ); ?>)</span>
	</p>
	<script type="text/html" id="tmpl-wcc-entry">
		<?php wcc_render_entry_fields( array(), '__INDEX__' ); ?>
	</script>
	<?php
}

/**
 * Load admin assets only on the post editor.
 *
 * @param string $hook Current admin page.
 */
function wcc_admin_assets( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_style( 'wcc-admin', WCC_PLUGIN_URL . 'assets/admin.css', array(), WCC_VERSION );
	wp_enqueue_script( 'wcc-admin', WCC_PLUGIN_URL . 'assets/admin.js', array( 'jquery' ), WCC_VERSION, true );
	wp_localize_script(
		'wcc-admin',
		'wccAdmin',
		array(
			'labelPublished'   => __( 'Publicada', 'weekly-content-concatenator' ),
			'labelDraft'       => __( 'Borrador', 'weekly-content-concatenator' ),
			'labelScheduled'   => __( 'Programada', 'weekly-content-concatenator' ),
			'confirmDelete'    => __( '¿Eliminar esta entrega? El cambio se aplicará al guardar el post.', 'weekly-content-concatenator' ),
			'labelEntries'     => __( 'entregas', 'weekly-content-concatenator' ),
			'labelPublishedOf' => __( 'publicadas', 'weekly-content-concatenator' ),
			'labelSave'        => __( 'Guardar', 'weekly-content-concatenator' ),
			'labelSaving'      => __( '...', 'weekly-content-concatenator' ),
			'labelSaved'       => __( '✓', 'weekly-content-concatenator' ),
			'noticeSaved'      => __( 'Entrega guardada correctamente.', 'weekly-content-concatenator' ),
			'noticeBumped'     => __( 'Entrega publicada: la fecha del post fue actualizada y el post volvió al inicio.', 'weekly-content-concatenator' ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'wcc_admin_assets' );

/**
 * Get an entry publication timestamp using the WordPress timezone.
 *
 * @param array $entry Entry values.
 * @return int
 */
function wcc_get_entry_timestamp( $entry ) {
	$date = isset( $entry['date'] ) ? $entry['date'] : current_time( 'Y-m-d' );
	$time = isset( $entry['time'] ) ? $entry['time'] : '00:00';

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		$date = current_time( 'Y-m-d' );
	}

	if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
		$time = '00:00';
	}

	$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );

	return $datetime ? $datetime->getTimestamp() : 0;
}

/**
 * Format an entry date/time in the WordPress timezone.
 *
 * @param array $entry Entry values.
 * @return string
 */
function wcc_format_entry_datetime( $entry ) {
	$timestamp = wcc_get_entry_timestamp( $entry );
	$format    = get_option( 'date_format' );

	if ( ! empty( $entry['time'] ) && '00:00' !== $entry['time'] ) {
		$format .= ' ' . get_option( 'time_format' );
	}

	return $timestamp ? wp_date( $format, $timestamp ) : '';
}

/**
 * Determine whether an entry should be visible on the frontend.
 *
 * @param array $entry Entry values.
 * @return bool
 */
function wcc_is_entry_visible( $entry ) {
	if ( ! isset( $entry['status'] ) ) {
		return false;
	}

	if ( 'publish' === $entry['status'] ) {
		return true;
	}

	if ( 'scheduled' !== $entry['status'] ) {
		return false;
	}

	return wcc_get_entry_timestamp( $entry ) <= time();
}

/**
 * Clear WP-Cron events for entries.
 *
 * @param int   $post_id Post ID.
 * @param array $entries Entries.
 */
function wcc_clear_scheduled_entries( $post_id, $entries ) {
	foreach ( $entries as $entry ) {
		if ( empty( $entry['id'] ) ) {
			continue;
		}

		wp_clear_scheduled_hook( 'wcc_publish_scheduled_entry', array( $post_id, $entry['id'] ) );
	}
}

/**
 * Schedule WP-Cron events for scheduled entries.
 *
 * @param int   $post_id Post ID.
 * @param array $entries Entries.
 */
function wcc_sync_scheduled_entries( $post_id, $entries ) {
	wcc_clear_scheduled_entries( $post_id, $entries );

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
 * Publish a scheduled entry when its WordPress-timezone date/time arrives.
 *
 * @param int    $post_id  Post ID.
 * @param string $entry_id Entry ID.
 */
function wcc_publish_scheduled_entry( $post_id, $entry_id ) {
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
add_action( 'wcc_publish_scheduled_entry', 'wcc_publish_scheduled_entry', 10, 2 );

/**
 * Sanitize an entry submitted from the editor.
 *
 * @param array $entry Raw entry.
 * @return array
 */
function wcc_sanitize_entry( $entry ) {
	$id = isset( $entry['id'] ) ? sanitize_key( $entry['id'] ) : '';

	// Validate that the date is a real calendar date, not just a format match.
	$date = current_time( 'Y-m-d' );
	if (
		isset( $entry['date'] ) &&
		preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $entry['date'], $m ) &&
		checkdate( (int) $m[2], (int) $m[3], (int) $m[1] )
	) {
		$date = $entry['date'];
	}

	$time = '00:00';
	if ( isset( $entry['time'] ) && preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $entry['time'] ) ) {
		$time = $entry['time'];
	}

	$content = isset( $entry['content'] ) ? $entry['content'] : '';
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		$content = wp_kses_post( $content );
	}

	$status = isset( $entry['status'] ) ? sanitize_key( $entry['status'] ) : 'draft';
	if ( ! in_array( $status, array( 'draft', 'scheduled', 'publish' ), true ) ) {
		$status = 'draft';
	}

	$sanitized = array(
		'id'      => $id ? $id : wp_generate_uuid4(),
		'title'   => isset( $entry['title'] ) ? sanitize_text_field( $entry['title'] ) : '',
		'date'    => $date,
		'time'    => $time,
		'status'  => $status,
		'content' => $content,
	);

	if ( 'scheduled' === $sanitized['status'] && wcc_get_entry_timestamp( $sanitized ) <= time() ) {
		$sanitized['status'] = 'publish';
	}

	return $sanitized;
}

/**
 * Save entries and bump the post date when a new entry is published.
 *
 * @param int $post_id Current post ID.
 */
function wcc_save_post( $post_id ) {
	// Previene re-entrada cuando wp_update_post() dispara save_post internamente.
	static $is_bumping = false;
	if ( $is_bumping ) {
		return;
	}

	if (
		! isset( $_POST['wcc_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcc_nonce'] ) ), 'wcc_save_weekly_content' ) ||
		( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
		// save_post_post solo dispara para post_type 'post'; las revisiones nunca
		// llegan a este hook. La comprobación se mantiene como salvaguarda extra.
		wp_is_post_revision( $post_id ) ||
		! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}

	$old_entries = get_post_meta( $post_id, '_wcc_entries', true );
	$old_entries = is_array( $old_entries ) ? $old_entries : array();
	$old_ids     = array();

	foreach ( $old_entries as $old_entry ) {
		if ( isset( $old_entry['id'], $old_entry['status'] ) && 'publish' === $old_entry['status'] ) {
			$old_ids[] = $old_entry['id'];
		}
	}

	$raw_entries = isset( $_POST['wcc_entries'] ) && is_array( $_POST['wcc_entries'] ) ? wp_unslash( $_POST['wcc_entries'] ) : array();
	$entries     = array();
	$has_new     = false;

	foreach ( $raw_entries as $raw_entry ) {
		if ( ! is_array( $raw_entry ) ) {
			continue;
		}

		$entry = wcc_sanitize_entry( $raw_entry );
		if ( '' === $entry['title'] && '' === trim( $entry['content'] ) ) {
			continue;
		}

		if ( 'publish' === $entry['status'] && ! in_array( $entry['id'], $old_ids, true ) ) {
			// Evitar actualizar la fecha del post si la fecha de la entrega es pasada (anterior a hoy)
			$today = current_time( 'Y-m-d' );
			if ( $entry['date'] >= $today ) {
				$has_new = true;
			}
		}
		$entries[] = $entry;
	}

	$order = isset( $_POST['wcc_order'] ) ? sanitize_key( wp_unslash( $_POST['wcc_order'] ) ) : 'desc';

	update_post_meta( $post_id, '_wcc_entries', $entries );
	update_post_meta( $post_id, '_wcc_enabled', isset( $_POST['wcc_enabled'] ) ? '1' : '0' );
	update_post_meta( $post_id, '_wcc_hide_index', isset( $_POST['wcc_hide_index'] ) ? '1' : '0' );
	update_post_meta( $post_id, '_wcc_order', 'asc' === $order ? 'asc' : 'desc' );
	wcc_clear_scheduled_entries( $post_id, $old_entries );
	wcc_sync_scheduled_entries( $post_id, $entries );

	if ( $has_new && 'publish' === get_post_status( $post_id ) ) {
		$is_bumping = true;
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', true ),
			)
		);
		$is_bumping = false;
		set_transient( 'wcc_bump_notice_' . get_current_user_id(), 1, 60 );
	}
}
add_action( 'save_post_post', 'wcc_save_post' );

/**
 * Muestra un aviso en el admin cuando la fecha del post fue actualizada
 * por la publicación de una nueva entrega.
 */
function wcc_admin_bump_notice() {
	$user_id = get_current_user_id();
	if ( get_transient( 'wcc_bump_notice_' . $user_id ) ) {
		delete_transient( 'wcc_bump_notice_' . $user_id );
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( '✓ Entrega publicada: la fecha del post fue actualizada y el post volvió al inicio.', 'weekly-content-concatenator' ); ?></p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'wcc_admin_bump_notice' );

/**
 * Sort entries by their assigned publication date.
 *
 * @param array  $entries Entries.
 * @param string $order   asc or desc.
 * @return array
 */
function wcc_sort_entries( $entries, $order ) {
	usort(
		$entries,
		function ( $a, $b ) use ( $order ) {
			$comparison = wcc_get_entry_timestamp( $a ) <=> wcc_get_entry_timestamp( $b );
			return 'asc' === $order ? $comparison : -$comparison;
		}
	);

	return $entries;
}

/**
 * Defer iframe loading by replacing src with data-src.
 *
 * @param string $html HTML content.
 * @return string
 */
function wcc_defer_iframes( $html ) {
	return preg_replace_callback(
		'/<iframe\b[^>]*>/i',
		function ( $matches ) {
			return preg_replace( '/\bsrc\s*=\s*/i', 'data-src=', $matches[0] );
		},
		$html
	);
}

/**
 * Build weekly entries markup for a post.
 *
 * @param int  $post_id             Post ID.
 * @param bool $override_hide_index Optional. Force hiding/showing index.
 * @return string
 */
function wcc_get_entries_html( $post_id, $override_hide_index = null ) {
	$entries = get_post_meta( $post_id, '_wcc_entries', true );
	$order   = get_post_meta( $post_id, '_wcc_order', true );
	$entries = is_array( $entries ) ? $entries : array();
	$entries = array_values(
		array_filter(
			$entries,
			function ( $entry ) {
				return wcc_is_entry_visible( $entry );
			}
		)
	);

	if ( empty( $entries ) ) {
		return '';
	}

	$entries = wcc_sort_entries( $entries, 'asc' === $order ? 'asc' : 'desc' );

	$hide_index = get_post_meta( $post_id, '_wcc_hide_index', true );
	$hide_index = ( null !== $override_hide_index ) ? (bool) $override_hide_index : (bool) $hide_index;

	// Fallback enqueue for shortcodes on non-singular pages or posts without _wcc_enabled.
	// The early wcc_enqueue_frontend_assets() hook handles the common singular-post case.
	wp_enqueue_style( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.css', array(), WCC_VERSION );
	wp_enqueue_script( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.js', array(), WCC_VERSION, true );
	wcc_add_custom_styles();

	ob_start();
	?>
	<section class="wcc-weekly-content" aria-label="<?php esc_attr_e( 'Entregas semanales', 'weekly-content-concatenator' ); ?>">
		<h2 class="wcc-weekly-content__title"><?php esc_html_e( 'Contenido de la serie', 'weekly-content-concatenator' ); ?></h2>
		<?php if ( ! $hide_index ) : ?>
			<nav class="wcc-weekly-content__index" aria-label="<?php esc_attr_e( 'Índice de entregas', 'weekly-content-concatenator' ); ?>">
				<details class="wcc-index-toggle" open>
					<summary><?php echo esc_html( sprintf( __( 'Índice de entregas (%d)', 'weekly-content-concatenator' ), count( $entries ) ) ); ?></summary>
					<ul>
						<?php foreach ( $entries as $number => $entry ) : ?>
							<li><a href="#entrega-<?php echo esc_attr( $entry['id'] ); ?>"><?php echo esc_html( $entry['title'] ? $entry['title'] : __( 'Entrega', 'weekly-content-concatenator' ) ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</details>
			</nav>
		<?php endif; ?>
		<div class="wcc-weekly-content__entries">
			<?php foreach ( $entries as $number => $entry ) : ?>
				<details class="wcc-weekly-entry" id="entrega-<?php echo esc_attr( $entry['id'] ); ?>"<?php echo 0 === $number ? ' open' : ''; ?>>
					<summary class="wcc-weekly-entry__summary">
						<span class="wcc-weekly-entry__summary-title"><?php echo esc_html( $entry['title'] ? $entry['title'] : __( 'Entrega', 'weekly-content-concatenator' ) ); ?></span>
						<time class="wcc-weekly-entry__summary-date" datetime="<?php echo esc_attr( $entry['date'] . 'T' . ( isset( $entry['time'] ) ? $entry['time'] : '00:00' ) ); ?>"><?php echo esc_html( wcc_format_entry_datetime( $entry ) ); ?></time>
					</summary>
					<div class="wcc-weekly-entry__content">
						<?php
						$entry_content = do_shortcode( wpautop( $entry['content'] ) );
						if ( 0 !== $number ) {
							$entry_content = wcc_defer_iframes( $entry_content );
						}
						echo $entry_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitized on save.
						?>
					</div>
				</details>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/**
 * Append entries automatically to enabled singular posts.
 *
 * @param string $content Post content.
 * @return string
 */
function wcc_append_entries_to_content( $content ) {
	if (
		is_singular( 'post' ) &&
		in_the_loop() &&
		is_main_query() &&
		get_post_meta( get_the_ID(), '_wcc_enabled', true ) &&
		! has_shortcode( $content, 'contenido_semanal' )
	) {
		$content .= wcc_get_entries_html( get_the_ID() );
	}

	return $content;
}
add_filter( 'the_content', 'wcc_append_entries_to_content', 20 );

/**
 * Allow manual placement via [contenido_semanal].
 *
 * Las entregas se renderizan independientemente del flag _wcc_enabled. Esto
 * es intencional: el autor puede desactivar el output automático y usar el
 * shortcode para controlar la posición de las entregas dentro del contenido.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function wcc_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'id'         => get_the_ID(),
			'hide_index' => '',
		),
		$atts,
		'contenido_semanal'
	);

	$post_id    = absint( $atts['id'] );
	$hide_index = $atts['hide_index'];

	if ( ! $post_id ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! $post || 'trash' === $post->post_status ) {
		return '';
	}

	if ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) {
		return '';
	}

	if ( '' !== $hide_index ) {
		$hide_index = filter_var( $hide_index, FILTER_VALIDATE_BOOLEAN );
	} else {
		$hide_index = null;
	}

	return wcc_get_entries_html( $post_id, $hide_index );
}
add_shortcode( 'contenido_semanal', 'wcc_shortcode' );

/**
 * AJAX Handler to save a single entry.
 */
function wcc_ajax_save_single_entry() {
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wcc_save_weekly_content' ) ) {
		wp_send_json_error( array( 'message' => __( 'Sesión expirada o no autorizada.', 'weekly-content-concatenator' ) ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
		wp_send_json_error( array( 'message' => __( 'No tienes permisos para editar este post.', 'weekly-content-concatenator' ) ) );
	}

	$entry_data = isset( $_POST['entry'] ) && is_array( $_POST['entry'] ) ? wp_unslash( $_POST['entry'] ) : null;
	if ( ! $entry_data ) {
		wp_send_json_error( array( 'message' => __( 'Datos de la entrega no recibidos.', 'weekly-content-concatenator' ) ) );
	}

	$sanitized_entry = wcc_sanitize_entry( $entry_data );

	if ( '' === $sanitized_entry['title'] && '' === trim( $sanitized_entry['content'] ) ) {
		wp_send_json_error( array( 'message' => __( 'El título y el contenido no pueden estar vacíos.', 'weekly-content-concatenator' ) ) );
	}

	$entries = get_post_meta( $post_id, '_wcc_entries', true );
	$entries = is_array( $entries ) ? $entries : array();

	$found      = false;
	$old_ids    = array();

	foreach ( $entries as $old_entry ) {
		if ( isset( $old_entry['id'], $old_entry['status'] ) && 'publish' === $old_entry['status'] ) {
			$old_ids[] = $old_entry['id'];
		}
	}

	$old_entries = $entries;

	foreach ( $entries as $key => $existing_entry ) {
		if ( isset( $existing_entry['id'] ) && $existing_entry['id'] === $sanitized_entry['id'] ) {
			$entries[ $key ] = $sanitized_entry;
			$found           = true;
			break;
		}
	}

	if ( ! $found ) {
		if ( empty( $sanitized_entry['id'] ) ) {
			$sanitized_entry['id'] = wp_generate_uuid4();
		}
		$entries[] = $sanitized_entry;
	}

	update_post_meta( $post_id, '_wcc_entries', $entries );
	wcc_clear_scheduled_entries( $post_id, $old_entries );
	wcc_sync_scheduled_entries( $post_id, $entries );

	if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
		$settings = wp_unslash( $_POST['settings'] );
		$order    = isset( $settings['order'] ) ? sanitize_key( $settings['order'] ) : 'desc';

		update_post_meta( $post_id, '_wcc_enabled', ! empty( $settings['enabled'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_wcc_hide_index', ! empty( $settings['hide_index'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_wcc_order', 'asc' === $order ? 'asc' : 'desc' );
	}

	$has_new = false;
	if ( 'publish' === $sanitized_entry['status'] && ! in_array( $sanitized_entry['id'], $old_ids, true ) ) {
		// Evitar actualizar la fecha del post si la fecha de la entrega es pasada (anterior a hoy)
		$today = current_time( 'Y-m-d' );
		if ( $sanitized_entry['date'] >= $today ) {
			$has_new = true;
		}
	}

	$bumped = false;
	if ( $has_new && 'publish' === get_post_status( $post_id ) ) {
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => current_time( 'mysql' ),
				'post_date_gmt' => current_time( 'mysql', true ),
			)
		);
		$bumped = true;
	}

	wp_send_json_success(
		array(
			'message'  => __( 'Entrega guardada correctamente.', 'weekly-content-concatenator' ),
			'entry_id' => $sanitized_entry['id'],
			'bumped'   => $bumped,
		)
	);
}
add_action( 'wp_ajax_wcc_save_single_entry', 'wcc_ajax_save_single_entry' );

/**
 * Register settings page for plugin styles.
 */
function wcc_register_settings_page() {
	add_options_page(
		__( 'Ajustes de Concatenador Semanal', 'weekly-content-concatenator' ),
		__( 'Concatenador Semanal', 'weekly-content-concatenator' ),
		'manage_options',
		'wcc-settings',
		'wcc_render_settings_page'
	);
}
add_action( 'admin_menu', 'wcc_register_settings_page' );

/**
 * Initialize settings, sections and fields.
 */
function wcc_settings_init() {
	register_setting( 'wcc_settings_group', 'wcc_styles', 'wcc_sanitize_styles' );

	add_settings_section(
		'wcc_accordion_section',
		__( 'Estilos del Acordeón (Entregas)', 'weekly-content-concatenator' ),
		'wcc_accordion_section_callback',
		'wcc-settings'
	);

	add_settings_field(
		'entry_header_bg',
		__( 'Color de fondo del encabezado', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_accordion_section',
		array( 'field' => 'entry_header_bg' )
	);

	add_settings_field(
		'entry_header_color',
		__( 'Color de texto del encabezado', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_accordion_section',
		array( 'field' => 'entry_header_color' )
	);

	add_settings_field(
		'entry_hover_bg',
		__( 'Color de fondo al pasar el cursor (Hover)', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_accordion_section',
		array( 'field' => 'entry_hover_bg' )
	);

	add_settings_field(
		'entry_content_bg',
		__( 'Color de fondo del contenido', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_accordion_section',
		array( 'field' => 'entry_content_bg' )
	);

	add_settings_field(
		'entry_border_color',
		__( 'Color de bordes', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_accordion_section',
		array( 'field' => 'entry_border_color' )
	);

	add_settings_section(
		'wcc_index_section',
		__( 'Estilos del Índice de Entregas', 'weekly-content-concatenator' ),
		'wcc_index_section_callback',
		'wcc-settings'
	);

	add_settings_field(
		'index_bg',
		__( 'Color de fondo del índice', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_index_section',
		array( 'field' => 'index_bg' )
	);

	add_settings_field(
		'index_border_color',
		__( 'Color de borde general', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_index_section',
		array( 'field' => 'index_border_color' )
	);

	add_settings_field(
		'index_left_border_color',
		__( 'Color de borde destacado (izquierdo)', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_index_section',
		array( 'field' => 'index_left_border_color' )
	);

	add_settings_field(
		'index_hover_bg',
		__( 'Color de fondo del índice al pasar el cursor (Hover)', 'weekly-content-concatenator' ),
		'wcc_color_picker_field_callback',
		'wcc-settings',
		'wcc_index_section',
		array( 'field' => 'index_hover_bg' )
	);
}
add_action( 'admin_init', 'wcc_settings_init' );

/**
 * Accordion section description callback.
 */
function wcc_accordion_section_callback() {
	echo '<p>' . esc_html__( 'Personaliza los colores de las cajas de las entregas.', 'weekly-content-concatenator' ) . '</p>';
}

/**
 * Index section description callback.
 */
function wcc_index_section_callback() {
	echo '<p>' . esc_html__( 'Personaliza los colores de la sección del índice / tabla de contenidos.', 'weekly-content-concatenator' ) . '</p>';
}

/**
 * Renders the color picker field.
 *
 * @param array $args Field arguments.
 */
function wcc_color_picker_field_callback( $args ) {
	$field   = $args['field'];
	$styles  = get_option( 'wcc_styles', array() );
	$value   = isset( $styles[ $field ] ) ? $styles[ $field ] : '';
	echo '<input type="text" name="wcc_styles[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '" class="wcc-color-field" data-default-color="" />';
}

/**
 * Sanitize style inputs.
 *
 * @param array $input Input values.
 * @return array
 */
function wcc_sanitize_styles( $input ) {
	$output = array();
	$fields = array(
		'entry_header_bg',
		'entry_header_color',
		'entry_hover_bg',
		'entry_content_bg',
		'entry_border_color',
		'index_bg',
		'index_border_color',
		'index_left_border_color',
		'index_hover_bg',
	);

	foreach ( $fields as $field ) {
		if ( isset( $input[ $field ] ) ) {
			$color = sanitize_text_field( $input[ $field ] );
			// Check if it is empty, a valid hex color, rgb/rgba, or hsl/hsla
			if ( empty( $color ) || preg_match( '/^#([A-Fa-f0-9]{3,4}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color ) || preg_match( '/^(rgb|rgba|hsl|hsla)\(/i', $color ) ) {
				$output[ $field ] = $color;
			} else {
				$output[ $field ] = '';
			}
		}
	}
	return $output;
}

/**
 * Enqueue scripts and styles for the settings page.
 *
 * @param string $hook Page hook.
 */
function wcc_admin_settings_assets( $hook ) {
	if ( 'settings_page_wcc-settings' !== $hook ) {
		return;
	}
	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script( 'wcc-settings-js', WCC_PLUGIN_URL . 'assets/settings.js', array( 'wp-color-picker', 'jquery' ), WCC_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'wcc_admin_settings_assets' );

/**
 * Render settings page.
 */
function wcc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'wcc_settings_group' );
			do_settings_sections( 'wcc-settings' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Add inline custom styles based on settings option.
 */
function wcc_add_custom_styles() {
	static $added = false;
	if ( $added ) {
		return;
	}

	$styles = get_option( 'wcc_styles', array() );
	if ( empty( $styles ) ) {
		return;
	}

	$custom_css = ':root {' . "\n";

	$mapping = array(
		'entry_header_bg'         => '--wcc-entry-header-bg',
		'entry_header_color'      => '--wcc-entry-header-color',
		'entry_hover_bg'          => '--wcc-entry-hover-bg',
		'entry_content_bg'        => '--wcc-entry-content-bg',
		'entry_border_color'      => '--wcc-entry-border-color',
		'index_bg'                => '--wcc-index-bg',
		'index_border_color'      => '--wcc-index-border-color',
		'index_left_border_color' => '--wcc-index-left-border-color',
		'index_hover_bg'          => '--wcc-index-hover-bg',
	);

	$has_vars = false;
	foreach ( $mapping as $key => $css_var ) {
		if ( ! empty( $styles[ $key ] ) ) {
			$custom_css .= '  ' . $css_var . ': ' . esc_attr( $styles[ $key ] ) . ';' . "\n";
			$has_vars = true;
		}
	}

	$custom_css .= '}';

	if ( $has_vars ) {
		wp_add_inline_style( 'wcc-frontend', $custom_css );
	}

	$added = true;
}
