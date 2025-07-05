<?php
/**
 * Abstract base and orchestrator for all optimizers.
 *
 * @package Cache_Hive
 * @since 1.2.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the main entry point to trigger all enabled optimizations.
 */
class Cache_Hive_Base_Optimizer {

	/**
	 * Main optimization handler, called from the output buffer.
	 *
	 * @param string $html The full HTML content to optimize.
	 * @return string The optimized HTML content.
	 */
	public static function optimize( $html ) {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( is_admin() || is_feed() || is_preview() || preg_match( '/sitemap(_index)?\.xml$/', $request_uri ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $html;
		}

		$settings = Cache_Hive_Settings::get_settings();

		/*
		Future: CSS and JS optimizers can be enabled here when their logic is complete.
		if ( ! empty( $settings['css_minify'] ) || ! empty( $settings['css_combine'] ) ) {
			$html = Cache_Hive_CSS_Optimizer::process( $html );
		}
		if ( ! empty( $settings['js_minify'] ) || 'off' !== $settings['js_defer_mode'] ) {
			$html = Cache_Hive_JS_Optimizer::process( $html );
		}
		*/

		$html_optimizations_enabled = array(
			$settings['html_minify'] ?? false,
			$settings['auto_dns_prefetch'] ?? false,
			$settings['google_fonts_async'] ?? false,
			$settings['html_remove_noscript'] ?? false,
		);

		if ( ! empty( array_filter( $html_optimizations_enabled ) ) || ! empty( $settings['html_dns_prefetch'] ) || ! empty( $settings['html_dns_preconnect'] ) ) {
			$html = Cache_Hive_HTML_Optimizer::process( $html );
		}

		return $html;
	}

	/**
	 * Helper method to initialize hooks for features that must run early.
	 */
	public static function init_hooks() {
		if ( Cache_Hive_Settings::get( 'remove_emoji_scripts', false ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
			add_filter( 'tiny_mce_plugins', array( __CLASS__, 'disable_emojis_tinymce' ) );
			add_filter( 'wp_resource_hints', array( __CLASS__, 'disable_emojis_dns_prefetch' ), 10, 2 );
		}
	}

	/**
	 * Disables the emoji plugin in TinyMCE.
	 *
	 * @param array $plugins An array of TinyMCE plugins.
	 * @return array The modified array of plugins.
	 */
	public static function disable_emojis_tinymce( $plugins ) {
		return is_array( $plugins ) ? array_diff( $plugins, array( 'wpemoji' ) ) : array();
	}

	/**
	 * Removes emoji-related DNS prefetch hints.
	 *
	 * @param array  $urls          URLs to print for resource hints.
	 * @param string $relation_type The relation type the URLs are printed for.
	 * @return array Difference between URLs and emoji-related URLs.
	 */
	public static function disable_emojis_dns_prefetch( $urls, $relation_type ) {
		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/13.0.0/svg/' );
			$urls          = array_diff( $urls, array( $emoji_svg_url ) );
		}
		return $urls;
	}
}
