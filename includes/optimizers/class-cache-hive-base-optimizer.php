<?php
/**
 * Abstract base and orchestrator for all optimizers.
 *
 * This class acts as a simple string-based orchestrator, passing the
 * raw HTML content to the appropriate optimizers in a specific sequence
 * without parsing the document itself.
 *
 * @package Cache_Hive
 * @since   1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the main entry point to trigger all enabled optimizations.
 */
class Cache_Hive_Base_Optimizer {

	/**
	 * Main optimization handler and orchestrator.
	 *
	 * The sequence of optimizations is critical:
	 * 1. Add header links (DNS prefetch, etc.).
	 * 2. Optimize CSS assets.
	 * 3. Optimize JS assets.
	 * 4. Optimize Media assets (future use).
	 * 5. Perform final HTML minification on the modified buffer.
	 *
	 * @param string $html            The full HTML content to optimize.
	 * @param string $base_cache_path The full path where the cache file will be stored.
	 * @return string The optimized HTML content.
	 */
	public static function process_all( $html, $base_cache_path ) {
		$settings = Cache_Hive_Settings::get_settings();

		// Check which major optimization types are enabled.
		$css_enabled   = Cache_Hive_CSS_Optimizer::is_enabled( $settings );
		$js_enabled    = Cache_Hive_JS_Optimizer::is_enabled( $settings );
		$html_enabled  = ! empty( $settings['html_minify'] ) || ! empty( $settings['auto_dns_prefetch'] ) || ! empty( $settings['google_fonts_async'] ) || ! empty( $settings['html_dns_prefetch'] ) || ! empty( $settings['html_dns_preconnect'] );
		$media_enabled = ! empty( $settings['media_lazyload_images'] ) || ! empty( $settings['media_lazyload_iframes'] );

		// If no optimizations are enabled, return the original buffer immediately.
		if ( ! $js_enabled && ! $css_enabled && ! $html_enabled && ! $media_enabled && empty( $settings['remove_emoji_scripts'] ) ) {
			return $html;
		}

		// The order of operations is crucial for stability.
		// 1. Handle Head-level injections first on the clean HTML.
		if ( $html_enabled || ! empty( $settings['remove_emoji_scripts'] ) ) {
			$html = Cache_Hive_HTML_Optimizer::run_pre_optimizations( $html, $settings );
		}

		// 2. Process CSS assets.
		if ( $css_enabled ) {
			$html = Cache_Hive_CSS_Optimizer::run_string_optimizations( $html, $base_cache_path, $settings );
		}

		// 3. Process JS assets.
		if ( $js_enabled ) {
			$html = Cache_Hive_JS_Optimizer::run_string_optimizations( $html, $base_cache_path, $settings );
		}

		// 4. Process Media assets (e.g., lazy loading, responsive placeholders).
		if ( $media_enabled ) {
			$html = Cache_Hive_Media_Optimizer::process( $html );
		}

		// 5. Process Image assets (e.g., <picture> tag rewriting for next-gen formats).
		$html = Cache_Hive_Image_Optimizer::rewrite_html_with_picture_tags( $html, $settings );

		// 6. Perform final HTML minification on the fully modified HTML string.
		if ( ! empty( $settings['html_minify'] ) ) {
			$html = Cache_Hive_HTML_Optimizer::minify_html( $html, $settings );
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
