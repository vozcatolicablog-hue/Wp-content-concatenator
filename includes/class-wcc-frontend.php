<?php
/**
 * Clase encargada de la parte pública (frontend, shortcodes, encolado de assets).
 *
 * @package WeeklyContentConcatenator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCC_Frontend {

	/**
	 * Días durante los que la última entrega lleva la insignia «Nueva».
	 */
	const NEW_BADGE_DAYS = 7;

	/**
	 * A partir de cuántas entregas el índice se muestra plegado y a dos columnas.
	 */
	const LONG_INDEX = 10;

	/**
	 * IDs de posts cuyas entregas ya se pintaron en esta petición (evita duplicados shortcode + automático).
	 *
	 * @var array
	 */
	private static $rendered = array();

	/**
	 * Constructor. Registra los hooks públicos.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_filter( 'the_content', array( $this, 'append_entries_to_content' ), 20 );
		add_shortcode( 'contenido_semanal', array( $this, 'shortcode' ) );
	}

	/**
	 * Encolar los assets en el frontend si es necesario.
	 */
	public function enqueue_frontend_assets() {
		wp_register_style( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.css', array(), WCC_VERSION );
		wp_register_script( 'wcc-frontend', WCC_PLUGIN_URL . 'assets/frontend.js', array(), WCC_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

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

		self::enqueue_assets();
	}

	/**
	 * Encolar hoja de estilos, script y estilos personalizados (idempotente).
	 */
	private static function enqueue_assets() {
		wp_enqueue_style( 'wcc-frontend' );
		wp_enqueue_script( 'wcc-frontend' );
		wcc_add_custom_styles();
	}

	/**
	 * Posponer la carga de iframes reemplazando el atributo src por data-src.
	 *
	 * Solo toca `src=`, no `data-src=` ni `srcdoc=`, para no romper iframes ya diferidos por otro plugin.
	 *
	 * @param string $html Contenido HTML.
	 * @return string
	 */
	public static function defer_iframes( $html ) {
		return preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			function ( $matches ) {
				return preg_replace( '/(?<![\w-])src\s*=/i', 'data-src=', $matches[0] );
			},
			$html
		);
	}

	/**
	 * Contenido de una entrega con los filtros normales de WordPress (oEmbed, wpautop, shortcodes, srcset).
	 *
	 * @param array $entry   Entrega.
	 * @param int   $post_id ID del post.
	 * @return string
	 */
	private function render_entry_content( $entry, $post_id ) {
		$raw = isset( $entry['content'] ) ? $entry['content'] : '';

		// Quitar el filtro propio para evitar la recursión (y los duplicados) y restaurarlo después.
		remove_filter( 'the_content', array( $this, 'append_entries_to_content' ), 20 );
		$html = apply_filters( 'the_content', $raw );
		add_filter( 'the_content', array( $this, 'append_entries_to_content' ), 20 );

		/**
		 * HTML final de una entrega.
		 *
		 * @param string $html    HTML ya filtrado.
		 * @param array  $entry   Entrega.
		 * @param int    $post_id ID del post.
		 */
		return apply_filters( 'wcc_entry_content', $html, $entry, $post_id );
	}

	/**
	 * Construir el marcado HTML de las entregas de un post.
	 *
	 * @param int       $post_id             ID del post.
	 * @param bool|null $override_hide_index Forzar ocultar/mostrar índice.
	 * @param array     $args                Opcionales: `title` (título de la sección) y `ante` (antetítulo).
	 * @return string
	 */
	public function get_entries_html( $post_id, $override_hide_index = null, $args = array() ) {
		$post_id    = (int) $post_id;
		$order      = get_post_meta( $post_id, '_wcc_order', true );
		$order      = 'asc' === $order ? 'asc' : 'desc';
		$hide_index = get_post_meta( $post_id, '_wcc_hide_index', true );
		$hide_index = ( null !== $override_hide_index ) ? (bool) $override_hide_index : filter_var( $hide_index, FILTER_VALIDATE_BOOLEAN );

		$entries = wcc_get_visible_entries( $post_id, $order );
		if ( empty( $entries ) ) {
			return '';
		}

		self::$rendered[ $post_id ] = true;
		self::enqueue_assets();

		$count = count( $entries );

		// Título de la sección: atributo del shortcode > meta del post > texto por defecto.
		$title = ! empty( $args['title'] ) ? $args['title'] : get_post_meta( $post_id, '_wcc_title', true );
		$title = $title ? $title : __( 'Contenido de la serie', 'weekly-content-concatenator' );
		$ante  = ! empty( $args['ante'] ) ? $args['ante'] : __( 'Serie', 'weekly-content-concatenator' );

		/**
		 * Título y antetítulo de la sección de entregas.
		 *
		 * @param string $title   Texto.
		 * @param int    $post_id ID del post.
		 */
		$title = apply_filters( 'wcc_section_title', $title, $post_id );
		$ante  = apply_filters( 'wcc_section_ante', $ante, $post_id );

		// Última entrega (la de mayor fecha) para el metadato y la insignia «Nueva».
		$latest    = 'desc' === $order ? $entries[0] : $entries[ $count - 1 ];
		$latest_ts = wcc_get_entry_timestamp( $latest );
		$is_recent = $latest_ts && ( time() - $latest_ts ) <= self::NEW_BADGE_DAYS * DAY_IN_SECONDS;

		/**
		 * ¿La última entrega lleva la insignia «Nueva»? Por defecto, si tiene menos de 7 días.
		 *
		 * @param bool  $is_recent Resultado por defecto.
		 * @param array $latest    Última entrega.
		 * @param int   $post_id   ID del post.
		 */
		$is_recent = apply_filters( 'wcc_show_new_badge', $is_recent, $latest, $post_id );

		$meta = sprintf(
			/* translators: %d: número de entregas */
			_n( '%d entrega', '%d entregas', $count, 'weekly-content-concatenator' ),
			$count
		);
		if ( $latest_ts ) {
			$meta .= ' · ' . sprintf(
				/* translators: %s: fecha de la última entrega */
				__( 'Última: %s', 'weekly-content-concatenator' ),
				wcc_format_entry_date_public( $latest )
			);
		}

		$uid        = 'wcc-' . $post_id;
		$long_index = $count > self::LONG_INDEX;

		ob_start();
		?>
		<section class="wcc-weekly-content wcc-ds" aria-labelledby="<?php echo esc_attr( $uid . '-titulo' ); ?>">
			<header class="wcc-weekly-content__head">
				<?php if ( '' !== $ante ) : ?>
					<p class="wcc-weekly-content__ante"><?php echo esc_html( $ante ); ?></p>
				<?php endif; ?>
				<h2 class="wcc-weekly-content__title" id="<?php echo esc_attr( $uid . '-titulo' ); ?>"><?php echo esc_html( $title ); ?></h2>
				<p class="wcc-weekly-content__meta"><?php echo esc_html( $meta ); ?></p>
			</header>
			<?php if ( ! $hide_index ) : ?>
				<nav class="wcc-weekly-content__index" aria-label="<?php esc_attr_e( 'Índice de entregas', 'weekly-content-concatenator' ); ?>">
					<details class="wcc-index-toggle<?php echo $long_index ? ' wcc-index-toggle--larga' : ''; ?>"<?php echo $long_index ? '' : ' open'; ?>>
						<summary class="wcc-index-toggle__summary">
							<span class="wcc-index-toggle__label"><?php esc_html_e( 'Índice de entregas', 'weekly-content-concatenator' ); ?></span>
							<span class="wcc-index-toggle__n"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
							<span class="wcc-chevron" aria-hidden="true"></span>
						</summary>
						<ol class="wcc-index-toggle__list">
							<?php foreach ( $entries as $entry ) : ?>
								<li>
									<a href="#entrega-<?php echo esc_attr( $entry['id'] ); ?>">
										<span class="wcc-index-toggle__num"><?php echo esc_html( $entry['number'] ); ?></span>
										<span class="wcc-index-toggle__txt"><?php echo esc_html( '' !== $entry['clean_title'] ? $entry['clean_title'] : __( 'Entrega', 'weekly-content-concatenator' ) ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ol>
					</details>
				</nav>
			<?php endif; ?>
			<div class="wcc-weekly-content__entries">
				<?php foreach ( $entries as $index => $entry ) : ?>
					<?php
					$is_open   = 0 === $index;
					$is_latest = $entry['id'] === $latest['id'];
					$time_attr = $entry['date'] . 'T' . ( isset( $entry['time'] ) ? $entry['time'] : '00:00' );
					?>
					<details class="wcc-weekly-entry" id="entrega-<?php echo esc_attr( $entry['id'] ); ?>"<?php echo $is_open ? ' open' : ''; ?>>
						<summary class="wcc-weekly-entry__summary">
							<span class="wcc-weekly-entry__num"><?php echo esc_html( $entry['number'] ); ?></span>
							<span class="wcc-weekly-entry__summary-title">
								<?php echo esc_html( '' !== $entry['clean_title'] ? $entry['clean_title'] : __( 'Entrega', 'weekly-content-concatenator' ) ); ?>
								<?php if ( $is_latest && $is_recent ) : ?>
									<span class="wcc-weekly-entry__badge"><?php esc_html_e( 'Nueva', 'weekly-content-concatenator' ); ?></span>
								<?php endif; ?>
							</span>
							<time class="wcc-weekly-entry__summary-date" datetime="<?php echo esc_attr( $time_attr ); ?>"><?php echo esc_html( wcc_format_entry_date_public( $entry ) ); ?></time>
							<span class="wcc-chevron" aria-hidden="true"></span>
						</summary>
						<div class="wcc-weekly-entry__content">
							<?php
							$entry_content = $this->render_entry_content( $entry, $post_id );
							if ( ! $is_open ) {
								$entry_content = self::defer_iframes( $entry_content );
							}
							echo $entry_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	 * Adjuntar entregas automáticamente al final de posts singulares habilitados.
	 *
	 * Si el post ya pintó sus entregas con el shortcode (que corre antes, en la prioridad 11), no se
	 * vuelven a añadir: por eso se consulta el registro y el contenido crudo, no el ya filtrado.
	 *
	 * @param string $content Contenido del post.
	 * @return string
	 */
	public function append_entries_to_content( $content ) {
		if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if (
			! $post_id ||
			isset( self::$rendered[ $post_id ] ) ||
			! get_post_meta( $post_id, '_wcc_enabled', true ) ||
			has_shortcode( (string) get_post_field( 'post_content', $post_id ), 'contenido_semanal' )
		) {
			return $content;
		}

		return $content . $this->get_entries_html( $post_id );
	}

	/**
	 * Renderizar mediante shortcode [contenido_semanal].
	 *
	 * @param array $atts Atributos del shortcode.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'         => get_the_ID(),
				'hide_index' => '',
				'title'      => '',
				'ante'       => '',
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

		if ( ( 'publish' !== $post->post_status || ! is_post_publicly_viewable( $post ) ) && ! current_user_can( 'read_post', $post_id ) ) {
			return '';
		}

		if ( '' !== $hide_index ) {
			$hide_index = filter_var( $hide_index, FILTER_VALIDATE_BOOLEAN );
		} else {
			$hide_index = null;
		}

		return $this->get_entries_html(
			$post_id,
			$hide_index,
			array(
				'title' => sanitize_text_field( $atts['title'] ),
				'ante'  => sanitize_text_field( $atts['ante'] ),
			)
		);
	}
}
