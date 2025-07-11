<?php
/**
 * CSS optimizer for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Vendor\MatthiasMullie\Minify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles CSS minification and combination for Cache Hive.
 */
final class Cache_Hive_CSS_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Holds the plugin settings.
	 *
	 * @var array
	 */
	private static $settings;

	/**
	 * Check if CSS optimization is enabled.
	 *
	 * @return bool
	 */
	protected static function is_enabled() {
		return ! empty( self::$settings['css_minify'] ) || ! empty( self::$settings['css_combine'] );
	}

	/**
	 * Process and optimize CSS in the given HTML.
	 *
	 * @param string $html            HTML content.
	 * @param string $base_cache_path The full path to the HTML cache file being created.
	 * @return string Optimized HTML content.
	 */
	public static function process( $html, $base_cache_path ) {
		self::$settings = Cache_Hive_Settings::get_settings();

		if ( ! self::is_enabled() || empty( $base_cache_path ) ) {
			return $html;
		}

		$dom                   = new \DOMDocument();
		$previous_libxml_state = libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_state );

		$xpath = new \DOMXPath( $dom );
		$nodes = $xpath->query( '//head/link[@rel="stylesheet"] | //head/style' );

		if ( 0 === $nodes->length ) {
			return $html;
		}

		if ( ! empty( self::$settings['css_combine'] ) ) {
			self::combine_and_minify_css( $dom, $xpath, $nodes, $base_cache_path );
		} else {
			self::minify_css_individually( $dom, $nodes, $base_cache_path );
		}

		$processed_html = $dom->saveHTML( $dom->{'documentElement'} );
		return '<!DOCTYPE html>' . "\n" . $processed_html;
	}

	/**
	 * Handles the combine and minify process with correct ordering and path handling.
	 *
	 * @param \DOMDocument $dom             The DOM object.
	 * @param \DOMXPath    $xpath           The DOMXPath object.
	 * @param \DOMNodeList $nodes           List of CSS nodes.
	 * @param string       $base_cache_path The HTML cache file path.
	 */
	private static function combine_and_minify_css( \DOMDocument $dom, \DOMXPath $xpath, \DOMNodeList $nodes, $base_cache_path ) {
		$groups          = array();
		$nodes_to_remove = array();
		$combine_inline  = ! empty( self::$settings['css_combine_external_inline'] );
		$head_node       = $xpath->query( '//head' )->item( 0 );

		if ( ! $head_node ) {
			return;
		}

		// Step 1: Collect all stylesheets in their original order, grouped by media type.
		foreach ( $nodes as $node ) {
			if ( self::is_excluded( $node ) ) {
				continue;
			}

			$media = $node->hasAttribute( 'media' ) ? strtolower( $node->getAttribute( 'media' ) ) : 'all';
			$media = empty( $media ) ? 'all' : $media;

			if ( 'link' === $node->{'nodeName'} ) {
				$url = $node->getAttribute( 'href' );
				if ( self::is_remote_url( $url ) ) {
					continue;
				}
				$path = self::get_file_path_from_url( $url );
				if ( $path && is_readable( $path ) ) {
					$groups[ $media ][] = $path;
					$nodes_to_remove[]  = $node;
				}
			} elseif ( $combine_inline && 'style' === $node->{'nodeName'} ) {
				$content = trim( $node->{'nodeValue'} );
				if ( ! empty( $content ) ) {
					$groups[ $media ][] = $content;
					$nodes_to_remove[]  = $node;
				}
			}
		}

		if ( empty( $groups ) ) {
			return;
		}

		$html_path_info = pathinfo( $base_cache_path );
		$css_base_name  = $html_path_info['filename'];
		$cache_dir_path = $html_path_info['dirname'];
		$cache_dir_url  = str_replace( WP_CONTENT_DIR, content_url(), $cache_dir_path );

		// Step 2: Process each group, preserving order and setting paths correctly.
		foreach ( $groups as $media => $sources ) {
			$minifier = new Minify\CSS();
			foreach ( $sources as $source ) {
				$minifier->add( $source );
			}

			$minifier->setMaxImportSize( 10 );

			$group_hash    = substr( md5( serialize( $sources ) ), 0, 8 );
			$css_filename  = "{$css_base_name}-{$media}-{$group_hash}.css";
			$minified_path = "{$cache_dir_path}/{$css_filename}";

			// Pass the destination path to minify() to correctly rewrite relative URLs.
			$optimized_css = $minifier->minify( $minified_path );

			if ( empty( trim( $optimized_css ) ) ) {
				continue;
			}

			if ( 'swap' === ( self::$settings['css_font_optimization'] ?? 'default' ) ) {
				$optimized_css = self::add_font_display_swap( $optimized_css );
				// Re-save the file after modification.
				file_put_contents( $minified_path, $optimized_css );
			}

			$new_link_url = "{$cache_dir_url}/{$css_filename}";
			$link_node    = $dom->createElement( 'link' );
			$link_node->setAttribute( 'rel', 'stylesheet' );
			$link_node->setAttribute( 'media', $media );
			$link_node->setAttribute( 'href', $new_link_url );
			$head_node->appendChild( $link_node );
		}

		// Step 3: Remove the original, processed nodes from the DOM.
		foreach ( $nodes_to_remove as $node ) {
			if ( $node->{'parentNode'} ) {
				$node->{'parentNode'}->removeChild( $node );
			}
		}
	}

	/**
	 * Handles minifying each CSS file and inline block individually with correct path handling.
	 *
	 * @param \DOMDocument $dom             The DOM object.
	 * @param \DOMNodeList $nodes           List of CSS nodes.
	 * @param string       $base_cache_path The HTML cache file path.
	 */
	private static function minify_css_individually( \DOMDocument $dom, \DOMNodeList $nodes, $base_cache_path ) {
		$html_path_info = pathinfo( $base_cache_path );
		$css_base_name  = $html_path_info['filename'];
		$cache_dir_path = $html_path_info['dirname'];
		$cache_dir_url  = str_replace( WP_CONTENT_DIR, content_url(), $cache_dir_path );

		foreach ( $nodes as $node ) {
			if ( self::is_excluded( $node ) ) {
				continue;
			}

			if ( 'link' === $node->{'nodeName'} ) {
				$url = $node->getAttribute( 'href' );
				if ( self::is_remote_url( $url ) ) {
					continue;
				}
				$source_path = self::get_file_path_from_url( $url );
				if ( ! $source_path || ! is_readable( $source_path ) ) {
					continue;
				}

				$minifier = new Minify\CSS( $source_path );
				$minifier->setMaxImportSize( 10 );

				$url_hash      = substr( md5( $url ), 0, 8 );
				$css_filename  = "{$css_base_name}-min-{$url_hash}.css";
				$minified_path = "{$cache_dir_path}/{$css_filename}";

				$optimized_css = $minifier->minify( $minified_path );

				if ( 'swap' === ( self::$settings['css_font_optimization'] ?? 'default' ) ) {
					$optimized_css = self::add_font_display_swap( $optimized_css );
				}

				if ( ! empty( trim( $optimized_css ) ) ) {
					file_put_contents( $minified_path, $optimized_css );
					$new_link_url = "{$cache_dir_url}/{$css_filename}";
					$node->setAttribute( 'href', $new_link_url );
				}
			} elseif ( 'style' === $node->{'nodeName'} ) {
				$original_css = $node->{'nodeValue'};
				if ( empty( trim( $original_css ) ) ) {
					continue;
				}
				$minifier      = new Minify\CSS( $original_css );
				$optimized_css = $minifier->minify();
				if ( 'swap' === ( self::$settings['css_font_optimization'] ?? 'default' ) ) {
					$optimized_css = self::add_font_display_swap( $optimized_css );
				}

				while ( $node->hasChildNodes() ) {
					$node->removeChild( $node->{'firstChild'} );
				}
				$node->appendChild( $dom->createTextNode( $optimized_css ) );
			}
		}
	}

	/**
	 * Check if a given CSS node should be excluded from optimization in a robust manner.
	 *
	 * This function is designed to handle various user inputs for exclusions, including:
	 * - Partial strings or filenames (e.g., 'font-awesome.css').
	 * - Full URLs or paths, with or without query strings.
	 * - Identifiers for inline <style> blocks (e.g., an ID or a specific comment).
	 *
	 * @param \DOMNode $node The CSS node (<link> or <style>).
	 * @return bool True if the node should be excluded.
	 */
	private static function is_excluded( \DOMNode $node ) {
		$exclusions = self::$settings['css_excludes'] ?? array();
		if ( empty( $exclusions ) ) {
			return false;
		}
		$check_string = '';
		if ( 'link' === $node->{'nodeName'} ) {
			$href = $node->getAttribute( 'href' );

			// Don't process empty or data URI links.
			if ( empty( $href ) || 0 === strpos( $href, 'data:' ) ) {
				return false;
			}

			// Normalize the URL by removing query strings and fragments for a more reliable match.
			// This allows excluding 'path/to/style.css' to match 'path/to/style.css?ver=1.2.3'.
			$check_string = strtok( $href, '?#' );

		} elseif ( 'style' === $node->{'nodeName'} ) {
			// For inline styles, check against the full tag definition and its content.
			// This allows excluding by id, a comment, or a specific CSS rule within the block.
			// e.g., an exclusion for 'no-optimize' would match <style id="no-optimize-css">...</style>
			// or a comment like /* no-optimize */.
			$check_string = $node->{ 'ownerDocument' }->saveHTML( $node );
		}

		if ( empty( $check_string ) ) {
			return false;
		}

		foreach ( (array) $exclusions as $exclude_pattern ) {
			// Sanitize the user-provided exclusion pattern by trimming whitespace.
			$pattern = trim( $exclude_pattern );

			// Skip if the pattern is empty after trimming.
			if ( empty( $pattern ) ) {
				continue;
			}

			// Use a case-insensitive comparison for better user experience.
			if ( false !== stripos( $check_string, $pattern ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convert a URL into an absolute server file path.
	 *
	 * @param string $url The URL of the CSS file.
	 * @return string|false The absolute file path or false if not possible.
	 */
	private static function get_file_path_from_url( $url ) {
		$url = strtok( $url, '?#' );

		$site_url    = site_url();
		$content_url = content_url();
		$abs_path    = rtrim( ABSPATH, '/' );

		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( strpos( $url, $content_url ) !== false ) {
			return str_replace( $content_url, rtrim( WP_CONTENT_DIR, '/' ), $url );
		}
		if ( strpos( $url, $site_url ) !== false ) {
			return str_replace( rtrim( $site_url, '/' ), $abs_path, $url );
		}
		if ( 0 === strpos( $url, '/' ) ) {
			$site_path = wp_parse_url( $site_url, PHP_URL_PATH ) ?? '';
			if ( ! empty( $site_path ) && '/' !== $site_path && 0 === strpos( $url, $site_path ) ) {
				$path_in_install = substr( $url, strlen( $site_path ) );
				return $abs_path . $path_in_install;
			}
			return $abs_path . $url;
		}
		return false;
	}

	/**
	 * Check if a URL points to an external resource.
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL is for a remote/external resource.
	 */
	private static function is_remote_url( $url ) {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		return ! empty( $url_host ) && strtolower( $url_host ) !== strtolower( $site_host );
	}

	/**
	 * Adds font-display: swap to @font-face rules that don't have it.
	 *
	 * @param string $css The CSS content.
	 * @return string The modified CSS content.
	 */
	private static function add_font_display_swap( $css ) {
		return preg_replace_callback(
			'/@font-face\s*\{([^\}]*)\}/is',
			function ( $matches ) {
				if ( false !== strpos( $matches[1], 'font-display' ) ) {
					return $matches[0];
				}
				return '@font-face{' . trim( $matches[1] ) . ';font-display:swap;}';
			},
			$css
		);
	}
}
