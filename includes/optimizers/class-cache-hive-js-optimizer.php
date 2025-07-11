<?php
/**
 * JS optimizer for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * A robust JS optimizer that implements safe minification and selectively delays
 * or defers script execution, ensuring sequential loading to prevent race conditions.
 */
class Cache_Hive_JS_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Holds the plugin settings.
	 *
	 * @var array
	 */
	private static $settings;

	/**
	 * The DOMDocument object for HTML manipulation.
	 *
	 * @var \DOMDocument
	 */
	private static $dom;

	/**
	 * The DOMXPath object for querying the DOM.
	 *
	 * @var \DOMXPath
	 */
	private static $xpath;

	/**
	 * Stores nodes that need to be removed from the DOM after processing.
	 *
	 * @var \DOMNode[]
	 */
	private static $nodes_to_remove = array();

	/**
	 * Flag to ensure the loader script is injected only once.
	 *
	 * @var bool
	 */
	private static $loader_injected = false;

	/**
	 * Flag to check if any script has been marked for delay.
	 *
	 * @var bool
	 */
	private static $delay_active = false;

	/**
	 * Processes and optimizes JavaScript in the given HTML.
	 *
	 * @param string $html            HTML content.
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

		self::process_all_scripts( $all_scripts, $base_cache_path );
		self::remove_original_nodes();

		if ( self::$delay_active ) {
			self::inject_loader_script();
		}

		$processed_html = self::$dom->saveHTML( self::$dom->{'documentElement'} );
		$processed_html = str_replace( array( '<![CDATA[', ']]>' ), '', $processed_html );

		return '<!DOCTYPE html>' . "\n" . $processed_html;
	}

	/**
	 * Unified processing function that handles all script optimization logic.
	 *
	 * @param array  $scripts         All scripts found on the page.
	 * @param string $base_cache_path The base path for creating new files.
	 */
	private static function process_all_scripts(
		array $scripts,
		string $base_cache_path
	) {
		$combine_enabled = ! empty( self::$settings['js_combine'] );
		$minify_enabled  = ! empty( self::$settings['js_minify'] );
		$delay_enabled   = 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' );
		$defer_enabled   = 'deferred' === ( self::$settings['js_defer_mode'] ?? 'default' );

		$scripts_to_combine          = array();
		$scripts_not_for_combination = array();

		// Categorize scripts into those for combination and those to be handled individually.
		// Excluded scripts are left untouched and skipped in this loop.
		foreach ( $scripts as $script ) {
			$node = $script['node'];

			if ( self::is_excluded( $node, 'js_excludes' ) || self::is_critical_data_script( $node ) ) {
				if ( $minify_enabled && self::is_critical_data_script( $node ) ) {
					$minifier            = new Cache_Hive_JS_Minifier();
					$node->{'nodeValue'} = $minifier->minify_content( $node->{'nodeValue'} );
				}
				continue;
			}

			if ( $combine_enabled ) {
				$scripts_to_combine[] = $script;
			} else {
				$scripts_not_for_combination[] = $script;
			}
		}

		// Process the combined group, ensuring the final file is non-render-blocking.
		if ( ! empty( $scripts_to_combine ) ) {
			$new_combined_node = self::create_combined_file( $scripts_to_combine, $base_cache_path );
			if ( $new_combined_node ) {
				if ( $delay_enabled && ! self::is_excluded( $new_combined_node, 'js_defer_excludes' ) ) {
					self::mark_script_for_delay( $new_combined_node );
					self::$delay_active = true;
				} elseif ( $defer_enabled && ! self::is_excluded( $new_combined_node, 'js_defer_excludes' ) ) {
					$new_combined_node->setAttribute( 'defer', '' );
				} else {
					// As a fallback for "combine only" mode, add 'async' to make it non-blocking.
					$new_combined_node->setAttribute( 'async', '' );
				}
			} else {
				// If combination failed, process these scripts individually.
				$scripts_not_for_combination = array_merge( $scripts_not_for_combination, $scripts_to_combine );
			}
		}

		// Process individual scripts (only runs if 'combine' is disabled).
		foreach ( $scripts_not_for_combination as $script ) {
			$node      = $script['node'];
			$is_inline = empty( $script['src'] );

			if ( $delay_enabled && ! self::is_excluded( $node, 'js_defer_excludes' ) ) {
				self::mark_script_for_delay( $node );
				self::$delay_active = true;
				if ( $minify_enabled && $is_inline ) {
					$minifier            = new Cache_Hive_JS_Minifier();
					$node->{'nodeValue'} = $minifier->minify_content( $node->{'nodeValue'} );
				}
			} elseif ( $defer_enabled && ! self::is_excluded( $node, 'js_defer_excludes' ) ) {
				if ( $node->hasAttribute( 'async' ) || $node->hasAttribute( 'defer' ) ) {
					continue;
				}
				if ( $is_inline ) {
					$new_node = self::externalize_inline_script( $script, $base_cache_path );
					if ( $new_node ) {
						$new_node->setAttribute( 'defer', '' );
						self::$nodes_to_remove[] = $node;
					}
				} else {
					$node->setAttribute( 'defer', '' );
				}
			} elseif ( $minify_enabled && $is_inline ) {
				$minifier            = new Cache_Hive_JS_Minifier();
				$node->{'nodeValue'} = $minifier->minify_content( $node->{'nodeValue'} );
			}
		}
	}

	/**
	 * Creates a single combined JS file.
	 *
	 * @param array  $scripts_to_combine An array of script data to combine.
	 * @param string $base_cache_path    The base path for creating new files.
	 * @return \DOMNode|null The new script node for the combined file.
	 */
	private static function create_combined_file(
		array $scripts_to_combine,
		string $base_cache_path
	) {
		$content_parts    = array();
		$sources_for_hash = array();

		foreach ( $scripts_to_combine as $script ) {
			$content = ! empty( $script['src'] ) ? self::get_file_contents( $script['src'] ) : $script['content'];
			$content = str_replace( array( '<![CDATA[', ']]>' ), '', (string) $content );
			if ( null === $content ) {
				continue;
			}
			$content_parts[]         = $content;
			$sources_for_hash[]      = ! empty( $script['src'] ) ? $script['src'] : md5( $content );
			self::$nodes_to_remove[] = $script['node'];
		}

		if ( empty( $content_parts ) ) {
			return null;
		}

		$final_js     = implode( ";\n", $content_parts );
		$optimized_js = ! empty( self::$settings['js_minify'] ) ? ( new Cache_Hive_JS_Minifier() )->minify_content( $final_js ) : $final_js;

		$html_path_info = pathinfo( $base_cache_path );
		$cache_dir_path = $html_path_info['dirname'];
		$hash           = substr( md5( serialize( $sources_for_hash ) ), 0, 8 );
		$js_filename    = "{$html_path_info["filename"]}-combined-{$hash}.js";
		$cache_file     = "{$cache_dir_path}/{$js_filename}";

		if ( file_put_contents( $cache_file, $optimized_js ) ) {
			return self::create_script_node( $cache_file );
		}
		return null;
	}

	/**
	 * Creates an external JS file from an inline script's content.
	 *
	 * @param array  $script          The script data array.
	 * @param string $base_cache_path The base path for creating new files.
	 * @return \DOMNode|null The new script node for the external file, or null on failure.
	 */
	private static function externalize_inline_script(
		array $script,
		string $base_cache_path
	) {
		$content = str_replace( array( '<![CDATA[', ']]>' ), '', $script['content'] );
		if ( empty( trim( $content ) ) ) {
			return null;
		}

		if ( ! empty( self::$settings['js_minify'] ) ) {
			$minifier = new Cache_Hive_JS_Minifier();
			$content  = $minifier->minify_content( $content );
		}

		$html_path_info = pathinfo( $base_cache_path );
		$cache_dir_path = $html_path_info['dirname'];
		$hash           = substr( md5( $content ), 0, 8 );
		$js_filename    = "{$html_path_info["filename"]}-inline-{$hash}.js";
		$cache_file     = "{$cache_dir_path}/{$js_filename}";

		if ( file_put_contents( $cache_file, $content ) ) {
			return self::create_script_node( $cache_file );
		}
		return null;
	}

	/**
	 * Modifies a script tag to be handled by the delayed script loader.
	 *
	 * @param \DOMNode $node The script DOMNode to modify.
	 */
	private static function mark_script_for_delay( \DOMNode $node ) {
		if ( $node->hasAttribute( 'src' ) ) {
			$node->setAttribute( 'data-src', $node->getAttribute( 'src' ) );
			$node->removeAttribute( 'src' );
		}
		$node->setAttribute( 'type', 'cache-hive/javascript' );
		if ( $node->hasAttribute( 'async' ) ) {
			$node->removeAttribute( 'async' );
		}
		if ( $node->hasAttribute( 'defer' ) ) {
			$node->removeAttribute( 'defer' );
		}
	}

	/**
	 * Injects the JS loader that triggers sequential execution on user interaction.
	 */
	private static function inject_loader_script() {
		if ( self::$loader_injected ) {
			return;
		}
		$loader_js   = <<<JS
const chEvents=new Set(["mouseover","keydown","touchmove","touchstart","wheel"]);var chUrlCreator=window.URL||window.webkitURL;function chTrigger(){chEvents.forEach(e=>window.removeEventListener(e,chTrigger,{passive:!0}));var t=document.querySelectorAll('script[type="cache-hive/javascript"]');"loading"==document.readyState?window.addEventListener("DOMContentLoaded",()=>chLoadScripts(t)):chLoadScripts(t)}async function chLoadScripts(t){for(const e of t)await new Promise(r=>{chLoadOne(e,r)});document.dispatchEvent(new Event("ch:scripts_loaded"))}function chLoadOne(t,e){const r=document.createElement("script");r.addEventListener("load",e),r.addEventListener("error",e);for(const o of t.getAttributeNames())"type"!==o&&r.setAttribute("data-src"===o?"src":o,t.getAttribute(o));let o=!1;r.type="text/javascript",!r.src&&t.textContent&&(r.src=chInlineToSrc(t.textContent),o=!0),t.after(r),t.remove(),o&&e()}function chInlineToSrc(t){try{var e=chUrlCreator.createObjectURL(new Blob([t],{type:"text/javascript"}))}catch(r){e="data:text/javascript;base64,"+btoa(t)}return e}chEvents.forEach(e=>window.addEventListener(e,chTrigger,{passive:!0}));
JS;
		$loader_node = self::$dom->createElement( 'script' );
		$loader_node->setAttribute( 'id', 'cache-hive-loader' );
		$loader_node->appendChild( self::$dom->createTextNode( $loader_js ) );
		$body = self::$xpath->query( '//body' )->item( 0 );
		if ( $body ) {
			if ( $body->{'firstChild'} ) {
				$body->insertBefore( $loader_node, $body->{'firstChild'} );
			} else {
				$body->appendChild( $loader_node );
			}
		}
		self::$loader_injected = true;
	}

	/**
	 * Checks if a script is a critical, inline data object.
	 *
	 * @param \DOMNode $node The script node to check.
	 * @return bool True if it's a critical data script.
	 */
	private static function is_critical_data_script( \DOMNode $node ) {
		if ( ! empty( $node->getAttribute( 'src' ) ) ) {
			return false;
		}
		$id = $node->getAttribute( 'id' );
		if ( ! empty( $id ) && ( str_ends_with( $id, '-js-extra' ) || str_ends_with( $id, '-js-before' ) ) ) {
			return true;
		}
		$content = trim( $node->{'nodeValue'} );
		if ( str_starts_with( $content, 'var ' ) || str_starts_with( $content, 'const ' ) || str_starts_with( $content, 'let ' ) ) {
			if ( str_contains( $content, '{' ) || str_contains( $content, '[' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if a given JS node should be excluded from optimization.
	 *
	 * @param \DOMNode $node            The JS node (<script>).
	 * @param string   $exclusion_key The key in settings for the exclusion list.
	 * @return bool True if the node should be excluded.
	 */
	private static function is_excluded( \DOMNode $node, $exclusion_key ) {
		$src = $node->getAttribute( 'src' );
		if ( ! empty( $src ) && false !== strpos( $src, '/wp-includes/js/dist/' ) ) {
			return true;
		}
		$exclusions = self::$settings[ $exclusion_key ] ?? array();
		if ( empty( $exclusions ) ) {
			return false;
		}
		$check_string = ! empty( $src ) ? strtok( $src, '?#' ) : $node->{'ownerDocument'}->saveHTML( $node );
		if ( empty( $check_string ) ) {
			return false;
		}
		foreach ( (array) $exclusions as $exclude_pattern ) {
			$pattern = trim( $exclude_pattern );
			if ( empty( $pattern ) ) {
				continue;
			}
			if ( false !== stripos( $check_string, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Creates a new script DOM node and appends it to the body.
	 *
	 * @param string $path The file path of the script.
	 * @return \DOMNode The newly created script node.
	 */
	private static function create_script_node( $path ) {
		$cache_dir_path = dirname( $path );
		$js_filename    = basename( $path );
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
	 * Retrieves the content of a file from a given URL.
	 *
	 * @param string $url The URL of the file.
	 * @return string|null The file content, or null if not found or readable.
	 */
	private static function get_file_contents( $url ) {
		$path = self::get_file_path_from_url( $url );
		if ( $path && is_readable( $path ) ) {
			return file_get_contents( $path );
		}
		return null;
	}

	/**
	 * Initializes the DOMDocument and DOMXPath objects, protecting inline scripts with CDATA.
	 *
	 * @param string $html The HTML content to load.
	 */
	private static function init_dom( $html ) {
		self::$dom             = new \DOMDocument();
		self::$nodes_to_remove = array();
		self::$loader_injected = false;
		self::$delay_active    = false;
		$html                  = preg_replace_callback(
			'~<script(?P<attrs>[^>]*)>(?P<content>.*?)</script>~is',
			function ( $matches ) {
				if ( false === stripos( $matches['attrs'], 'src=' ) ) {
					return sprintf( '<script%s><![CDATA[%s]]></script>', $matches['attrs'], $matches['content'] );
				}
				return $matches[0];
			},
			$html
		);
		$previous_libxml_state = libxml_use_internal_errors( true );
		self::$dom->loadHTML(
			'<?xml encoding="UTF-8">' . $html,
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_libxml_state );
		self::$xpath = new \DOMXPath( self::$dom );
	}

	/**
	 * Checks if any JS optimization is enabled in the settings.
	 *
	 * @return bool True if any JS optimization is enabled.
	 */
	private static function is_enabled() {
		return ! empty( self::$settings['js_minify'] ) ||
			! empty( self::$settings['js_combine'] ) ||
			( isset( self::$settings['js_defer_mode'] ) && 'default' !== self::$settings['js_defer_mode'] );
	}

	/**
	 * Removes the original script nodes from the DOM that have been processed.
	 */
	private static function remove_original_nodes() {
		foreach ( self::$nodes_to_remove as $node ) {
			if ( $node->{'parentNode'} ) {
				$node->{'parentNode'}->removeChild( $node );
			}
		}
	}

	/**
	 * Converts a URL into an absolute server file path.
	 *
	 * @param string $url The URL of the JS file.
	 * @return string|false The absolute file path or false if not possible.
	 */
	private static function get_file_path_from_url( $url ) {
		$url         = strtok( $url, '?#' );
		$site_url    = site_url();
		$content_url = content_url();
		$abs_path    = rtrim( ABSPATH, '/' );
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		if ( str_contains( $url, $content_url ) ) {
			return str_replace( $content_url, rtrim( WP_CONTENT_DIR, '/' ), $url );
		}
		if ( str_contains( $url, $site_url ) ) {
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
	 * Checks if a URL points to an external resource.
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
	 * Identifies and categorizes script nodes from the DOM.
	 *
	 * @return array An array of script data.
	 */
	private static function identify_and_categorize_scripts() {
		$scripts      = array();
		$ignore_types = array( 'module', 'importmap', 'application/ld+json', 'application/json', 'speculationrules', 'text/template', 'text/html' );
		foreach ( self::$xpath->query( '//script' ) as $node ) {
			$type = $node->getAttribute( 'type' );
			if ( ! empty( $type ) && in_array( strtolower( $type ), $ignore_types, true ) ) {
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
}
