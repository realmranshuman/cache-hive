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
 * This class uses a safe, string-based tokenizing approach to avoid DOM reformatting issues.
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

		// Step 1: Remove comments first, while preserving special conditional comments.
		if ( empty( $settings['html_keep_comments'] ) ) {
			$html = self::remove_comments( $html );
		}

		// Step 2: Remove <noscript> tags if enabled.
		if ( ! empty( $settings['html_remove_noscript'] ) ) {
			$html = self::remove_noscript_tags( $html );
		}

		// Step 3: Perform the core minification of whitespace.
		if ( ! empty( $settings['html_minify'] ) ) {
			$html = self::minify_html_content( $html );
		}

		// Step 4: Add header links like prefetch/preconnect. This is done last to avoid being affected by other processes.
		$html = self::add_header_links( $html, $settings );

		return $html;
	}

	/**
	 * Removes HTML comments but preserves special IE conditional comments.
	 *
	 * @since 1.2.0
	 * @param string $html The HTML content.
	 * @return string The HTML with comments removed.
	 */
	private static function remove_comments( $html ) {
		// Protect IE conditional comments.
		$protected_comments = array();
		$html               = preg_replace_callback(
			'/<!--\[if.*?\[endif\]-->/is',
			function ( $matches ) use ( &$protected_comments ) {
				$placeholder          = '<!--CACHE-HIVE-IE-COMMENT--' . count( $protected_comments ) . '-->';
				$protected_comments[] = $matches[0];
				return $placeholder;
			},
			$html
		);

		// Remove all other standard HTML comments.
		$html = preg_replace( '/<!--[\s\S]*?-->/', '', $html );

		// Restore the protected IE conditional comments.
		if ( ! empty( $protected_comments ) ) {
			$html = preg_replace_callback(
				'/<!--CACHE-HIVE-IE-COMMENT--(\d+)-->/',
				function ( $matches ) use ( $protected_comments ) {
					return isset( $protected_comments[ $matches[1] ] ) ? $protected_comments[ $matches[1] ] : '';
				},
				$html
			);
		}

		return $html;
	}

	/**
	 * Removes <noscript> tags and their content.
	 *
	 * @since 1.2.0
	 * @param string $html The HTML content.
	 * @return string The HTML with <noscript> tags removed.
	 */
	private static function remove_noscript_tags( $html ) {
		return preg_replace( '/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html );
	}

	/**
	 * The core HTML minification logic.
	 *
	 * This method tokenizes the HTML into tags and content, then intelligently
	 * removes whitespace from the content parts, respecting sensitive tags like <pre> and <script>.
	 *
	 * @since 1.2.0
	 * @param string $html The HTML content.
	 * @return string The minified HTML content.
	 */
	private static function minify_html_content( $html ) {
		// Tokenize the HTML by splitting it at tags, but keeping the tags as delimiters.
		$tokens = preg_split( '/(<[^>]+>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $tokens ) ) {
			return $html; // Return original HTML if tokenization fails.
		}

		$output       = '';
		$skip_stack   = array(); // Use a stack to handle nested skip tags correctly.
		$tags_to_skip = array(
			'pre',
			'code',
			'script',
			'style',
			'textarea',
		);

		foreach ( $tokens as $token ) {
			// Check if the token is a tag.
			if ( isset( $token[0] ) && '<' === $token[0] ) {
				// It's a tag, get its name. e.g., 'div' from '<div class="...">' or '/div' from '</div>'.
				if ( preg_match( '/^<([\/!\?]?)(\w+)/', $token, $matches ) ) {
					$tag_name       = strtolower( $matches[2] );
					$is_closing_tag = '/' === $matches[1];

					// If this is a closing tag for the last item on our skip stack, pop it off.
					if ( $is_closing_tag && ! empty( $skip_stack ) && end( $skip_stack ) === $tag_name ) {
						array_pop( $skip_stack );
					}

					$output .= $token;

					// If this is an opening tag that we need to skip, push it to the stack.
					if ( ! $is_closing_tag && in_array( $tag_name, $tags_to_skip, true ) ) {
						array_push( $skip_stack, $tag_name );
					}
				} else {
					// Not a standard tag (e.g., a comment placeholder), just append it.
					$output .= $token;
				}
			} else { // It's content between tags.
				// If the skip stack is empty, we can minify the content.
				if ( empty( $skip_stack ) ) {
					// Collapse multiple whitespace characters (including newlines) into a single space.
					$minified_token = preg_replace( '/\s+/', ' ', $token );
					// Don't add a space if the content was just whitespace.
					if ( trim( $minified_token ) !== '' ) {
						$output .= $minified_token;
					}
				} else {
					// We are inside a skip tag, so append the content as-is.
					$output .= $token;
				}
			}
		}

		return $output;
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
