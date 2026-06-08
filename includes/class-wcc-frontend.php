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

	/**
	 * Posponer la carga de iframes reemplazando el atributo src por data-src.
	 *
	 * @param string $html Contenido HTML.
	 * @return string
	 */
	public static function defer_iframes( $html ) {
		return preg_replace_callback(
			'/<iframe\b[^>]*>/i',
			function ( $matches ) {
				return preg_replace( '/\bsrc\s*=\s*/i', 'data-src=', $matches[0] );
			},
			$html
		);
	}

	/**
	 * Construir el marcado HTML de las entregas de un post.
	 *
	 * @param int  $post_id             ID del post.
	 * @param bool $override_hide_index Forzar ocultar/mostrar índice.
	 * @return string
	 */
	public function get_entries_html( $post_id, $override_hide_index = null ) {
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
						<summary class="wcc-weekly-entry__summary" tabindex="0">
							<span class="wcc-weekly-entry__summary-title"><?php echo esc_html( $entry['title'] ? $entry['title'] : __( 'Entrega', 'weekly-content-concatenator' ) ); ?></span>
							<time class="wcc-weekly-entry__summary-date" datetime="<?php echo esc_attr( $entry['date'] . 'T' . ( isset( $entry['time'] ) ? $entry['time'] : '00:00' ) ); ?>"><?php echo esc_html( wcc_format_entry_datetime( $entry ) ); ?></time>
						</summary>
						<div class="wcc-weekly-entry__content">
							<?php
							// Remover filtro para prevenir recursividad infinita
							remove_filter( 'the_content', array( $this, 'append_entries_to_content' ), 20 );

							// Aplicar todos los filtros normales para que funcionen oEmbed, srcset y otros plugins
							$entry_content = apply_filters( 'the_content', $entry['content'] );

							// Volver a añadir el filtro para futuras cargas de contenido
							add_filter( 'the_content', array( $this, 'append_entries_to_content' ), 20 );

							if ( 0 !== $number ) {
								$entry_content = self::defer_iframes( $entry_content );
							}
							echo wp_kses_post( $entry_content );
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
	 * @param string $content Contenido del post.
	 * @return string
	 */
	public function append_entries_to_content( $content ) {
		if (
			is_singular( 'post' ) &&
			in_the_loop() &&
			is_main_query() &&
			get_post_meta( get_the_ID(), '_wcc_enabled', true ) &&
			! has_shortcode( $content, 'contenido_semanal' )
		) {
			$content .= $this->get_entries_html( get_the_ID() );
		}

		return $content;
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

		return $this->get_entries_html( $post_id, $hide_index );
	}
}
