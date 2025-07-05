<?php
/**
 * HTML optimizer for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.2.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all HTML minification and optimization tasks.
 */
final class Cache_Hive_HTML_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Processes and optimizes the HTML content based on enabled settings.
	 *
	 * @param string $html The HTML content to process.
	 * @return string The optimized HTML content.
	 */
	public static function process( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		$settings = Cache_Hive_Settings::get_settings();

		if ( ! empty( $settings['html_minify'] ) ) {
			$html = self::minify_html( $html, $settings );
		}

		$html = self::add_header_links( $html, $settings );

		return $html;
	}

	/**
	 * Implements a high-performance, single-pass HTML minifier.
	 *
	 * @param string $html     The HTML content.
	 * @param array  $settings The plugin settings.
	 * @return string The minified HTML content.
	 */
	private static function minify_html( $html, $settings ) {
		if ( strlen( $html ) > 700000 ) { // Safety brake for very large pages.
			return $html;
		}

		$ignore_tags       = (array) apply_filters( 'cache_hive_minify_html_ignore_tags', array( 'textarea', 'pre', 'code' ) );
		$ignore_tags_regex = implode( '|', array_unique( $ignore_tags ) );

		$patterns   = array();
		$patterns[] = '(?<whitespace>(?>[^\S ]\s*|\s{2,})(?=[^<]*+(?:<(?!/?(?:' . $ignore_tags_regex . ')\b)[^<]*+)*+(?:<(?>' . $ignore_tags_regex . ')\b|\z)))';

		if ( empty( $settings['html_keep_comments'] ) ) {
			$patterns[] = '(?<comment><!--[\s\S]*?-->)';
		}
		if ( ! empty( $settings['html_remove_noscript'] ) ) {
			$patterns[] = '(?<noscript><noscript\b[^>]*>.*?<\/noscript>)';
		}

		if ( empty( $patterns ) ) {
			return $html;
		}

		$master_regex = '#' . implode( '|', $patterns ) . '#isx';

		$minified_html = preg_replace_callback(
			$master_regex,
			static function ( $matches ) {
				if ( ! empty( $matches['comment'] ) || ! empty( $matches['noscript'] ) ) {
					return ''; // Remove comments and noscript tags.
				}
				if ( ! empty( $matches['whitespace'] ) ) {
					return ' '; // Collapse whitespace.
				}
				return '';
			},
			$html
		);

		return strlen( $minified_html ) > 1 ? $minified_html : $html;
	}

	/**
	 * Injects DNS prefetch, preconnect, and other links into the <head>.
	 *
	 * @param string $html     The final HTML string.
	 * @param array  $settings The plugin settings array.
	 * @return string The HTML with added header links.
	 */
	private static function add_header_links( $html, $settings ) {
		$links_to_add = array();
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

		// Manual DNS Prefetch & Preconnect.
		if ( ! empty( $settings['html_dns_prefetch'] ) && is_array( $settings['html_dns_prefetch'] ) ) {
			foreach ( $settings['html_dns_prefetch'] as $domain ) {
				$links_to_add[] = '<link rel="dns-prefetch" href="' . esc_attr( $domain ) . '">';
			}
		}
		if ( ! empty( $settings['html_dns_preconnect'] ) && is_array( $settings['html_dns_preconnect'] ) ) {
			foreach ( $settings['html_dns_preconnect'] as $domain ) {
				$links_to_add[] = '<link rel="preconnect" href="' . esc_attr( $domain ) . '" crossorigin>';
			}
		}

		// Automatic DNS Prefetch.
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

		// Async Google Fonts.
		if ( ! empty( $settings['google_fonts_async'] ) ) {
			preg_match_all( '/<link[^>]+href=["\'](https?:\/\/fonts\.googleapis\.com\/css[^\'"]+)["\'][^>]*>/i', $html, $font_matches );
			if ( ! empty( $font_matches[1] ) ) {
				foreach ( $font_matches[0] as $index => $full_match ) {
					$url               = $font_matches[1][ $index ];
					$url_with_swap     = add_query_arg( 'display', 'swap', $url );
					$async_link        = '<link rel="preload" href="' . esc_url( $url_with_swap ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
					$noscript_fallback = '<noscript>' . $full_match . '</noscript>';
					$html              = str_replace( $full_match, $async_link . $noscript_fallback, $html );
				}
			}
		}

		if ( ! empty( $links_to_add ) ) {
			$unique_links = implode( "\n", array_unique( $links_to_add ) );
			$pos          = strripos( $html, '</head>' );
			if ( false !== $pos ) {
				$html = substr_replace( $html, $unique_links . "\n", $pos, 0 );
			}
		}

		return $html;
	}
}
