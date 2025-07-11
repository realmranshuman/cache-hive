<?php
/**
 * JS optimizer for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A robust JS optimizer that implements safe minification and selectively delays
 * script execution, respecting critical inline data script dependencies.
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
	 * @var array
	 */
	private static $nodes_to_remove = array();

	/**
	 * Flag to ensure the loader script is injected only once.
	 *
	 * @var bool
	 */
	private static $loader_injected = false;

	/**
	 * Processes and optimizes JavaScript in the given HTML.
	 *
	 * @param string $html HTML content.
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

		// A single, unified processing function handles all logic.
		$live_scripts = self::process_all_scripts( $all_scripts, $base_cache_path );

		// The 'defer' attribute is now handled separately after all other processing.
		self::apply_defer_attribute( $live_scripts );
		self::remove_original_nodes();

		// Inject the loader script if the 'delayed' mode is active.
		if ( 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' ) ) {
			self::inject_loader_script();
		}

		$processed_html = self::$dom->saveHTML( self::$dom->{'documentElement'} );
		return '<!DOCTYPE html>' . "\n" . $processed_html;
	}

	/**
	 * A new, unified processing function that handles all logic based on settings.
	 *
	 * @param array  $scripts All scripts found on the page.
	 * @param string $base_cache_path The base path for creating new files.
	 * @return array The final list of script nodes on the page.
	 */
	private static function process_all_scripts( array $scripts, string $base_cache_path ) {
		$combine_enabled = ! empty( self::$settings['js_combine'] );
		$minify_enabled  = ! empty( self::$settings['js_minify'] );
		$delay_enabled   = ( 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' ) );

		$final_script_list      = array();
		$scripts_for_combine    = array();
		$combine_inline_enabled = ! empty( self::$settings['js_combine_external_inline'] );

		// Step 1: Loop through all scripts and categorize them for processing.
		foreach ( $scripts as $script ) {
			$node = $script['node'];

			// Priority 1: Check for total exclusion.
			if ( self::is_excluded( $node, 'js_excludes' ) ) {
				$final_script_list[] = $script;
				continue;
			}

			// Priority 2: Check for critical inline data scripts.
			if ( self::is_critical_data_script( $node ) ) {
				if ( $minify_enabled ) {
					$minifier            = new Cache_Hive_JS_Minifier();
					$node->{'nodeValue'} = $minifier->minify_content( $node->{'nodeValue'} );
				}
				$final_script_list[] = $script;
				continue;
			}

			// Categorize remaining scripts for combination or individual processing.
			$is_local_file = ! empty( $script['src'] ) && ! self::is_remote_url( $script['src'] );
			$is_inline     = empty( $script['src'] ) && ! empty( trim( $script['content'] ) );

			if ( $combine_enabled && ( $is_local_file || ( $is_inline && $combine_inline_enabled ) ) ) {
				$scripts_for_combine[] = $script;
			} else {
				// Process individually (includes remote scripts and inline scripts when not combining them).
				self::process_individual_script( $script, $base_cache_path );
				$final_script_list[] = $script;
			}
		}

		// Step 2: Create the combined file if necessary.
		if ( $combine_enabled && ! empty( $scripts_for_combine ) ) {
			$new_combined_node = self::create_combined_file( $scripts_for_combine, $base_cache_path );
			if ( $new_combined_node ) {
				$final_script_list[] = array( 'node' => $new_combined_node );
			}
		}

		return $final_script_list;
	}

	/**
	 * Creates a single combined and minified JS file, wrapping contents for delay as needed.
	 *
	 * @param array  $scripts_to_combine An array of script data to combine.
	 * @param string $base_cache_path The base path for creating new files.
	 * @return \DOMNode|null The new script node for the combined file.
	 */
	private static function create_combined_file( array $scripts_to_combine, string $base_cache_path ) {
		$immediate_content = array();
		$delayed_content   = array();
		$sources_for_hash  = array();
		$delay_enabled     = ( 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' ) );

		foreach ( $scripts_to_combine as $script ) {
			$content = ! empty( $script['src'] ) ? self::get_file_contents( $script['src'] ) : $script['content'];
			if ( null === $content ) {
				continue;
			}

			if ( $delay_enabled && ! self::is_excluded( $script['node'], 'js_defer_excludes' ) ) {
				$delayed_content[] = $content;
			} else {
				$immediate_content[] = $content;
			}
			$sources_for_hash[]      = ! empty( $script['src'] ) ? $script['src'] : md5( $content );
			self::$nodes_to_remove[] = $script['node'];
		}

		if ( empty( $immediate_content ) && empty( $delayed_content ) ) {
			return null;
		}

		$final_js = '';
		if ( ! empty( $immediate_content ) ) {
			$final_js .= implode( ";\n", $immediate_content );
		}
		if ( ! empty( $delayed_content ) ) {
			$final_js .= ( '' === $final_js ? '' : ";\n" ) . self::wrap_for_delay( implode( ";\n", $delayed_content ) );
		}

		$optimized_js = ! empty( self::$settings['js_minify'] ) ? ( new Cache_Hive_JS_Minifier() )->minify_content( $final_js ) : $final_js;

		$html_path_info = pathinfo( $base_cache_path );
		$cache_dir_path = $html_path_info['dirname'];
		$hash           = substr( md5( serialize( $sources_for_hash ) ), 0, 8 );
		$js_filename    = "{$html_path_info['filename']}-combined-{$hash}.js";
		$cache_file     = "{$cache_dir_path}/{$js_filename}";

		if ( file_put_contents( $cache_file, $optimized_js ) ) {
			return self::create_script_node( $cache_file );
		}

		return null;
	}

	/**
	 * Processes a single script that is not being combined.
	 *
	 * @param array  &$script_ref Reference to the script data array.
	 * @param string $base_cache_path The base path for creating new files.
	 */
	private static function process_individual_script( array &$script_ref, string $base_cache_path ) {
		$node          = $script_ref['node'];
		$is_inline     = empty( $script_ref['src'] ) && ! empty( trim( $script_ref['content'] ) );
		$delay_enabled = ( 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' ) );

		// We only process inline scripts this way. External scripts are left as-is.
		if ( $is_inline ) {
			$raw_content = $script_ref['content'];

			if ( $delay_enabled && ! self::is_excluded( $node, 'js_defer_excludes' ) ) {
				$raw_content = self::wrap_for_delay( $raw_content );
			}

			$optimized_content = ! empty( self::$settings['js_minify'] ) ? ( new Cache_Hive_JS_Minifier() )->minify_content( $raw_content ) : $raw_content;

			while ( $node->hasChildNodes() ) {
				$node->removeChild( $node->{'firstChild'} );
			}
			$node->appendChild( self::$dom->createTextNode( $optimized_content ) );
		}
	}

	/**
	 * Wraps a string of JavaScript to be executed on a custom event.
	 *
	 * @param string $js_content The JavaScript to wrap.
	 * @return string The wrapped JavaScript.
	 */
	private static function wrap_for_delay( $js_content ) {
		return "document.addEventListener('ch:run_delayed_scripts',function(){(function(){\n" . $js_content . "\n})();},{once:!0});";
	}

	/**
	 * Applies `defer` attribute to scripts.
	 *
	 * @param array $scripts The final list of script nodes on the page.
	 */
	private static function apply_defer_attribute( array $scripts ) {
		$mode = self::$settings['js_defer_mode'] ?? 'default';
		if ( 'deferred' !== $mode || empty( $scripts ) ) {
			return;
		}

		foreach ( $scripts as $script ) {
			$node = $script['node'];
			if ( empty( $node->getAttribute( 'src' ) ) || self::is_excluded( $node, 'js_defer_excludes' ) ) {
				continue;
			}
			if ( ! $node->hasAttribute( 'async' ) && ! $node->hasAttribute( 'defer' ) ) {
				$node->setAttribute( 'defer', '' );
			}
		}
	}

	/**
	 * Checks if a script is a critical, inline data object that must not be delayed.
	 *
	 * @param \DOMNode $node The script node to check.
	 * @return bool True if it's a critical data script.
	 */
	private static function is_critical_data_script( \DOMNode $node ) {
		if ( ! empty( $node->getAttribute( 'src' ) ) ) {
			return false; // Only applies to inline scripts.
		}

		$id = $node->getAttribute( 'id' );
		if ( ! empty( $id ) && ( str_ends_with( $id, '-js-extra' ) || str_ends_with( $id, '-js-before' ) ) ) {
			return true;
		}

		// Fallback for scripts without IDs, common in some themes/plugins.
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
	 * This function is designed to handle various user inputs for exclusions, including:
	 * - Partial strings or filenames (e.g., 'jquery.js').
	 * - Full URLs or paths, with or without query strings.
	 * - Identifiers for inline <script> blocks (e.g., an ID or a specific comment).
	 * It also includes hardcoded exclusions for WordPress core scripts.
	 *
	 * @param \DOMNode $node The JS node (<script>).
	 * @param string   $exclusion_key The key in settings for the exclusion list (e.g., 'js_excludes', 'js_defer_excludes').
	 * @return bool True if the node should be excluded.
	 */
	private static function is_excluded( \DOMNode $node, $exclusion_key ) {
		$src = $node->getAttribute( 'src' );
		$id  = $node->getAttribute( 'id' );

		// Hardcoded exclusions for WordPress core scripts that should never be optimized.
		if ( ( ! empty( $src ) && false !== strpos( $src, '/wp-includes/js/dist/' ) ) || ( ! empty( $id ) && 0 === strpos( $id, 'wp-' ) ) ) {
			return true;
		}

		$exclusions = self::$settings[ $exclusion_key ] ?? array();
		if ( empty( $exclusions ) ) {
			return false;
		}

		$check_string = '';
		if ( ! empty( $src ) ) {
			// Normalize the URL by removing query strings and fragments for a more reliable match.
			// This allows excluding 'path/to/script.js' to match 'path/to/script.js?ver=1.2.3'.
			$check_string = strtok( $src, '?#' );
		} else {
			// For inline scripts, check against the full tag definition and its content.
			// This allows excluding by id, a comment, or a specific JS rule within the block.
			// e.g., an exclusion for 'no-optimize' would match <script id="no-optimize-js">...</script>
			// or a comment like /* no-optimize */.
			$check_string = $node->ownerDocument->saveHTML( $node ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		if ( empty( $check_string ) ) {
			return false;
		}

		foreach ( (array) $exclusions as $exclude_pattern ) {
			// Sanitize the user-provided exclusion pattern by trimming whitespace.
			$pattern = trim( $exclude_pattern );
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
	 * Injects the JavaScript loader script into the HTML body.
	 * This script listens for user interaction to trigger delayed script execution.
	 */
	private static function inject_loader_script() {
		if ( self::$loader_injected ) {
			return;
		}
		$loader_js   = 'const chEvents=new Set(["mouseover","keydown","touchmove","touchstart"]);function chTrigger(){document.dispatchEvent(new Event("ch:run_delayed_scripts")),chEvents.forEach(e=>window.removeEventListener(e,chTrigger,{passive:!0}))}chEvents.forEach(e=>window.addEventListener(e,chTrigger,{passive:!0}));';
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
			$body->appendChild( $new_node ); }
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
			return file_get_contents( $path ); }
		return null;
	}

	/**
	 * Initializes the DOMDocument and DOMXPath objects.
	 *
	 * @param string $html The HTML content to load.
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
	 * Checks if any JS optimization is enabled in the settings.
	 *
	 * @return bool True if any JS optimization is enabled, false otherwise.
	 */
	private static function is_enabled() {
		return ! empty( self::$settings['js_minify'] )
			|| ! empty( self::$settings['js_combine'] )
			|| ( isset( self::$settings['js_defer_mode'] ) && 'default' !== self::$settings['js_defer_mode'] );
	}

	/**
	 * Removes the original script nodes from the DOM that have been processed (combined or minified).
	 * This prevents duplicate scripts in the output HTML.
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
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url; }
		if ( strpos( $url, $content_url ) !== false ) {
			return str_replace( $content_url, rtrim( WP_CONTENT_DIR, '/' ), $url ); }
		if ( strpos( $url, $site_url ) !== false ) {
			return str_replace( rtrim( $site_url, '/' ), $abs_path, $url ); }
		if ( 0 === strpos( $url, '/' ) ) {
			$site_path = wp_parse_url( $site_url, PHP_URL_PATH ) ?? '';
			if ( ! empty( $site_path ) && '/' !== $site_path && 0 === strpos( $url, $site_path ) ) {
				return $abs_path . substr( $url, strlen( $site_path ) ); }
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
	 * @return array An array of script data, each containing the DOM node, src, and content.
	 */
	private static function identify_and_categorize_scripts() {
		$scripts = array();
		/**
		 * List of script types to ignore during optimization.
		 * These are typically data scripts, templates, or module definitions
		 * that should not be minified or combined as regular JavaScript.
		 */
		$ignore_types = array( 'module', 'importmap', 'application/ld+json', 'application/json', 'speculationrules', 'text/template', 'text/html', 'text/x-template', 'text/x-handlebars-template' );
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
