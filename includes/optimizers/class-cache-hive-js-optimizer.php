<?php
/**
 * JS Optimizer for Cache Hive - String-Based Engine
 *
 * This version uses a regex-based parsing engine to avoid the destructive
 * behavior of DOMDocument, ensuring modern JS templates and special script
 * types are not broken. It is powered by the matthiasmullie/minify library.
 *
 * @package Cache_Hive
 * @since   1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Vendor\MatthiasMullie\Minify;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * A robust JS optimizer that works directly on the HTML string for maximum compatibility.
 */
final class Cache_Hive_JS_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	private static $settings;

	/**
	 * The full path for creating new cache files.
	 *
	 * @var string
	 */
	private static $base_cache_path;

	/**
	 * Flag to track if the delay loader script needs to be injected.
	 *
	 * @var bool
	 */
	private static $loader_is_needed = false;

	/**
	 * Checks if any JS optimization is enabled.
	 *
	 * @param array $settings The plugin settings array.
	 * @return bool True if JS optimization is active.
	 */
	public static function is_enabled( array $settings ) {
		$defer_mode_enabled = isset( $settings['js_defer_mode'] ) && in_array( $settings['js_defer_mode'], array( 'delayed', 'deferred' ), true );
		return ! empty( $settings['js_minify'] ) || ! empty( $settings['js_combine'] ) || $defer_mode_enabled;
	}

	/**
	 * The main entry point for all JS string-based optimizations.
	 *
	 * @param string $html            The full HTML content.
	 * @param string $base_cache_path The full path to the cache directory.
	 * @param array  $settings        The plugin settings.
	 * @return string The optimized HTML content.
	 */
	public static function run_string_optimizations( $html, $base_cache_path, array $settings ) {
		self::$settings         = $settings;
		self::$base_cache_path  = $base_cache_path;
		self::$loader_is_needed = false; // Reset for each run.

		list( $combinable_scripts, $tags_to_remove, $html_replacements ) = self::parse_scripts_from_string( $html );

		// 1. First, apply individual modifications (defer/delay) to their original tags.
		if ( ! empty( $html_replacements ) ) {
			$html = str_replace( array_keys( $html_replacements ), array_values( $html_replacements ), $html );
		}

		// 2. Next, process the list of scripts to be combined.
		if ( ! empty( $combinable_scripts ) && ! empty( self::$settings['js_combine'] ) ) {
			$combined_script_tag = self::create_combined_file( $combinable_scripts );
			if ( $combined_script_tag ) {
				// Remove all original script tags that were combined.
				$html = str_replace( $tags_to_remove, '', $html );
				// Append the new combined script tag to the body.
				$html = self::append_to_body( $html, $combined_script_tag );
			}
		}

		// 3. Finally, inject the loader script if any scripts were marked for delay.
		if ( self::$loader_is_needed ) {
			$html = self::append_to_body( $html, self::get_loader_script_tag() );
		}

		return $html;
	}

	/**
	 * Parses all script tags from the HTML string using regular expressions.
	 *
	 * @param string $html The full HTML content.
	 * @return array [combinable_scripts, tags_to_remove, html_replacements]
	 */
	private static function parse_scripts_from_string( $html ) {
		$combinable_scripts = array();
		$tags_to_remove     = array();
		$html_replacements  = array();
		$combine_inline     = ! empty( self::$settings['js_combine_external_inline'] );
		$exclusions         = self::$settings['js_excludes'] ?? array();

		$scripts_regex = '/<script\b(?<attrs>[^>]*)>(?<content>[\s\S]*?)<\/script>/is';

		preg_match_all( $scripts_regex, $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$original_tag = $match[0];
			$attrs_string = $match['attrs'];
			$content      = $match['content'];

			$attrs = array();
			if ( preg_match_all( '/([\w-]+)\s*=\s*(["\'])(.*?)\2/is', $attrs_string, $attr_matches ) ) {
				$attrs = array_combine( array_map( 'strtolower', $attr_matches[1] ), $attr_matches[3] );
			}

			if ( isset( $attrs['data-optimized'] ) || isset( $attrs['data-no-optimize'] ) ) {
				continue;
			}
			$type = $attrs['type'] ?? 'text/javascript';
			if ( 'text/javascript' !== strtolower( $type ) && 'application/javascript' !== strtolower( $type ) ) {
				continue;
			}

			$src       = $attrs['src'] ?? null;
			$is_inline = is_null( $src );

			$is_exception = false;
			$path         = null;

			if ( self::is_string_excluded( $original_tag, $exclusions ) ) {
				$is_exception = true;
			} elseif ( $is_inline ) {
				if ( ! $combine_inline ) {
					$is_exception = true;
				}
			} else { // Is external.
				$path = self::get_file_path_from_url( $src );
				if ( ! $path || ! is_readable( $path ) ) {
					$is_exception = true;
				}
			}

			if ( $is_exception ) {
				$modified_tag = self::handle_individual_script_tag( $original_tag, $attrs, $is_inline );
				if ( $modified_tag !== $original_tag ) {
					$html_replacements[ $original_tag ] = $modified_tag;
				}
			} else {
				$combinable_scripts[] = array(
					'src'       => $src,
					'path'      => $is_inline ? null : $path,
					'content'   => $is_inline ? $content : null,
					'is_inline' => $is_inline,
				);
				$tags_to_remove[]     = $original_tag;
			}
		}

		return array( $combinable_scripts, $tags_to_remove, $html_replacements );
	}

	/**
	 * Creates a single combined JS file from a list of scripts.
	 *
	 * @param array $scripts List of scripts to combine.
	 * @return string|null The new <script> tag or null on failure.
	 */
	private static function create_combined_file( array $scripts ) {
		$minify_enabled = ! empty( self::$settings['js_minify'] );
		$delay_enabled  = 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' );
		$defer_enabled  = 'deferred' === ( self::$settings['js_defer_mode'] ?? 'default' );

		$final_js = '';
		foreach ( $scripts as $script ) {
			$content = $script['is_inline'] ? $script['content'] : file_get_contents( $script['path'] );

			if ( empty( $content ) ) {
				continue;
			}

			$should_minify = $minify_enabled;
			if ( ! $script['is_inline'] && self::is_min_file( $script['src'] ) ) {
				$should_minify = false;
			}

			if ( $should_minify ) {
				$minifier          = new Minify\JS( $content );
				$processed_content = $minifier->minify();
			} else {
				$processed_content = $content;
			}

			$final_js .= $processed_content . ";\n";
		}

		if ( empty( trim( $final_js ) ) ) {
			return null;
		}

		if ( $delay_enabled ) {
			$final_js = str_replace( "'DOMContentLoaded'", "'ch:domready'", $final_js );
			$final_js = str_replace( '"DOMContentLoaded"', '"ch:domready"', $final_js );
		}

		$file_url = self::create_external_file( $final_js, 'combined' );
		if ( ! $file_url ) {
			return null;
		}

		if ( $delay_enabled ) {
			self::$loader_is_needed = true;
			return '<script type="cache-hive/javascript" data-src="' . esc_attr( $file_url ) . '" data-optimized="true"></script>';
		}

		$tag = '<script src="' . esc_attr( $file_url ) . '" data-optimized="true"';
		if ( $defer_enabled ) {
			$tag .= ' defer';
		}
		$tag .= '></script>';

		return $tag;
	}

	/**
	 * Modifies an individual script tag for defer/delay.
	 *
	 * @param string $original_tag The original full <script> tag string.
	 * @param array  $attrs        The parsed attributes of the tag.
	 * @param bool   $is_inline    Whether the script is inline.
	 * @return string The modified tag.
	 */
	private static function handle_individual_script_tag( $original_tag, $attrs, $is_inline ) {
		if ( isset( $attrs['data-no-defer'] ) || self::is_string_excluded( $original_tag, self::$settings['js_defer_excludes'] ?? array() ) ) {
			return $original_tag;
		}

		$delay_enabled = 'delayed' === ( self::$settings['js_defer_mode'] ?? 'default' );

		if ( $delay_enabled ) {
			self::$loader_is_needed = true;
			$modified_tag           = preg_replace( '/<script/i', '<script type="cache-hive/javascript"', $original_tag, 1 );
			if ( ! $is_inline ) {
				$modified_tag = preg_replace( '/ src=/i', ' data-src=', $modified_tag, 1 );
			}
			return str_ireplace( array( ' defer', ' async' ), '', $modified_tag );
		}

		if ( ! empty( self::$settings['js_defer_mode'] ) && 'deferred' === self::$settings['js_defer_mode'] && false === stripos( $original_tag, 'defer' ) ) {
			return str_ireplace( '></script>', ' defer></script>', $original_tag );
		}

		return $original_tag;
	}

	/**
	 * Gets the minified loader script tag.
	 *
	 * @return string The full <script> tag for the loader.
	 */
	private static function get_loader_script_tag() {
		$loader_js = 'const chEvents=new Set(["pointerdown","pointermove","keydown","wheel","scroll"]),chLoader=()=>{chEvents.forEach(e=>window.removeEventListener(e,chLoader,{passive:!0}));const t=document.querySelectorAll(\'script[type="cache-hive/javascript"]\');let e=Promise.resolve();t.forEach(r=>{e=e.then(()=>new Promise(e=>{const t=document.createElement("script");for(const c of r.attributes){let e=c.name;"data-src"===e&&(e="src"),t.setAttribute(e,c.value)}t.src||!r.textContent||(t.textContent=r.textContent),t.type="text/javascript",t.onload=t.onerror=e,r.parentNode.replaceChild(t,r)}))}),e.then(()=>{document.dispatchEvent(new Event("ch:domready")),document.dispatchEvent(new Event("ch:scripts_loaded"))})};chEvents.forEach(e=>window.addEventListener(e,chLoader,{passive:!0}));';
		return '<script id="cache-hive-loader">' . $loader_js . '</script>';
	}

	/**
	 * Creates a new external JS file from a string of content.
	 *
	 * @param string $content     The JavaScript content to save.
	 * @param string $file_prefix A prefix for the filename.
	 * @return string|null The URL of the new file, or null on failure.
	 */
	private static function create_external_file( $content, $file_prefix ) {
		if ( empty( trim( $content ) ) ) {
			return null;
		}
		$path_info = pathinfo( self::$base_cache_path );
		$cache_dir = $path_info['dirname'];
		$hash      = substr( md5( $content ), 0, 8 );
		$filename  = "{$path_info['filename']}-{$file_prefix}-{$hash}.js";
		$file_path = "{$cache_dir}/{$filename}";
		if ( file_put_contents( $file_path, $content ) ) {
			return str_replace( WP_CONTENT_DIR, content_url(), $cache_dir ) . "/{$filename}";
		}
		return null;
	}

	/**
	 * Checks if a given string (a script tag) should be excluded.
	 *
	 * @param string $string_to_check The full script tag.
	 * @param array  $exclusions      The list of exclusion patterns.
	 * @return bool True if the script should be excluded.
	 */
	private static function is_string_excluded( $string_to_check, $exclusions ) {
		if ( empty( $exclusions ) ) {
			return false;
		}
		foreach ( (array) $exclusions as $pattern ) {
			if ( trim( $pattern ) && false !== stripos( $string_to_check, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks if a filename appears to be already minified.
	 *
	 * @param string $filename The URL or path of the script.
	 * @return bool True if the file contains .min. or -min.
	 */
	private static function is_min_file( $filename ) {
		if ( empty( $filename ) ) {
			return false;
		}
		$filename_without_qs = strtok( $filename, '?' );
		return (bool) preg_match( '/[-\.]min\.(?:[a-zA-Z]+)$/i', basename( $filename_without_qs ) );
	}

	/**
	 * Appends a string to the HTML body.
	 *
	 * @param string $html    The full HTML string.
	 * @param string $content The content to append.
	 * @return string The modified HTML string.
	 */
	private static function append_to_body( $html, $content ) {
		$count = 0;
		$html  = str_ireplace( '</body>', $content . '</body>', $html, $count );
		if ( 0 === $count ) {
			$html .= $content;
		}
		return $html;
	}

	/**
	 * Converts a URL into an absolute server file path, hardened against LFI.
	 *
	 * @param string $url The URL of the JS file.
	 * @return string|false The absolute file path or false on failure.
	 */
	private static function get_file_path_from_url( $url ) {
		// SECURITY FIX: Centralized and hardened path resolution logic.
		$url = strtok( $url, '?#' );
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}

		$site_url    = site_url();
		$content_url = content_url();
		$home_path   = rtrim( ABSPATH, '/' );
		$path        = '';

		if ( 0 === strpos( $url, $content_url ) ) {
			$path = str_replace( $content_url, rtrim( WP_CONTENT_DIR, '/' ), $url );
		} elseif ( 0 === strpos( $url, $site_url ) ) {
			$path = str_replace( $site_url, $home_path, $url );
		} elseif ( 0 === strpos( $url, '/' ) ) {
			$path = $home_path . $url;
		}

		if ( empty( $path ) ) {
			return false;
		}

		// SECURITY HARDENING: Prevent path traversal attacks (LFI).
		// 1. Normalize the path.
		$normalized_path = wp_normalize_path( $path );
		// 2. Check for directory traversal characters.
		if ( strpos( $normalized_path, '../' ) !== false || strpos( $normalized_path, '..\\' ) !== false ) {
			return false;
		}
		// 3. Resolve the real path and ensure it's within the WordPress installation.
		$real_path = realpath( $normalized_path );
		if ( false === $real_path || strpos( $real_path, wp_normalize_path( ABSPATH ) ) !== 0 ) {
			return false;
		}

		return $real_path;
	}
}
