<?php
/**
 * HTML optimizer for Cache Hive.
 *
 * This class handles all HTML minification and string-based optimization tasks
 * using a robust, regex-based engine to avoid breaking page structure.
 *
 * @package Cache_Hive
 * @since   1.0.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all HTML minification and string-based optimization tasks.
 */
final class Cache_Hive_HTML_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Runs pre-optimization tasks that should happen before asset processing.
	 *
	 * @param string $html     The HTML content to process.
	 * @param array  $settings The plugin settings array.
	 * @return string The optimized HTML content.
	 */
	public static function run_pre_optimizations( $html, $settings ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		// The order of these operations is important.
		// 1. Add header links first.
		$html = self::add_header_links( $html, $settings );

		// 2. Remove WordPress Emoji scripts if enabled.
		if ( ! empty( $settings['remove_emoji_scripts'] ) ) {
			$html = self::strip_emoji_scripts( $html );
		}

		// 3. Asynchronously load Google Fonts if enabled.
		if ( ! empty( $settings['google_fonts_async'] ) ) {
			$html = self::async_google_fonts( $html );
		}

		return $html;
	}

	/**
	 * Implements a high-performance HTML minifier using regex.
	 *
	 * @param string $html     The HTML content.
	 * @param array  $settings The plugin settings.
	 * @return string The minified HTML content.
	 */
	public static function minify_html( $html, $settings ) {
		// Safety brake for extremely large pages to prevent regex performance issues.
		if ( strlen( $html ) > 700000 ) {
			return $html;
		}

		// 1. Remove HTML comments, respecting IE conditional comments.
		if ( empty( $settings['html_keep_comments'] ) ) {
			$html = preg_replace_callback(
				'/<!--([\s\S]*?)-->/',
				function ( $matches ) {
					// If the comment contains an IE conditional, keep it. Otherwise, remove it.
					if ( false !== strpos( $matches[1], '[if' ) ) {
						return $matches[0];
					}
					return '';
				},
				$html
			);
		}

		// 2. Collapse whitespace except inside pre|textarea|script|style|code.
		$ignore_tags  = (array) apply_filters( 'cache_hive_minify_html_ignore_tags', array( 'textarea', 'pre', 'code', 'script', 'style' ) );
		$ignore_regex = implode( '|', array_map( 'preg_quote', array_unique( $ignore_tags ) ) );

		$blocks = array();
		// Mask ignored content.
		$masked_html = preg_replace_callback(
			"/(<(?:{$ignore_regex})\\b[^>]*>)([\\s\\S]*?)(<\/(?:{$ignore_regex})>)/i",
			function ( $m ) use ( &$blocks ) {
				$key            = '___CACHEHIVE_BLOCK_' . count( $blocks ) . '___';
				$blocks[ $key ] = $m[0];
				return $key;
			},
			$html
		);

		// Collapse excess whitespace and line-breaks.
		$minified_html = preg_replace(
			array(
				'/\s{2,}/',  // collapse multiple spaces/tabs.
				'/>\s+</',   // remove spaces between tags.
			),
			array(
				' ',
				'><',
			),
			$masked_html
		);

		// Restore masked blocks.
		if ( ! empty( $blocks ) ) {
			$minified_html = strtr( $minified_html, $blocks );
		}

		return strlen( $minified_html ) > 1 ? $minified_html : $html;
	}


	/**
	 * Injects DNS prefetch, preconnect, and other links into the <head>.
	 *
	 * @param string $html     The full HTML string.
	 * @param array  $settings The plugin settings array.
	 * @return string The HTML with added header links.
	 */
	private static function add_header_links( $html, $settings ) {
		$links_to_add = array();
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

		// Manual DNS Prefetch & Preconnect.
		$prefetch_domains   = $settings['html_dns_prefetch'] ?? array();
		$preconnect_domains = $settings['html_dns_preconnect'] ?? array();

		if ( ! empty( $prefetch_domains ) && is_array( $prefetch_domains ) ) {
			foreach ( $prefetch_domains as $domain ) {
				$links_to_add[] = '<link rel="dns-prefetch" href="' . esc_attr( $domain ) . '">';
			}
		}
		if ( ! empty( $preconnect_domains ) && is_array( $preconnect_domains ) ) {
			foreach ( $preconnect_domains as $domain ) {
				$links_to_add[] = '<link rel="preconnect" href="' . esc_attr( $domain ) . '" crossorigin>';
			}
		}

		// Automatic DNS Prefetch from all external domains found in the document.
		if ( ! empty( $settings['auto_dns_prefetch'] ) ) {
			preg_match_all( '/<(?:link|script|img|iframe)[^>]+(?:href|src)=["\']((?:https?:)?\/\/[^\'"\/]+)/i', $html, $matches );
			if ( ! empty( $matches[1] ) ) {
				$external_hosts = array_unique( $matches[1] );
				foreach ( $external_hosts as $url ) {
					$host = preg_replace( '/^(https?:)?\/\//', '', $url );
					if ( $host !== $site_host ) {
						$links_to_add[] = '<link rel="dns-prefetch" href="//' . esc_attr( $host ) . '">';
					}
				}
			}
		}

		if ( ! empty( $links_to_add ) ) {
			$unique_links = "\n" . implode( "\n", array_unique( $links_to_add ) ) . "\n";
			// Inject links just before the closing </head> tag for maximum compatibility.
			if ( strripos( $html, '</head>' ) !== false ) {
				$html = str_ireplace( '</head>', $unique_links . '</head>', $html );
			}
		}

		return $html;
	}

	/**
	 * Asynchronously loads Google Fonts and adds a noscript fallback.
	 *
	 * @param string $html The HTML content.
	 * @return string The HTML with async Google Fonts.
	 */
	private static function async_google_fonts( $html ) {
		// Regex to find both v1 and v2 Google Fonts links.
		$font_regex = '/<link[^>]+href=["\']((?:https?:)?\/\/fonts\.googleapis\.com\/css[^\'"]+)["\'][^>]*>/i';

		preg_match_all( $font_regex, $html, $font_matches, PREG_SET_ORDER );

		if ( empty( $font_matches ) ) {
			return $html;
		}

		$replacements = array();
		foreach ( $font_matches as $match ) {
			$full_tag          = $match[0];
			$url               = $match[1];
			$url_with_swap     = add_query_arg( 'display', 'swap', html_entity_decode( $url ) );
			$async_link        = '<link rel="preload" href="' . esc_url( $url_with_swap ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
			$noscript_fallback = '<noscript>' . $full_tag . '</noscript>';

			// Store replacements to avoid issues with multiple fonts.
			$replacements[ $full_tag ] = $async_link . $noscript_fallback;
		}

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $html );
	}

	/**
	 * Removes scripts and styles related to WordPress Emojis.
	 *
	 * @param string $html The HTML content.
	 * @return string The HTML with emoji scripts removed.
	 */
	private static function strip_emoji_scripts( $html ) {
		// Remove the DNS prefetch for s.w.org.
		$html = preg_replace( '/<link rel=[\'"]dns-prefetch[\'"] href=[\'"]\/\/s\.w\.org[\'"] \/>\n?/', '', $html );
		// Remove the inline script that defines wp-emoji-settings.
		$html = preg_replace( '/<script[^>]*>[\s\S]*?window\._wpemojiSettings[\s\S]*?<\/script>\n?/is', '', $html );
		// Remove the inline emoji CSS.
		$html = preg_replace( '/<style[^>]*>[\s\S]*?img\.wp-smiley[\s\S]*?<\/style>\n?/is', '', $html );

		return $html;
	}
}
