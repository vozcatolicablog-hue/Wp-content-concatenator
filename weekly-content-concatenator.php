<?php
/**
 * Plugin Name: Concatenador de Contenido Semanal
 * Description: Organiza entregas semanales dentro de posts normales y devuelve el post al inicio cuando se publica una entrega nueva.
 * Version: 2.1.1
 * Author: Voz Catolica
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: weekly-content-concatenator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WCC_VERSION', '2.1.1' );
define( 'WCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

// Cargar clases modulares
require_once WCC_PLUGIN_DIR . 'includes/class-wcc-cron.php';
require_once WCC_PLUGIN_DIR . 'includes/class-wcc-admin.php';
require_once WCC_PLUGIN_DIR . 'includes/class-wcc-frontend.php';

// Inicializar componentes
if ( is_admin() ) {
	new WCC_Admin();
}
new WCC_Frontend();
new WCC_Cron();

/**
 * Cargar el Text Domain para traducciones locales.
 */
function wcc_load_textdomain() {
	load_plugin_textdomain( 'weekly-content-concatenator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wcc_load_textdomain' );

/**
 * Obtener un timestamp de entrega usando la zona horaria de WordPress.
 *
 * @param array $entry Valores de la entrega.
 * @return int
 */
function wcc_get_entry_timestamp( $entry ) {
	$date = isset( $entry['date'] ) ? $entry['date'] : current_time( 'Y-m-d' );
	$time = isset( $entry['time'] ) ? $entry['time'] : current_time( 'H:i' );

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
		$date = current_time( 'Y-m-d' );
	}

	if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
		$time = current_time( 'H:i' );
	}

	$datetime = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . $time, wp_timezone() );

	return $datetime ? $datetime->getTimestamp() : 0;
}

/**
 * Formatear fecha/hora de la entrega según configuración del sitio (panel de administración).
 *
 * @param array $entry Valores de la entrega.
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
 * Fecha de la entrega para el público, como metadato breve del sistema de diseño («7 oct 2026»).
 *
 * La hora no se muestra: en una serie es ruido; sigue disponible en el atributo datetime de <time>.
 *
 * @param array $entry Valores de la entrega.
 * @return string
 */
function wcc_format_entry_date_public( $entry ) {
	$timestamp = wcc_get_entry_timestamp( $entry );
	if ( ! $timestamp ) {
		return '';
	}

	/**
	 * Formato de fecha pública de cada entrega. Por defecto «j M Y» (día, mes abreviado, año).
	 *
	 * @param string $format Formato de fecha de PHP.
	 * @param array  $entry  Entrega.
	 */
	$format = apply_filters( 'wcc_entry_date_format', 'j M Y', $entry );
	$date   = wp_date( $format, $timestamp );

	// En español el mes abreviado va en minúscula («oct»); WordPress lo traduce con mayúscula.
	if ( 'j M Y' === $format && 0 === strpos( get_locale(), 'es' ) ) {
		$date = function_exists( 'mb_strtolower' ) ? mb_strtolower( $date, 'UTF-8' ) : strtolower( $date );
	}

	return $date;
}

/**
 * Separar el número con que empieza un título («03 - Título», «12. Título», «7 Título»).
 *
 * Devuelve el número tal como lo escribió el autor (conserva «03») y el título sin él; si el título
 * no empieza por un número, devuelve el número de posición que se le pasa.
 *
 * @param string $title    Título de la entrega.
 * @param int    $position Posición cronológica (1 = la más antigua).
 * @return array{0:string,1:string} Número y título limpio.
 */
function wcc_split_entry_number( $title, $position ) {
	$title = trim( (string) $title );
	if ( preg_match( '/^(\d{1,3})(?!\d)\s*(?:[-\x{2013}\x{2014}.:\x{00b7})]\s*)?(?=\S)(.+)$/u', $title, $m ) ) {
		return array( $m[1], trim( $m[2] ) );
	}

	return array( (string) $position, $title );
}

/**
 * Determina si la entrega debe ser visible en el frontend.
 *
 * @param array $entry Valores de la entrega.
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
 * Ordenar las entregas cronológicamente.
 *
 * Las entregas con la misma fecha y hora conservan el orden del panel (la más nueva arriba), en
 * lugar de quedar en un orden arbitrario.
 *
 * @param array  $entries Entregas.
 * @param string $order   asc o desc.
 * @return array
 */
function wcc_sort_entries( $entries, $order ) {
	if ( empty( $entries ) || ! is_array( $entries ) ) {
		return array();
	}

	$entries    = array_values( $entries );
	$timestamps = array_map( 'wcc_get_entry_timestamp', $entries );
	$positions  = array_keys( $entries );
	$direction  = 'asc' === $order ? SORT_ASC : SORT_DESC;
	$tiebreak   = 'asc' === $order ? SORT_DESC : SORT_ASC;
	array_multisort( $timestamps, $direction, SORT_NUMERIC, $positions, $tiebreak, SORT_NUMERIC, $entries );

	return $entries;
}

