<?php
/**
 * Clase encargada de la administración del plugin (meta boxes, ajustes, AJAX).
 *
 * @package WeeklyContentConcatenator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCC_Admin {

	/**
	 * IDs de posts que están en proceso de actualización de fecha.
	 *
	 * @var array
	 */
	private static $bumping_ids = array();

	/**
	 * Constructor. Registra los hooks de administración.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes_post', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_post', array( $this, 'save_post' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_settings_assets' ) );
		add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_notices', array( $this, 'admin_bump_notice' ) );
		add_action( 'wp_ajax_wcc_save_single_entry', array( $this, 'ajax_save_single_entry' ) );
	}

	/**
	 * Registrar la meta box de entregas semanales.
	 */
	public function add_meta_box() {
		add_meta_box(
			'wcc_weekly_content',
			__( 'Entregas semanales', 'weekly-content-concatenator' ),
			array( $this, 'render_meta_box' ),
			'post',
			'normal',
			'high'
		);
	}

	/**
	 * Renderizar un campo de entrega individual.
	 *
	 * @param array  $entry Valores de la entrega.
	 * @param string $index Índice de la entrega.
	 */
	/**
	 * Obtener la clase CSS y etiqueta según el estado de la entrega.
	 *
	 * @param string $status Estado de la entrega.
	 * @return array Clase CSS y etiqueta.
	 */
	private function get_entry_status_meta( $status ) {
		switch ( $status ) {
			case 'publish':
				return array(
					'class' => 'is-publish',
					'label' => __( 'Publicada', 'weekly-content-concatenator' ),
				);
			case 'scheduled':
				return array(
					'class' => 'is-scheduled',
					'label' => __( 'Programada', 'weekly-content-concatenator' ),
				);
			case 'draft':
			default:
				return array(
					'class' => 'is-draft',
					'label' => __( 'Borrador', 'weekly-content-concatenator' ),
				);
		}
	}

	/**
	 * Renderizar un campo de entrega individual.
	 *
	 * @param array  $entry Valores de la entrega.
	 * @param string $index Índice de la entrega.
	 */
	public function render_entry_fields( $entry, $index ) {
		$defaults = array(
			'id'      => '',
			'title'   => '',
			'date'    => current_time( 'Y-m-d' ),
			'time'    => '00:00',
			'status'  => 'draft',
			'content' => '',
		);
		$entry    = wp_parse_args( $entry, $defaults );
		$status_meta  = $this->get_entry_status_meta( $entry['status'] );
		$status_class = $status_meta['class'];
		$status_label = $status_meta['label'];
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
					<button type="button" class="button-link wcc-toggle-entry" aria-expanded="true" aria-controls="wcc-body-<?php echo esc_attr( $index ); ?>"><?php esc_html_e( 'Abrir/cerrar', 'weekly-content-concatenator' ); ?></button>
					<button type="button" class="button-link-delete wcc-remove-entry"><?php esc_html_e( 'Eliminar', 'weekly-content-concatenator' ); ?></button>
				</div>
			</div>
			<div class="wcc-entry__body" id="wcc-body-<?php echo esc_attr( $index ); ?>">
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
	 * Renderizar la caja meta box completa en el editor.
	 *
	 * @param WP_Post $post Post actual.
	 */
	public function render_meta_box( $post ) {
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
				$this->render_entry_fields( $entry, (string) $index );
			}
			?>
		</div>
		<?php
		$wcc_total_entries     = count( $entries );
		$wcc_published_entries = count( array_filter( $entries, function ( $e ) { return isset( $e['status'] ) && 'publish' === $e['status']; } ) );
		$wcc_count_label       = sprintf(
			_n( '%d entrega', '%d entregas', $wcc_total_entries, 'weekly-content-concatenator' ),
			$wcc_total_entries
		);
		$wcc_count_label      .= ', ' . sprintf(
			_n( '%d publicada', '%d publicadas', $wcc_published_entries, 'weekly-content-concatenator' ),
			$wcc_published_entries
		);
		?>
		<p>
			<button type="button" class="button button-primary" id="wcc-add-entry"><?php esc_html_e( 'Añadir entrega', 'weekly-content-concatenator' ); ?></button>
			<span id="wcc-entry-count" class="wcc-entry-count">(<?php echo esc_html( $wcc_count_label ); ?>)</span>
		</p>
		<script type="text/html" id="tmpl-wcc-entry">
			<?php $this->render_entry_fields( array(), '__INDEX__' ); ?>
		</script>
		<?php
	}

	/**
	 * Carga de assets para el editor de posts (incluye jquery-ui-sortable).
	 *
	 * @param string $hook Página de administración actual.
	 */
	public function admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'wcc-admin', WCC_PLUGIN_URL . 'assets/admin.css', array(), WCC_VERSION );
		wp_enqueue_script( 'wcc-admin', WCC_PLUGIN_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), WCC_VERSION, true );
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
				'labelSaving'      => '...',
				'labelSaved'       => '✓',
				'noticeSaved'      => __( 'Entrega guardada correctamente.', 'weekly-content-concatenator' ),
				'noticeBumped'     => __( 'Entrega publicada: la fecha del post fue actualizada y el post volvió al inicio.', 'weekly-content-concatenator' ),
				'errorEmpty'       => __( 'El título y el contenido no pueden estar vacíos.', 'weekly-content-concatenator' ),
				'errorPostId'      => __( 'No se pudo obtener el ID del post.', 'weekly-content-concatenator' ),
				'errorServer'      => __( 'Ocurrió un error en la comunicación con el servidor.', 'weekly-content-concatenator' ),
			)
		);
	}

	/**
	 * Sanitizar una entrega del editor.
	 *
	 * @param array $entry Valores sin sanitizar.
	 * @return array
	 */
	public function sanitize_entry( $entry ) {
		$id = isset( $entry['id'] ) ? sanitize_text_field( wp_unslash( $entry['id'] ) ) : '';
		if ( $id && ! preg_match( '/^[0-9a-f\-]{36}$/i', $id ) ) {
			$id = '';
		}

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
	 * Guardar entregas al guardar el post principal de forma global.
	 *
	 * @param int $post_id ID del post.
	 */
	/**
	 * Actualiza la fecha del post si hay una entrega nueva publicada.
	 *
	 * @param int $post_id   ID del post.
	 * @param int $timestamp Timestamp de la entrega.
	 */
	private static function maybe_bump_post_date( $post_id, $timestamp ) {
		self::$bumping_ids[] = $post_id;
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => wp_date( 'Y-m-d H:i:s', $timestamp ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $timestamp ),
			)
		);
		self::$bumping_ids = array_diff( self::$bumping_ids, array( $post_id ) );
	}

	public function save_post( $post_id ) {
		if ( in_array( $post_id, self::$bumping_ids, true ) ) {
			return;
		}

		if (
			! isset( $_POST['wcc_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcc_nonce'] ) ), 'wcc_save_weekly_content' ) ||
			( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
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
		$latest_new_entry_timestamp = 0;

		foreach ( $raw_entries as $raw_entry ) {
			if ( ! is_array( $raw_entry ) ) {
				continue;
			}

			$entry = $this->sanitize_entry( $raw_entry );
			if ( '' === $entry['title'] && '' === trim( $entry['content'] ) ) {
				continue;
			}

			if ( 'publish' === $entry['status'] && ! in_array( $entry['id'], $old_ids, true ) ) {
				$today = current_time( 'Y-m-d' );
				if ( $entry['date'] >= $today ) {
					$has_new = true;
					$entry_ts = wcc_get_entry_timestamp( $entry );
					if ( $entry_ts > $latest_new_entry_timestamp ) {
						$latest_new_entry_timestamp = $entry_ts;
					}
				}
			}
			$entries[] = $entry;
		}

		// Limitar a un máximo de 500 entregas para prevenir desbordamientos de base de datos
		if ( count( $entries ) > 500 ) {
			$entries = array_slice( $entries, 0, 500 );
		}

		$order = isset( $_POST['wcc_order'] ) ? sanitize_key( wp_unslash( $_POST['wcc_order'] ) ) : 'desc';

		update_post_meta( $post_id, '_wcc_entries', $entries );
		update_post_meta( $post_id, '_wcc_enabled', isset( $_POST['wcc_enabled'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_wcc_hide_index', isset( $_POST['wcc_hide_index'] ) ? '1' : '0' );
		update_post_meta( $post_id, '_wcc_order', 'asc' === $order ? 'asc' : 'desc' );

		WCC_Cron::sync_scheduled_entries( $post_id, $entries, $old_entries );

		if ( $has_new && 'publish' === get_post_status( $post_id ) ) {
			$bump_timestamp = $latest_new_entry_timestamp ? $latest_new_entry_timestamp : time();
			self::maybe_bump_post_date( $post_id, $bump_timestamp );
			set_transient( 'wcc_bump_notice_' . get_current_user_id(), 1, 60 );
		}
	}

	/**
	 * Callback para el guardado parcial e individual de una entrega mediante AJAX.
	 */
	public function ajax_save_single_entry() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'No tienes permisos para editar este post.', 'weekly-content-concatenator' ) ) );
		}

		if ( in_array( $post_id, self::$bumping_ids, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Operación de actualización en curso.', 'weekly-content-concatenator' ) ) );
		}

		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wcc_save_weekly_content' ) ) {
			wp_send_json_error( array( 'message' => __( 'Sesión expirada o no autorizada.', 'weekly-content-concatenator' ) ) );
		}

		$entry_data = isset( $_POST['entry'] ) && is_array( $_POST['entry'] ) ? wp_unslash( $_POST['entry'] ) : null;
		if ( ! $entry_data ) {
			wp_send_json_error( array( 'message' => __( 'Datos de la entrega no recibidos.', 'weekly-content-concatenator' ) ) );
		}

		$sanitized_entry = $this->sanitize_entry( $entry_data );

		if ( '' === $sanitized_entry['title'] && '' === trim( $sanitized_entry['content'] ) ) {
			wp_send_json_error( array( 'message' => __( 'El título y el contenido no pueden estar vacíos.', 'weekly-content-concatenator' ) ) );
		}

		$entries = get_post_meta( $post_id, '_wcc_entries', true );
		$entries = is_array( $entries ) ? $entries : array();

		// Limitar a un máximo de 500 entregas para prevenir desbordamientos de base de datos
		$is_new_entry = ! in_array( $sanitized_entry['id'], array_column( $entries, 'id' ), true );
		if ( count( $entries ) >= 500 && $is_new_entry ) {
			wp_send_json_error( array( 'message' => __( 'Se ha alcanzado el límite máximo de 500 entregas para este post.', 'weekly-content-concatenator' ) ) );
		}

		$old_ids = array();
		foreach ( $entries as $old_entry ) {
			if ( isset( $old_entry['id'], $old_entry['status'] ) && 'publish' === $old_entry['status'] ) {
				$old_ids[] = $old_entry['id'];
			}
		}

		$old_entries = $entries;

		// Procesar eliminaciones pendientes enviadas por AJAX con validación de UUID
		$active_ids = isset( $_POST['active_ids'] ) && is_array( $_POST['active_ids'] )
			? array_filter(
				array_map( 'sanitize_text_field', wp_unslash( $_POST['active_ids'] ) ),
				fn( $id ) => preg_match( '/^[0-9a-f\-]{36}$/i', $id )
			)
			: array();

		if ( ! empty( $active_ids ) ) {
			$filtered_entries = array();
			foreach ( $entries as $existing_entry ) {
				if ( isset( $existing_entry['id'] ) && ( in_array( $existing_entry['id'], $active_ids, true ) || $existing_entry['id'] === $sanitized_entry['id'] ) ) {
					$filtered_entries[] = $existing_entry;
				}
			}
			$entries = $filtered_entries;
		}

		$found = false;
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

		WCC_Cron::sync_scheduled_entries( $post_id, $entries, $old_entries );

		if ( isset( $_POST['settings'] ) && is_array( $_POST['settings'] ) ) {
			$settings = wp_unslash( $_POST['settings'] );
			$order    = isset( $settings['order'] ) ? sanitize_key( $settings['order'] ) : 'desc';

			update_post_meta( $post_id, '_wcc_enabled', ! empty( $settings['enabled'] ) ? '1' : '0' );
			update_post_meta( $post_id, '_wcc_hide_index', ! empty( $settings['hide_index'] ) ? '1' : '0' );
			update_post_meta( $post_id, '_wcc_order', 'asc' === $order ? 'asc' : 'desc' );
		}

		$has_new = false;
		if ( 'publish' === $sanitized_entry['status'] && ! in_array( $sanitized_entry['id'], $old_ids, true ) ) {
			$today = current_time( 'Y-m-d' );
			if ( $sanitized_entry['date'] >= $today ) {
				$has_new = true;
			}
		}

		$bumped = false;
		if ( $has_new && 'publish' === get_post_status( $post_id ) ) {
			self::maybe_bump_post_date( $post_id, wcc_get_entry_timestamp( $sanitized_entry ) );
			$bumped = true;
		}

		wp_send_json_success(
			array(
				'message'        => __( 'Entrega guardada correctamente.', 'weekly-content-concatenator' ),
				'entry_id'       => $sanitized_entry['id'],
				'status'         => $sanitized_entry['status'],
				'bumped'         => $bumped,
				'formatted_date' => wcc_format_entry_datetime( $sanitized_entry ),
			)
		);
	}

	/**
	 * Registrar la página de ajustes generales del plugin.
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Ajustes de Concatenador Semanal', 'weekly-content-concatenator' ),
			__( 'Concatenador Semanal', 'weekly-content-concatenator' ),
			'manage_options',
			'wcc-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Inicializar las secciones y campos de ajustes.
	 */
	public function settings_init() {
		register_setting( 'wcc_settings_group', 'wcc_styles', array( $this, 'sanitize_styles' ) );

		add_settings_section(
			'wcc_accordion_section',
			__( 'Estilos del Acordeón (Entregas)', 'weekly-content-concatenator' ),
			array( $this, 'accordion_section_callback' ),
			'wcc-settings'
		);

		$color_fields = array(
			'entry_header_bg'         => __( 'Color de fondo del encabezado', 'weekly-content-concatenator' ),
			'entry_header_color'      => __( 'Color de texto del encabezado', 'weekly-content-concatenator' ),
			'entry_hover_bg'          => __( 'Color de fondo al pasar el cursor (Hover)', 'weekly-content-concatenator' ),
			'entry_content_bg'        => __( 'Color de fondo del contenido', 'weekly-content-concatenator' ),
			'entry_border_color'      => __( 'Color de bordes', 'weekly-content-concatenator' ),
		);

		foreach ( $color_fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'color_picker_field_callback' ),
				'wcc-settings',
				'wcc_accordion_section',
				array( 'field' => $field )
			);
		}

		add_settings_section(
			'wcc_index_section',
			__( 'Estilos del Índice de Entregas', 'weekly-content-concatenator' ),
			array( $this, 'index_section_callback' ),
			'wcc-settings'
		);

		$index_fields = array(
			'index_bg'                => __( 'Color de fondo del índice', 'weekly-content-concatenator' ),
			'index_border_color'      => __( 'Color de borde general', 'weekly-content-concatenator' ),
			'index_left_border_color' => __( 'Color de borde destacado (izquierdo)', 'weekly-content-concatenator' ),
			'index_hover_bg'          => __( 'Color de fondo del índice al pasar el cursor (Hover)', 'weekly-content-concatenator' ),
		);

		foreach ( $index_fields as $field => $label ) {
			add_settings_field(
				$field,
				$label,
				array( $this, 'color_picker_field_callback' ),
				'wcc-settings',
				'wcc_index_section',
				array( 'field' => $field )
			);
		}
	}

	/**
	 * Callback para descripción de sección acordeón.
	 */
	public function accordion_section_callback() {
		echo '<p>' . esc_html__( 'Personaliza los colores de las cajas de las entregas.', 'weekly-content-concatenator' ) . '</p>';
	}

	/**
	 * Callback para descripción de sección índice.
	 */
	public function index_section_callback() {
		echo '<p>' . esc_html__( 'Personaliza los colores de la sección del índice / tabla de contenidos.', 'weekly-content-concatenator' ) . '</p>';
	}

	/**
	 * Renderizar el input con color picker.
	 *
	 * @param array $args Argumentos del campo.
	 */
	public function color_picker_field_callback( $args ) {
		$field   = $args['field'];
		$styles  = get_option( 'wcc_styles', array() );
		$value   = isset( $styles[ $field ] ) ? $styles[ $field ] : '';
		echo '<input type="text" name="wcc_styles[' . esc_attr( $field ) . ']" value="' . esc_attr( $value ) . '" class="wcc-color-field" data-default-color="" />';
	}

	/**
	 * Validar colores RGB/RGBA, HSL/HSLA y HEX robustamente.
	 *
	 * @param string $color Color ingresado.
	 * @return bool
	 */
	/**
	 * Sanitizar ajustes de estilo guardados.
	 *
	 * @param array $input Estilos ingresados.
	 * @return array
	 */
	public function sanitize_styles( $input ) {
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
				if ( wcc_is_valid_color( $color ) ) {
					$output[ $field ] = $color;
				} else {
					$output[ $field ] = '';
				}
			}
		}
		return $output;
	}

	/**
	 * Encolar estilos y scripts en la página de configuración del admin.
	 *
	 * @param string $hook Gancho de página de admin.
	 */
	public function admin_settings_assets( $hook ) {
		if ( 'settings_page_wcc-settings' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wcc-settings-js', WCC_PLUGIN_URL . 'assets/settings.js', array( 'wp-color-picker', 'jquery' ), WCC_VERSION, true );
	}

	/**
	 * Renderizar el contenido HTML de la página de configuración.
	 */
	public function render_settings_page() {
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
	 * Mostrar un aviso exitoso cuando se publica una entrega que actualiza el post.
	 */
	public function admin_bump_notice() {
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
}
