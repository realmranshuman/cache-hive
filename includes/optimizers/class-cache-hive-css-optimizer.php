<?php
/**
 * CSS optimizer for Cache Hive.
 *
 * This class uses a regex-based parsing engine to find CSS assets and the
 * matthiasmullie/minify library to process them, avoiding the use of
 * DOMDocument to ensure maximum compatibility.
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
 * Handles CSS minification, combination, and optimization using a string-based engine.
 */
final class Cache_Hive_CSS_Optimizer extends Cache_Hive_Base_Optimizer {

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
	 * Checks if any CSS optimization is enabled.
	 *
	 * @param array $settings The plugin settings array.
	 * @return bool True if CSS optimization is active.
	 */
	public static function is_enabled( array $settings ) {
		return ! empty( $settings['css_minify'] ) || ! empty( $settings['css_combine'] );
	}

	/**
	 * The main entry point for all CSS string-based optimizations.
	 *
	 * @param string $html            The full HTML content.
	 * @param string $base_cache_path The full path to the cache directory.
	 * @param array  $settings        The plugin settings.
	 * @return string The optimized HTML content.
	 */
	public static function run_string_optimizations( $html, $base_cache_path, array $settings ) {
		self::$settings        = $settings;
		self::$base_cache_path = $base_cache_path;

		list( $styles_to_combine, $tags_to_remove, $html_replacements ) = self::parse_styles_from_string( $html );

		// 1. First, apply individual modifications (minification in place).
		if ( ! empty( $html_replacements ) ) {
			$html = str_replace( array_keys( $html_replacements ), array_values( $html_replacements ), $html );
		}

		// 2. Next, process the combined styles list.
		if ( ! empty( $styles_to_combine ) && ! empty( self::$settings['css_combine'] ) ) {
			$combined_tags = self::create_combined_files( $styles_to_combine );
			if ( ! empty( $combined_tags ) ) {
				// Remove all original tags that were combined.
				$html = str_replace( $tags_to_remove, '', $html );
				// Inject the new combined <link> tags into the head.
				$html = self::inject_into_head( $html, implode( "\n", $combined_tags ) );
			}
		}

		return $html;
	}

	/**
	 * Parses all stylesheet links and inline style blocks from the HTML string.
	 *
	 * @param string $html The full HTML content.
	 * @return array [styles_to_combine, tags_to_remove, html_replacements]
	 */
	private static function parse_styles_from_string( $html ) {
		$styles_to_combine = array();
		$tags_to_remove    = array();
		$html_replacements = array();
		$combine_inline    = ! empty( self::$settings['css_combine_external_inline'] );
		$exclusions        = self::$settings['css_excludes'] ?? array();

		// Regex to find <link rel="stylesheet"> and <style> tags.
		$styles_regex = '#(?:<link\s+(?<link_attrs>[^>]+?)/?>)|(?:<style(?<style_attrs>[^>]*)>(?<style_content>[\s\S]*?)</style>)#is';

		preg_match_all( $styles_regex, $html, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$original_tag = $match[0];
			$is_link      = isset( $match['link_attrs'] ) && ! empty( $match['link_attrs'] );

			if ( $is_link ) {
				// --- Handle <link> tags ---
				$attrs_string = $match['link_attrs'];
				// Ensure it's a stylesheet.
				if ( false === stripos( $attrs_string, 'rel' ) || false === stripos( $attrs_string, 'stylesheet' ) ) {
					continue;
				}

				preg_match( '/href\s*=\s*[\'"]\s*?(?<url>[^\'"]+\.css(?:\?[^\'"]*)?)\s*?[\'"]/i', $attrs_string, $url_match );
				if ( empty( $url_match['url'] ) ) {
					continue;
				}
				$url = $url_match['url'];

				if ( self::is_string_excluded( $original_tag, $exclusions ) || self::is_remote_url( $url ) ) {
					// Don't process excluded or remote files, but respect font-display setting.
					self::handle_individual_tag( $original_tag, $html_replacements );
					continue;
				}

				// Add to combine list.
				$media                         = self::get_media_attribute( $attrs_string );
				$styles_to_combine[ $media ][] = array(
					'content'   => $url,
					'is_inline' => false,
				);
				$tags_to_remove[]              = $original_tag;

			} else {
				// --- Handle <style> tags ---
				$content = $match['style_content'];
				if ( empty( trim( $content ) ) ) {
					continue;
				}

				if ( self::is_string_excluded( $original_tag, $exclusions ) ) {
					continue;
				}

				if ( $combine_inline ) {
					// Add to combine list.
					$media                         = self::get_media_attribute( $match['style_attrs'] );
					$styles_to_combine[ $media ][] = array(
						'content'   => $content,
						'is_inline' => true,
					);
					$tags_to_remove[]              = $original_tag;
				} else {
					// Minify in place.
					self::handle_individual_tag( $original_tag, $html_replacements, true, $content );
				}
			}
		}
		return array( $styles_to_combine, $tags_to_remove, $html_replacements );
	}

