<?php
/**
 * JS optimizer for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Vendor\MatthiasMullie\Minify;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles JavaScript optimization logic for Cache Hive.
 */
class Cache_Hive_JS_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Holds the plugin settings.
	 *
	 * @var array
	 */
	private static $settings;

	/**
	 * Holds the DOMDocument object.
	 *
	 * @var \DOMDocument
	 */
	private static $dom;

	/**
	 * Holds the DOMXPath object.
	 *
	 * @var \DOMXPath
	 */
	private static $xpath;

	/**
	 * A list of original script nodes that have been processed and should be removed.
	 *
	 * @var array
	 */
	private static $nodes_to_remove = array();

	/**
	 * Flag to ensure the delay loader script is only injected once.
	 *
	 * @var bool
	 */
	private static $loader_injected = false;


	/**
	 * Stage 1: Identify all script tags and categorize them.
	 *
	 * @return array An array of script data.
	 */
	private static function identify_and_categorize_scripts() {
		$scripts = array();
		$nodes   = self::$xpath->query( '//script' );

		// THE FIX: Add 'speculationrules' to the list of special types to be ignored.
		$special_types = array( 'module', 'importmap', 'application/ld+json', 'speculationrules' );

		foreach ( $nodes as $node ) {
			$type = $node->getAttribute( 'type' );
			if ( ! empty( $type ) && in_array( strtolower( $type ), $special_types, true ) ) {
				continue;
			}
			$scripts[] = array(
				'node'    => $node,
				'src'     => $node->getAttribute( 'src' ),
				'content' => $node->{'nodeValue'},
			);
		}
		return $scripts;
	}

	/**
	 * Main processing function for JS optimization.
	 *
	 * @param string $html            The HTML content.
	 * @param string $base_cache_path The full path to the HTML cache file being created.
	 * @return string Optimized HTML content.
	 */
	public static function process( $html, $base_cache_path ) {
		self::$settings = Cache_Hive_Settings::get_settings();
		if ( ! self::is_enabled() ) {
			return $html;
		}

		self::init_dom( $html );
		$all_scripts = self::identify_and_categorize_scripts();
		if ( empty( $all_scripts ) ) {
			return $html;
		}

		$live_scripts = self::process_for_combine_minify( $all_scripts, $base_cache_path );
		self::apply_timing_modifications( $live_scripts );
		self::remove_original_nodes();

		$processed_html = self::$dom->saveHTML( self::$dom->{'documentElement'} );
		return '<!DOCTYPE html>' . "\n" . $processed_html;
	}

	/**
	 * Checks if any JS optimization setting is active.
	 *
	 * @return bool
	 */
	private static function is_enabled() {
		return ! empty( self::$settings['js_minify'] )
			|| ! empty( self::$settings['js_combine'] )
			|| ( isset( self::$settings['js_defer_mode'] ) && 'default' !== self::$settings['js_defer_mode'] );
	}

	/**
	 * Initializes the DOM parser.
	 *
	 * @param string $html The HTML content.
	 */
	private static function init_dom( $html ) {
		self::$dom             = new \DOMDocument();
		self::$nodes_to_remove = array();
		self::$loader_injected = false;

		$previous_libxml_state = libxml_use_internal_errors( true );
		self::$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_state );

		self::$xpath = new \DOMXPath( self::$dom );
	}

	/**
	 * New orchestrator for combining/minifying.
	 *
	 * @param array  $scripts         All scripts found on the page.
	 * @param string $base_cache_path The base path for creating new files.
	 * @return array The definitive list of "live" scripts after processing.
	 */
	private static function process_for_combine_minify( array $scripts, $base_cache_path ) {
		if ( ! empty( self::$settings['js_combine'] ) ) {
			$pass_through_scripts = array();
			$content_to_combine   = array();

			foreach ( $scripts as $script ) {
				$node           = $script['node'];
				$combine_inline = ! empty( self::$settings['js_combine_external_inline'] );
				$is_local_file  = ! empty( $script['src'] ) && ! self::is_remote_url( $script['src'] );
				$is_inline      = empty( $script['src'] ) && ! empty( trim( $script['content'] ) );

				if ( ! self::is_excluded( $node, 'js_excludes' ) && ( $is_local_file || ( $combine_inline && $is_inline ) ) ) {
					$content_to_combine[]    = $script;
					self::$nodes_to_remove[] = $node;
				} else {
					$pass_through_scripts[] = $script;
				}
			}

			$new_combined_node = self::create_combined_script( $content_to_combine, $base_cache_path );
			if ( $new_combined_node ) {
				$pass_through_scripts[] = array( 'node' => $new_combined_node );
			}

			return $pass_through_scripts;
		}

		if ( ! empty( self::$settings['js_minify'] ) ) {
			self::minify_scripts_individually( $scripts, $base_cache_path );
		}

		// If not combining, the original scripts (as modified) are passed to the next stage.
		return $scripts;
	}

	/**
	 * Creates a single combined script file.
	 *
	 * @param array  $scripts_to_combine Scripts designated for combination.
	 * @param string $base_cache_path    Path for file creation.
	 * @return \DOMNode|null
	 */
	private static function create_combined_script( array $scripts_to_combine, $base_cache_path ) {
		if ( empty( $scripts_to_combine ) ) {
			return null;
		}

		$js_strings = array();
		foreach ( $scripts_to_combine as $script ) {
			if ( ! empty( $script['src'] ) ) {
				$path = self::get_file_path_from_url( $script['src'] );
				if ( $path && is_readable( $path ) ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$js_strings[] = file_get_contents( $path );
				}
			} else {
				$js_strings[] = $script['content'];
			}
		}

		if ( empty( $js_strings ) ) {
			return null;
		}

		$full_js_string = implode( ";\n", $js_strings );
		$optimized_js   = ( new Minify\JS( $full_js_string ) )->minify();

		$html_path_info = pathinfo( $base_cache_path );
		$js_base_name   = $html_path_info['filename'];
		$cache_dir_path = $html_path_info['dirname'];
		$js_filename    = "{$js_base_name}-combined.js";
		$cache_file     = "{$cache_dir_path}/{$js_filename}";

		if ( ! file_put_contents( $cache_file, $optimized_js ) ) {
			return null;
		}

		$cache_dir_url  = str_replace( WP_CONTENT_DIR, content_url(), $cache_dir_path );
		$new_script_url = "{$cache_dir_url}/{$js_filename}";
		$new_node       = self::$dom->createElement( 'script' );
		$new_node->setAttribute( 'src', $new_script_url );

		$body = self::$xpath->query( '//body' )->item( 0 );
		if ( $body ) {
			$body->appendChild( $new_node );
		}

		return $new_node;
	}

	/**
	 * Handles minifying each script file individually.
	 *
	 * @param array  $scripts         The categorized scripts.
	 * @param string $base_cache_path The base path for file creation.
	 */
	private static function minify_scripts_individually( array $scripts, $base_cache_path ) {
		$html_path_info = pathinfo( $base_cache_path );
		$js_base_name   = $html_path_info['filename'];
		$cache_dir_path = $html_path_info['dirname'];
		$cache_dir_url  = str_replace( WP_CONTENT_DIR, content_url(), $cache_dir_path );

		foreach ( $scripts as $script ) {
			$node = $script['node'];
			if ( self::is_excluded( $node, 'js_excludes' ) ) {
				continue;
			}

			if ( ! empty( $script['src'] ) && ! self::is_remote_url( $script['src'] ) ) {
				$path = self::get_file_path_from_url( $script['src'] );
				if ( ! $path || ! is_readable( $path ) ) {
					continue;
				}
				$minified_js = ( new Minify\JS( $path ) )->minify();
				$url_hash    = substr( md5( $script['src'] ), 0, 8 );
				$js_filename = "{$js_base_name}-min-{$url_hash}.js";
				$cache_file  = "{$cache_dir_path}/{$js_filename}";

				if ( file_put_contents( $cache_file, $minified_js ) ) {
					$node->setAttribute( 'src', "{$cache_dir_url}/{$js_filename}" );
				}
			} elseif ( empty( $script['src'] ) && ! empty( $script['content'] ) ) {
				$minified_js = ( new Minify\JS( $script['content'] ) )->minify();
				while ( $node->hasChildNodes() ) {
					$node->removeChild( $node->{'firstChild'} );
				}
				$node->appendChild( self::$dom->createTextNode( $minified_js ) );
			}
		}
	}

	/**
	 * Stage 3: Apply defer or delayed attributes to scripts.
	 *
	 * @param array $scripts The scripts to process (original or combined).
	 */
	private static function apply_timing_modifications( array $scripts ) {
		$mode = self::$settings['js_defer_mode'] ?? 'default';
		if ( 'default' === $mode || empty( $scripts ) ) {
			return;
		}

		foreach ( $scripts as $script ) {
			$node = $script['node'];
			if ( empty( $node->getAttribute( 'src' ) ) || self::is_excluded( $node, 'js_defer_excludes' ) ) {
				continue;
			}

			if ( 'deferred' === $mode ) {
				$node->setAttribute( 'defer', '' );
				$node->removeAttribute( 'async' );
			} elseif ( 'delayed' === $mode ) {
				$src = $node->getAttribute( 'src' );
				$node->setAttribute( 'data-ch-src', $src );
				$node->setAttribute( 'type', 'text/cache-hive-script' );
				$node->removeAttribute( 'src' );
				$node->removeAttribute( 'async' );
				$node->removeAttribute( 'defer' );
				self::inject_loader_script();
			}
		}
	}

	/**
	 * Injects the delayed loader script into the page.
	 */
	private static function inject_loader_script() {
		if ( self::$loader_injected ) {
			return;
		}
		$loader_js   = 'const chEvents=new Set(["mouseover","keydown","touchmove","touchstart"]);function chTrigger(){chLoad(),chEvents.forEach(e=>window.removeEventListener(e,chTrigger,{passive:!0}))}function chLoad(){document.querySelectorAll("script[type=\'text/cache-hive-script\']").forEach(e=>{const t=document.createElement("script");e.getAttributeNames().forEach(n=>{const o=e.getAttribute(n);o&&t.setAttribute("data-ch-src"===n?"src":n,o)}),t.type="text/javascript",e.parentNode.replaceChild(t,e)})}chEvents.forEach(e=>window.addEventListener(e,chTrigger,{passive:!0}));';
		$loader_node = self::$dom->createElement( 'script' );
		$loader_node->setAttribute( 'id', 'cache-hive-loader' );
		$loader_node->appendChild( self::$dom->createTextNode( $loader_js ) );
		$body = self::$xpath->query( '//body' )->item( 0 );
		if ( $body ) {
			$body->appendChild( $loader_node );
		}
		self::$loader_injected = true;
	}

	/**
	 * Stage 4: Removes all original script nodes that were combined.
	 */
	private static function remove_original_nodes() {
		foreach ( self::$nodes_to_remove as $node ) {
			if ( $node->{'parentNode'} ) {
				$node->{'parentNode'}->removeChild( $node );
			}
		}
	}

	/**
	 * Generic check to see if a node should be excluded based on a settings key.
	 *
	 * @param \DOMNode $node           The script node.
	 * @param string   $exclusion_key The key in the settings array (e.g., 'js_excludes').
	 * @return bool
	 */
	private static function is_excluded( \DOMNode $node, $exclusion_key ) {
		$exclusions = self::$settings[ $exclusion_key ] ?? array();
		if ( empty( $exclusions ) ) {
			return false;
		}
		$src     = $node->getAttribute( 'src' );
		$content = $node->{'nodeValue'};
		foreach ( $exclusions as $pattern ) {
			if ( ! empty( $pattern ) ) {
				if ( ! empty( $src ) && false !== strpos( $src, $pattern ) ) {
					return true;
				}
				if ( ! empty( $content ) && false !== strpos( $content, $pattern ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Helper function to get a server path from a URL.
	 *
	 * @param string $url The script URL.
	 * @return string|false
	 */
	private static function get_file_path_from_url( $url ) {
		$url         = strtok( $url, '?#' );
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
				return $abs_path . substr( $url, strlen( $site_path ) );
			}
			return $abs_path . $url;
		}
		return false;
	}

	/**
	 * Helper function to check if a URL is remote.
	 *
	 * @param string $url The script URL.
	 * @return bool
	 */
	private static function is_remote_url( $url ) {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$url_host  = wp_parse_url( $url, PHP_URL_HOST );
		return ! empty( $url_host ) && strtolower( $url_host ) !== strtolower( $site_host );
	}
}
