<?php
/**
 * Abstract base and orchestrator for all optimizers.
 *
 * @since 1.2.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Hive_Base_Optimizer
 *
 * Provides the main entry point to trigger all enabled optimizations.
 */
class Cache_Hive_Base_Optimizer {

	/**
	 * Main optimization handler, called from the output buffer.
	 *
	 * This method takes the full HTML buffer, checks which optimizers are enabled,
	 * and applies them in a safe order. This is called right before a page is cached.
	 *
	 * @since 1.2.0
	 * @param string $html The full HTML content to optimize.
	 * @return string The optimized HTML content.
	 */
	public static function optimize( $html ) {
		// Get the request URI for sitemap check.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		// THE FIX: Replaced is_sitemap() with a reliable check on the request URI.
		// Do not optimize if the request is for an admin page, a feed, a preview, a sitemap, or a REST request.
		if ( is_admin() || is_feed() || is_preview() || ( preg_match( '/sitemap(_index)?\.xml$/', $request_uri ) ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $html;
		}

		$settings = Cache_Hive_Settings::get_settings();

		// FUTURE: The following can be uncommented as you build the other optimizers.

		/*
		// 1. Apply CSS Optimizations.
		if ( ! empty( $settings['css_minify'] ) || ! empty( $settings['css_combine'] ) ) {
			$html = Cache_Hive_CSS_Optimizer::process( $html );
		}

		// 2. Apply JS Optimizations.
		if ( ! empty( $settings['js_minify'] ) || ! empty( 'off' !== $settings['js_defer_mode'] ) ) {
			$html = Cache_Hive_JS_Optimizer::process( $html );
		}
		*/

		// Check if any HTML-related optimization is enabled before processing.
		$html_optimizations_enabled = array(
			$settings['html_minify'] ?? false,
			$settings['html_dns_prefetch'] ?? array(),
			$settings['html_dns_preconnect'] ?? array(),
			$settings['auto_dns_prefetch'] ?? false,
			$settings['google_fonts_async'] ?? false,
			! ( $settings['html_keep_comments'] ?? false ), // This is an optimization if it's false.
			$settings['html_remove_noscript'] ?? false,
		);

		if ( ! empty( array_filter( $html_optimizations_enabled ) ) ) {
			$html = Cache_Hive_HTML_Optimizer::process( $html );
		}

		// FUTURE: Apply Media Optimizations (e.g., lazy loading).

		return $html;
	}

	/**
	 * Helper method to initialize hooks for features that must run early.
	 * This should be called once from the main plugin file on a hook like 'init'.
	 *
	 * @since 1.2.0
	 */
	public static function init_hooks() {
		// The 'remove_emoji_scripts' option works by removing actions, which must
		// be done on an early hook, not during buffer processing.
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
	 * @since 1.2.0
	 * @param array $plugins An array of TinyMCE plugins.
	 * @return array The modified array of plugins.
	 */
	public static function disable_emojis_tinymce( $plugins ) {
		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		} else {
			return array();
		}
	}

	/**
	 * Removes emoji-related DNS prefetch hints.
	 *
	 * @since 1.2.0
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