/**
 * Entregas visibles de un post, ya ordenadas, con su número y su título limpio.
 *
 * Cada entrega devuelta lleva además `number` (número de posición cronológica o el que encabeza el
 * título) y `clean_title` (título sin ese número).
 *
 * @param int    $post_id ID del post.
 * @param string $order   asc o desc.
 * @return array
 */
function wcc_get_visible_entries( $post_id, $order = 'desc' ) {
	$entries = get_post_meta( $post_id, '_wcc_entries', true );
	$entries = is_array( $entries ) ? $entries : array();
	$entries = array_values( array_filter( $entries, 'wcc_is_entry_visible' ) );

	if ( empty( $entries ) ) {
		return array();
	}

	// Numerar en orden cronológico (1 = la más antigua) antes de aplicar el orden visible.
	$entries = wcc_sort_entries( $entries, 'asc' );
	foreach ( $entries as $i => $entry ) {
		list( $number, $clean )           = wcc_split_entry_number( isset( $entry['title'] ) ? $entry['title'] : '', $i + 1 );
		$entries[ $i ]['number']      = $number;
		$entries[ $i ]['clean_title'] = $clean;
	}

	return 'asc' === $order ? $entries : array_reverse( $entries );
}

/**
 * Validar colores RGB/RGBA, HSL/HSLA y HEX robustamente.
 *
 * @param string $color Color ingresado.
 * @return bool
 */
function wcc_is_valid_color( $color ) {
	$color = trim( $color );
	if ( empty( $color ) ) {
		return true;
	}
	if ( preg_match( '/^#([A-Fa-f0-9]{3,4}|[A-Fa-f0-9]{6}|[A-Fa-f0-9]{8})$/', $color ) ) {
		return true;
	}
	if ( preg_match( '/^[a-zA-Z]+$/', $color ) && in_array( strtolower( $color ), array( 'transparent', 'inherit', 'initial', 'currentcolor' ), true ) ) {
		return true;
	}
	if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $color ) ) {
		return true;
	}
	if ( preg_match( '/^hsla?\(\s*\d+\s*,\s*\d+%\s*,\s*\d+%\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/i', $color ) ) {
		return true;
	}
	return false;
}

/**
 * ¿Se usan los colores del sistema de diseño de Voz Católica (en vez de los de la pantalla de ajustes)?
 *
 * Es el valor por defecto: solo se desactiva marcando la opción en Ajustes > Concatenador Semanal.
 *
 * @return bool
 */
function wcc_uses_design_system() {
	$styles = get_option( 'wcc_styles', array() );

	return ! ( is_array( $styles ) && isset( $styles['usar_sistema'] ) && '0' === $styles['usar_sistema'] );
}

/**
 * Añadir estilos CSS en línea según ajustes del plugin (solo con los colores personalizados).
 */
function wcc_add_custom_styles() {
	// Prevents printing the custom styles multiple times when there are multiple shortcodes on the same page.
	static $added = false;
	if ( $added ) {
		return;
	}
	$added = true;

	if ( wcc_uses_design_system() ) {
		return;
	}

	$styles = get_option( 'wcc_styles', array() );
	if ( empty( $styles ) || ! is_array( $styles ) ) {
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
		if ( ! empty( $styles[ $key ] ) && wcc_is_valid_color( $styles[ $key ] ) ) {
			$custom_css .= '  ' . $css_var . ': ' . wp_strip_all_tags( $styles[ $key ] ) . ';' . "\n";
			$has_vars    = true;
		}
	}

	$custom_css .= '}';

	if ( $has_vars ) {
		wp_add_inline_style( 'wcc-frontend', $custom_css );
	}
}

/**
 * Invalidar la caché de página de un post después de cambiar sus entregas.
 *
 * `update_post_meta` no dispara los avisos que escuchan los plugins de caché (WP Rocket, LiteSpeed,
 * etc.); `clean_post_cache` sí, así la entrega guardada por AJAX se ve enseguida en el sitio.
 *
 * @param int $post_id ID del post.
 */
function wcc_purge_post_cache( $post_id ) {
	clean_post_cache( $post_id );

	if ( function_exists( 'rocket_clean_post' ) ) {
		rocket_clean_post( $post_id );
	}

	/**
	 * Se dispara después de cambiar las entregas de un post, para integrar otras cachés.
	 *
	 * @param int $post_id ID del post.
	 */
	do_action( 'wcc_entries_updated', $post_id );
}