	/**
	 * Creates combined CSS files for each media type.
	 *
	 * @param array $styles_by_media An array of styles grouped by media type.
	 * @return array An array of new <link> tags.
	 */
	private static function create_combined_files( array $styles_by_media ) {
		$new_link_tags = array();
		$path_info     = pathinfo( self::$base_cache_path );
		$cache_dir     = $path_info['dirname'];

		foreach ( $styles_by_media as $media => $sources ) {
			$minifier = new Minify\CSS();
			foreach ( $sources as $source ) {
				$minifier->add( $source['is_inline'] ? $source['content'] : self::get_file_path_from_url( $source['content'] ) );
			}

			// Define the final path for the combined file.
			$group_hash    = substr( md5( serialize( $sources ) ), 0, 8 );
			$filename      = "{$path_info['filename']}-{$media}-combined-{$group_hash}.css";
			$minified_path = "{$cache_dir}/{$filename}";

			// Let the library save the file. This triggers the path rewriting.
			$minified_content = $minifier->minify( $minified_path );

			if ( empty( $minified_content ) ) {
				continue;
			}

			// If font-display swap is enabled, we need to read the content back, modify it, and re-save.
			if ( 'swap' === ( self::$settings['css_font_optimization'] ?? 'default' ) ) {
				$content_with_swap = self::add_font_display_swap( $minified_content );
				file_put_contents( $minified_path, $content_with_swap );
			}

			$file_url = str_replace( WP_CONTENT_DIR, content_url(), $cache_dir ) . "/{$filename}";
			if ( $file_url ) {
				$new_link_tags[] = '<link rel="stylesheet" href="' . esc_attr( $file_url ) . '" media="' . esc_attr( $media ) . '" data-optimized="true">';
			}
		}
		return $new_link_tags;
	}

	/**
	 * Handles an individual CSS tag that will not be combined.
	 *
	 * @param string $original_tag      The original full tag string.
	 * @param array  &$html_replacements Reference to the replacements array.
	 * @param bool   $is_inline         Whether the tag is a <style> block.
	 * @param string $content           The content of the style block.
	 */
	private static function handle_individual_tag( $original_tag, &$html_replacements, $is_inline = false, $content = '' ) {
		$modified_css   = $is_inline ? $content : null;
		$minify_enabled = ! empty( self::$settings['css_minify'] );

		// Minify inline style blocks.
		if ( $is_inline && $minify_enabled ) {
			$modified_css = ( new Minify\CSS( $content ) )->minify();
		}

		// Apply font-display:swap to all non-excluded styles.
		if ( 'swap' === ( self::$settings['css_font_optimization'] ?? 'default' ) ) {
			if ( $is_inline ) {
				$modified_css = self::add_font_display_swap( $modified_css );
			}
		}

		if ( ! is_null( $modified_css ) && $modified_css !== $content ) {
			$new_tag                            = str_replace( $content, $modified_css, $original_tag );
			$html_replacements[ $original_tag ] = $new_tag;
		}
	}

	/**
	 * Extracts the media attribute value from a tag's attribute string.
	 *
	 * @param string $attrs_string The string of attributes.
	 * @return string The media value, defaulting to 'all'.
	 */
	private static function get_media_attribute( $attrs_string ) {
		if ( preg_match( '/media\s*=\s*["\']([^"\']+)["\']/i', $attrs_string, $media_match ) ) {
			return strtolower( $media_match[1] );
		}
		return 'all';
	}

	/**
	 * Injects content into the HTML <head>.
	 *
	 * @param string $html    The full HTML string.
	 * @param string $content The content to inject.
	 * @return string The modified HTML string.
	 */
	private static function inject_into_head( $html, $content ) {
		if ( strripos( $html, '</head>' ) !== false ) {
			return str_ireplace( '</head>', "\n" . $content . "\n</head>", $html );
		}
		return $html;
	}

	/**
	 * Checks if a string (a tag) should be excluded.
	 *
	 * @param string $string_to_check The full tag.
	 * @param array  $exclusions      The list of exclusion patterns.
	 * @return bool True if the tag should be excluded.
	 */
	private static function is_string_excluded( $string_to_check, $exclusions ) {
		if ( empty( $exclusions ) ) {
			return false;
		}
		foreach ( (array) $exclusions as $pattern ) {
			if ( trim( $pattern ) && false !== stripos( $string_to_check, trim( $pattern ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Adds or replaces font-display: swap in @font-face rules.
	 *
	 * This function finds all @font-face blocks, removes any pre-existing
	 * font-display property, and then appends `font-display: swap;` to ensure
	 * compliance with Lighthouse recommendations.
	 *
	 * @param string $css The CSS content.
	 * @return string The modified CSS content.
	 */
	private static function add_font_display_swap( $css ) {
		return preg_replace_callback(
			'/@font-face\s*\{(?<rules>[^}]*)\}/is',
			function ( $matches ) {
				$rules = $matches['rules'];

				// 1. Remove any existing font-display property.
				// This regex handles different spacing and quoting.
				$rules = preg_replace( '/font-display\s*:\s*[^;}\s]+;?/i', '', $rules );

				// 2. Add the desired font-display property.
				// We trim and add a semicolon to ensure clean output.
				$clean_rules = trim( $rules, " \t\n\r\0\x0B;" );
				$new_rules   = $clean_rules . ( $clean_rules ? ';' : '' ) . 'font-display:swap;';

				return '@font-face{' . $new_rules . '}';
			},
			(string) $css
		);
	}

	/**
	 * Converts a URL into an absolute server file path.
	 *
	 * @param string $url The URL of the CSS file.
	 * @return string|false The absolute file path or false if not possible.
	 */
	private static function get_file_path_from_url( $url ) {
		$url = strtok( $url, '?#' );
		if ( 0 === strpos( $url, '//' ) ) {
			$url = ( is_ssl() ? 'https:' : 'http:' ) . $url;
		}
		$site_url    = site_url();
		$content_url = content_url();
		$abs_path    = rtrim( ABSPATH, '/' );
		if ( 0 === strpos( $url, $content_url ) ) {
			return str_replace( $content_url, rtrim( WP_CONTENT_DIR, '/' ), $url );
		}
		if ( 0 === strpos( $url, $site_url ) ) {
			return str_replace( $site_url, $abs_path, $url );
		}
		if ( 0 === strpos( $url, '/' ) ) {
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
}
