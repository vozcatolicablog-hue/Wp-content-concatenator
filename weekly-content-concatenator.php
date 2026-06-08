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

// Cargar clases modulares
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wcc-cron.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wcc-admin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wcc-frontend.php';

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
 * Formatear fecha/hora de la entrega según configuración del sitio.
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
 * @param array  $entries Entregas.
 * @param string $order   asc o desc.
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
 * Añadir estilos CSS en línea según ajustes del plugin.
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
