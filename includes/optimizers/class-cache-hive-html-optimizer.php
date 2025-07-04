<?php
/**
 * HTML optimizer for Cache Hive.
 *
 * @since 1.2.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Hive_HTML_Optimizer
 *
 * Handles all HTML minification and optimization tasks based on plugin settings.
 */
final class Cache_Hive_HTML_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Processes and optimizes the HTML content based on enabled settings.
	 *
	 * This is the main entry point for HTML optimization.
	 *
	 * @since 1.2.0
	 * @param string $html The HTML content to process.
	 * @return string The optimized HTML content.
	 */
	public static function process( $html ) {
		if ( empty( $html ) || ! is_string( $html ) ) {
			return $html;
		}

		$settings = Cache_Hive_Settings::get_settings();

		// Step 1: Perform the main minification if enabled.
		// This single, highly-efficient method now handles all conditional structural
		// minification (comments, noscript, whitespace) in one single pass.
		if ( ! empty( $settings['html_minify'] ) ) {
			$html = self::minify_html( $html );
		}

		// Step 2: Add header links like prefetch/preconnect. This is done last to avoid
		// being affected by other processes and to inject into the final <head>.
		$html = self::add_header_links( $html, $settings );

		return $html;
	}

	/**
	 * Implements a high-performance, single-pass HTML minifier.
	 *
	 * This function uses a single `preg_replace_callback` call with a dynamically built
	 * regular expression to perform multiple optimizations at once, preventing the
	 * performance cost of processing the full HTML string multiple times. It handles:
	 * 1. Conditional removal of all HTML comments.
	 * 2. Conditional removal of <noscript> tags.
	 * 3. Conditional minification of inline CSS and JS comments.
	 * 4. Collapsing all redundant whitespace into single spaces.
	 *
	 * All operations intelligently skip content within <pre>, <code>, <textarea>, and other specified tags.
	 *
	 * @since 1.2.0
	 * @param string $html The HTML content.
	 * @return string The minified HTML content.
	 */
	private static function minify_html( $html ) {
		// Bail if the HTML is too large to process, to prevent performance issues.
		if ( strlen( $html ) > 700000 ) {
			return $html;
		}

		$settings = Cache_Hive_Settings::get_settings();

		// Step 1: Conditionally minify comments inside <style> and <script> tags.
		// This is a preliminary pass that only runs if the respective settings are enabled.
		$minify_inline_css = ! empty( $settings['html_minify_inline_css'] );
		$minify_inline_js  = ! empty( $settings['html_minify_inline_js'] );

		if ( $minify_inline_css || $minify_inline_js ) {
			$html = preg_replace(
				// This regex safely targets CSS/JS block comments (/*...*/) and single-line JS comments (//...).
				'#/\*(?!!)[\s\S]*?\*/|(?:^[ \t]*)//.*$|((?<!\()[ \t>;,{}[\]])//[^;\n]*$#m',
				'$1',
				$html
			);
		}

		// Step 2: Build the list of tags whose content should not be minified (whitespace collapse).
		/**
		 * Filters the HTML tags to ignore during whitespace minification.
		 *
		 * @since 1.2.0
		 * @param string[] $ignore_tags The names of HTML tags to ignore. Default are 'textarea', 'pre', and 'code'.
		 */
		$ignore_tags = (array) apply_filters( 'cache_hive_minify_html_ignore_tags', array( 'textarea', 'pre', 'code' ) );

		// If inline CSS/JS minification is off, add them to the ignore list to prevent whitespace collapse.
		if ( ! $minify_inline_css ) {
			$ignore_tags[] = 'style';
		}
		if ( ! $minify_inline_js ) {
			$ignore_tags[] = 'script';
		}

		$ignore_tags_regex = implode( '|', array_unique( $ignore_tags ) );

		// Step 3: Dynamically build a single master regex to handle all structural minification in one pass.
		$patterns = array();

		// Pattern for HTML comments (only added if the setting is enabled).
		if ( empty( $settings['html_keep_comments'] ) ) {
			$patterns[] = '(?<comment><!--[\s\S]*?-->)';
		}

		// Pattern for <noscript> tags (only added if the setting is enabled).
		if ( ! empty( $settings['html_remove_noscript'] ) ) {
			$patterns[] = '(?<noscript><noscript\b[^>]*>.*?<\/noscript>)';
		}

		// Pattern for collapsible whitespace (always runs if minification is on).
		// This powerful regex looks ahead to ensure it's not inside an ignored tag.
		$patterns[] = '(?<whitespace>(?>[^\S ]\s*|\s{2,})(?=[^<]*+(?:<(?!/?(?:' . $ignore_tags_regex . ')\b)[^<]*+)*+(?:<(?>' . $ignore_tags_regex . ')\b|\z)))';

		// If there are no patterns to match (e.g., all conditionals are off), return original HTML.
		if ( empty( $patterns ) ) {
			return $html;
		}

		$master_regex = '#' . implode( '|', $patterns ) . '#isx';

		$minified_html = preg_replace_callback(
			$master_regex,
			function ( $matches ) {
				// Check which named capture group matched and return the correct replacement.
				if ( ! empty( $matches['comment'] ) || ! empty( $matches['noscript'] ) ) {
					return ''; // Remove comments and noscript tags completely.
				}
				if ( ! empty( $matches['whitespace'] ) ) {
					return ' '; // Collapse whitespace to a single space.
				}
				return ''; // Fallback, should not be reached.
			},
			$html
		);

		// If minification resulted in a very short or empty string, it likely failed. Return the original.
		if ( strlen( $minified_html ) <= 1 ) {
			return $html;
		}

		return $minified_html;
	}

	/**
	 * Injects DNS prefetch, preconnect, and other links into the <head> of the document.
	 *
	 * @since 1.2.0
	 * @param string $html     The final HTML string.
	 * @param array  $settings The plugin settings array.
	 * @return string The HTML with added header links.
	 */
	private static function add_header_links( $html, $settings ) {
		$links_to_add = array();
		$site_host    = wp_parse_url( home_url(), PHP_URL_HOST );

		// 1. Manual DNS Prefetch.
		if ( ! empty( $settings['html_dns_prefetch'] ) && is_array( $settings['html_dns_prefetch'] ) ) {
			foreach ( $settings['html_dns_prefetch'] as $domain ) {
				$links_to_add[] = '<link rel="dns-prefetch" href="' . esc_attr( $domain ) . '">';
			}
		}

		// 2. Manual DNS Preconnect.
		if ( ! empty( $settings['html_dns_preconnect'] ) && is_array( $settings['html_dns_preconnect'] ) ) {
			foreach ( $settings['html_dns_preconnect'] as $domain ) {
				$links_to_add[] = '<link rel="preconnect" href="' . esc_attr( $domain ) . '" crossorigin>';
			}
		}

		// 3. Automatic DNS Prefetch.
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

		// 4. Async Google Fonts.
		if ( ! empty( $settings['google_fonts_async'] ) ) {
			preg_match_all( '/<link[^>]+href=["\'](https?:\/\/fonts\.googleapis\.com\/css[^\'"]+)["\'][^>]*>/i', $html, $font_matches );
			if ( ! empty( $font_matches[1] ) ) {
				foreach ( $font_matches[0] as $index => $full_match ) {
					$url = $font_matches[1][ $index ];
					// Add display=swap if not present, which is a great perf boost.
					$url_with_swap = add_query_arg( 'display', 'swap', $url );

					// Create the async loading pattern.
					$async_link = '<link rel="preload" href="' . esc_url( $url_with_swap ) . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">';
					// Add a noscript fallback.
					$noscript_fallback = '<noscript>' . $full_match . '</noscript>';

					// Replace the original link with the async version and its fallback.
					$html = str_replace( $full_match, $async_link . $noscript_fallback, $html );
				}
			}
		}

		// If there are links to add, inject them into the head.
		if ( ! empty( $links_to_add ) ) {
			$unique_links = implode( "\n", array_unique( $links_to_add ) );
			// Find the closing </head> tag and insert the links before it.
			$pos = strripos( $html, '</head>' );
			if ( false !== $pos ) {
				$html = substr_replace( $html, $unique_links . "\n", $pos, 0 );
			}
		}

		return $html;
	}
}
