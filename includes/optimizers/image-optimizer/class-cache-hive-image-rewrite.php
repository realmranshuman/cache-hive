<?php
/**
 * Handles HTML, CSS, and server rule rewriting for next-gen image delivery.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

use Cache_Hive\Includes\Cache_Hive_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages all rewriting tasks for the image optimizer.
 */
final class Cache_Hive_Image_Rewrite {

	/**
	 * Insert rewrite rules into the main .htaccess file.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function insert_rules(): bool {
		$htaccess_path = self::get_htaccess_path();
		if ( ! $htaccess_path || ! is_writable( $htaccess_path ) ) {
			return false;
		}

		$rules = self::get_rules();
		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		return \insert_with_markers( $htaccess_path, 'Cache Hive Image Optimizer', $rules );
	}

	/**
	 * Remove rewrite rules from the main .htaccess file.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function remove_rules(): bool {
		$htaccess_path = self::get_htaccess_path();
		if ( ! $htaccess_path || ! is_writable( $htaccess_path ) ) {
			return false;
		}

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		return \insert_with_markers( $htaccess_path, 'Cache Hive Image Optimizer', array() );
	}

	/**
	 * Gets the path to the main .htaccess file.
	 *
	 * @since 1.0.0
	 * @return string|false The path to the file, or false if not found.
	 */
	private static function get_htaccess_path() {
		return ABSPATH . '.htaccess';
	}

	/**
	 * Generates the array of rewrite rules.
	 *
	 * @since 1.0.0
	 * @return array An array of rule strings.
	 */
	private static function get_rules(): array {
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'',
			'# Serve AVIF if browser accepts it and file exists.',
			'RewriteCond %{HTTP_ACCEPT} image/avif',
			'RewriteCond %{REQUEST_FILENAME}.avif -f',
			'RewriteRule ^(.*)$ $1.avif [T=image/avif,L]',
			'',
			'# Serve WebP if browser accepts it and file exists.',
			'RewriteCond %{HTTP_ACCEPT} image/webp',
			'RewriteCond %{REQUEST_FILENAME}.webp -f',
			'RewriteRule ^(.*)$ $1.webp [T=image/webp,L]',
			'</IfModule>',
			'',
			'<IfModule mod_headers.c>',
			'# Add Vary: Accept header to notify proxies about content negotiation.',
			'<FilesMatch "\.(jpe?g|png)$">',
			'Header append Vary Accept',
			'</FilesMatch>',
			'</IfModule>',
		);
	}

	/**
	 * Rewrites HTML image references to use <picture> tags.
	 *
	 * @param string $html     The HTML buffer.
	 * @param array  $settings Plugin image optimization settings.
	 * @return string Modified HTML.
	 */
	public static function rewrite_html_with_picture_tags( string $html, array $settings ): string {
		$delivery_method = $settings['image_delivery_method'] ?? 'rewrite';
		$next_gen_format = $settings['image_next_gen_format'] ?? 'webp';

		if ( 'picture' !== $delivery_method || 'default' === $next_gen_format ) {
			return $html;
		}

		$html_no_pictures = self::_remove_existing_picture_tags( $html );

		if ( ! preg_match_all( '/<img\s[^>]+>/isU', $html_no_pictures, $matches ) ) {
			return $html;
		}

		$images_to_replace = array();

		foreach ( $matches[0] as $image_tag ) {
			$processed_image = self::_process_image_tag( $image_tag, $next_gen_format );

			if ( ! $processed_image ) {
				continue;
			}
			if ( self::_is_image_excluded( $image_tag, $processed_image['attributes'], $settings, $html ) ) {
				continue;
			}

			$images_to_replace[ $image_tag ] = self::_build_picture_html( $processed_image, $next_gen_format );
		}

		if ( empty( $images_to_replace ) ) {
			return $html;
		}

		return str_replace( array_keys( $images_to_replace ), array_values( $images_to_replace ), $html );
	}

	/**
	 * Rewrites CSS background-image URLs to next-gen formats.
	 *
	 * @param string $css      The CSS content.
	 * @param array  $settings Plugin image optimization settings.
	 * @return string The modified CSS.
	 */
	public static function rewrite_css_background_images( string $css, array $settings ): string {
		$delivery_method = $settings['image_delivery_method'] ?? 'rewrite';
		$next_gen_format = $settings['image_next_gen_format'] ?? 'webp';

		if ( 'picture' !== $delivery_method || 'default' === $next_gen_format ) {
			return $css;
		}

		$site_url = \site_url();

		return \preg_replace_callback(
			'/url\((["\']?)([^)\'"]+?)\.(jpe?g|png)\1\)/i',
			function ( $m ) use ( $next_gen_format, $site_url ) {
				$url = $m[2] . '.' . $m[3];

				if ( \preg_match( '/\.(webp|avif)$/i', $url ) || ( false === \strpos( $url, $site_url ) && 0 !== \strpos( $url, '/' ) ) ) {
					return $m[0];
				}

				$next_gen_url = self::get_next_gen_url_if_exists( $url, $next_gen_format );
				if ( ! $next_gen_url ) {
					return $m[0];
				}

				return 'url("' . \esc_attr( $next_gen_url ) . '")';
			},
			$css
		);
	}

	/**
	 * Checks if an image should be excluded from <picture> tag rewrite based on rules.
	 * This is the proven, working logic from the original implementation.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $image_tag  The full HTML <img> tag.
	 * @param array  $attributes An array of the image's parsed attributes.
	 * @param array  $settings   The plugin settings array.
	 * @param string $html       The full HTML content for context searching.
	 * @return bool True if the image should be excluded.
	 */
	private static function _is_image_excluded( string $image_tag, array $attributes, array $settings, string $html ): bool {
		$rules = $settings['image_exclude_picture_rewrite'] ?? array();

		if ( empty( $rules ) || ! is_array( $rules ) ) {
			return false;
		}

		// Clean up the array just in case there are empty values or whitespace.
		$rules = array_filter( array_map( 'trim', $rules ) );
		if ( empty( $rules ) ) {
			return false;
		}

		foreach ( $rules as $rule ) {
			// Rule: Parent Selector (e.g., 'div.profile-card', '.profile-card', 'header#main-nav', '#main-nav').
			if ( preg_match( '/^([a-zA-Z0-9_-]*)?([\.#])([a-zA-Z0-9_-]+)$/', $rule, $selector_parts ) ) {
				list( , $tag, $type, $name ) = $selector_parts;
				$attr                        = ( '.' === $type ) ? 'class' : 'id';
				$pos                         = strpos( $html, $image_tag );

				if ( false === $pos ) {
					continue;
				}

				$html_before = substr( $html, 0, $pos );
				$search_tag  = '<' . ( $tag ? $tag : '' ); // If tag is empty, searches for any tag '<'.

				$last_open_pos = strrpos( $html_before, $search_tag );

				if ( false !== $last_open_pos ) {
					// We found a potential parent. Now, validate it.
					$context = substr( $html_before, $last_open_pos );

					// 1. Get the actual tag name from what we found.
					preg_match( '/<([a-zA-Z0-9_-]+)/', $context, $tag_name_match );
					$actual_tag_name = $tag_name_match[1] ?? null;

					// 2. If a specific tag was required by the rule, ensure it matches.
					if ( $actual_tag_name && ! empty( $tag ) && $tag !== $actual_tag_name ) {
						continue; // The found tag doesn't match the rule's required tag.
					}

					// 3. CRITICAL: Check if this tag was closed before our image. If so, it's not a parent.
					if ( $actual_tag_name && false !== strpos( $context, '</' . $actual_tag_name . '>' ) ) {
						continue; // This tag was closed, so it's a sibling/uncle, not a parent.
					}

					// 4. Extract the full opening tag to check its attributes.
					preg_match( '/^<[^>]+>/', $context, $opening_tag_match );
					if ( ! empty( $opening_tag_match[0] ) ) {
						$pattern = ( 'class' === $attr )
							? '/class\s*=\s*(["\']).*\b' . preg_quote( $name, '/' ) . '\b.*?\1/'
							: '/id\s*=\s*(["\'])\s*' . preg_quote( $name, '/' ) . '\s*\1/';

						if ( preg_match( $pattern, $opening_tag_match[0] ) ) {
							return true; // Confirmed parent match, exclude.
						}
					}
				}
				continue; // This was a parent selector rule, done with it.
			}

			// Rule: Image's own Class.
			if ( '.' === $rule[0] ) {
				$class_to_find = substr( $rule, 1 );
				if ( ! empty( $attributes['class'] ) && preg_match( '/\b' . preg_quote( $class_to_find, '/' ) . '\b/', $attributes['class'] ) ) {
					return true;
				}
				continue;
			}

			// Rule: Image's own ID.
			if ( '#' === $rule[0] ) {
				$id_to_find = substr( $rule, 1 );
				if ( ! empty( $attributes['id'] ) && $id_to_find === $attributes['id'] ) {
					return true;
				}
				continue;
			}
		}

		return false;
	}


	/**
	 * Removes pre-existing <picture> tags from the HTML.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $html The HTML content.
	 * @return string The HTML content with <picture> tags removed.
	 */
	private static function _remove_existing_picture_tags( string $html ): string {
		if ( false === strpos( $html, '<picture' ) ) {
			return $html;
		}
		return preg_replace( '#<picture[^>]*>.*?<source[^>]*>.*?(<img[^>]*>).*?</picture\s*>#mis', '$1', $html );
	}

	/**
	 * Process a single <img> tag string and convert it into a structured array.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $image_tag       The full HTML <img> tag.
	 * @param string $next_gen_format The target format.
	 * @return array|false A structured array of image data or false.
	 */
	private static function _process_image_tag( string $image_tag, string $next_gen_format ) {
		if ( ! preg_match_all( '/(?<name>[a-zA-Z0-9_-]+)=([\'"])(?<value>.*?)\2/is', $image_tag, $attr_matches, PREG_SET_ORDER ) ) {
			return false;
		}

		$attributes = array();
		foreach ( $attr_matches as $match ) {
			$attributes[ strtolower( $match['name'] ) ] = $match['value'];
		}

		$src_attribute_name = ! empty( $attributes['data-src'] ) ? 'data-src' : ( ! empty( $attributes['src'] ) ? 'src' : '' );
		if ( ! $src_attribute_name || ! preg_match( '/\.(jpe?g|png|gif)$/i', $attributes[ $src_attribute_name ] ) ) {
			return false;
		}

		$next_gen_src = self::get_next_gen_url_if_exists( $attributes[ $src_attribute_name ], $next_gen_format );
		if ( ! $next_gen_src ) {
			return false;
		}

		$image_data = array(
			'original_tag'     => $image_tag,
			'attributes'       => $attributes,
			'src'              => $attributes[ $src_attribute_name ],
			'next_gen_src'     => $next_gen_src,
			'srcset_attribute' => '',
			'srcset_sources'   => array(),
		);

		$srcset_attribute_name = ! empty( $attributes['data-srcset'] ) ? 'data-srcset' : ( ! empty( $attributes['srcset'] ) ? 'srcset' : '' );
		if ( $srcset_attribute_name ) {
			$image_data['srcset_attribute'] = $srcset_attribute_name;
			$sources                        = explode( ',', $attributes[ $srcset_attribute_name ] );
			foreach ( $sources as $source ) {
				$parts = preg_split( '/\s+/', trim( $source ) );
				if ( ! empty( $parts[0] ) ) {
					$url          = $parts[0];
					$descriptor   = $parts[1] ?? '';
					$next_gen_url = self::get_next_gen_url_if_exists( $url, $next_gen_format );
					if ( $next_gen_url ) {
						$image_data['srcset_sources'][] = array(
							'descriptor'   => $descriptor,
							'next_gen_url' => $next_gen_url,
						);
					}
				}
			}
		}
		return $image_data;
	}

	/**
	 * Build the complete <picture>...</picture> HTML.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array  $image_data      The structured array from _process_image_tag().
	 * @param string $next_gen_format The target format.
	 * @return string The final HTML for the <picture> element.
	 */
	private static function _build_picture_html( array $image_data, string $next_gen_format ): string {
		$picture_attributes = array();
		$img_tag_modified   = $image_data['original_tag'];
		$attributes_to_move = array( 'class', 'style', 'id' );

		foreach ( $image_data['attributes'] as $name => $value ) {
			if ( in_array( $name, $attributes_to_move, true ) || 0 === strpos( $name, 'data-' ) ) {
				$picture_attributes[ $name ] = $value;
				$attribute_pattern           = '/\s+' . preg_quote( $name, '/' ) . '=([\'"])' . preg_quote( $value, '/' ) . '\1/';
				$img_tag_modified            = preg_replace( $attribute_pattern, '', $img_tag_modified );
			}
		}

		$next_gen_srcset = array();
		if ( ! empty( $image_data['srcset_sources'] ) ) {
			foreach ( $image_data['srcset_sources'] as $source ) {
				$next_gen_srcset[] = $source['next_gen_url'] . ( $source['descriptor'] ? ' ' . $source['descriptor'] : '' );
			}
		} else {
			$next_gen_srcset[] = $image_data['next_gen_src'];
		}

		$srcset_key                       = ! empty( $image_data['srcset_attribute'] ) ? $image_data['srcset_attribute'] : 'srcset';
		$source_attributes                = array( 'type' => 'image/' . $next_gen_format );
		$source_attributes[ $srcset_key ] = implode( ', ', $next_gen_srcset );

		if ( ! empty( $image_data['attributes']['sizes'] ) ) {
			$source_attributes['sizes'] = $image_data['attributes']['sizes'];
		}

		$picture_html  = '<picture' . self::_build_attributes_string( $picture_attributes ) . '>';
		$picture_html .= '<source' . self::_build_attributes_string( $source_attributes ) . '>';
		$picture_html .= $img_tag_modified;
		$picture_html .= '</picture>';

		return $picture_html;
	}

	/**
	 * Helper to convert an associative array of attributes to an HTML string.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $attributes An array of HTML attributes.
	 * @return string The formatted string of HTML attributes.
	 */
	private static function _build_attributes_string( array $attributes ): string {
		$string = '';
		if ( empty( $attributes ) ) {
			return $string;
		}
		foreach ( $attributes as $name => $value ) {
			$string .= ' ' . $name . '="' . \esc_attr( $value ) . '"';
		}
		return $string;
	}

	/**
	 * Converts a local WordPress URL to an absolute server file path.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $url The URL to convert.
	 * @return string|null The absolute file path or null if conversion fails.
	 */
	private static function url_to_path( string $url ): ?string {
		static $uploads_url, $uploads_dir, $content_url, $content_dir, $site_url, $site_dir;

		if ( is_null( $uploads_url ) ) {
			$uploads     = wp_upload_dir();
			$uploads_url = set_url_scheme( $uploads['baseurl'] );
			$uploads_dir = $uploads['basedir'];
			$content_url = set_url_scheme( content_url() );
			$content_dir = WP_CONTENT_DIR;
			$site_url    = set_url_scheme( site_url() );
			$site_dir    = ABSPATH;
		}

		$url_no_query = strtok( $url, '?' );
		$url_scheme   = set_url_scheme( $url_no_query );

		if ( 0 === stripos( $url_scheme, $uploads_url ) ) {
			return str_ireplace( $uploads_url, $uploads_dir, $url_no_query );
		}
		if ( 0 === stripos( $url_scheme, $content_url ) ) {
			return str_ireplace( $content_url, $content_dir, $url_no_query );
		}
		if ( 0 === stripos( $url_scheme, $site_url ) ) {
			return str_ireplace( $site_url, $site_dir, $url_no_query );
		}

		return null;
	}

	/**
	 * Checks if a next-gen version of an image exists and returns its URL.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param string $original_url    The URL of the original image.
	 * @param string $next_gen_format The desired next-gen format.
	 * @return string|null The URL of the next-gen image if it exists, otherwise null.
	 */
	private static function get_next_gen_url_if_exists( string $original_url, string $next_gen_format ): ?string {
		$file_path = self::url_to_path( $original_url );

		if ( ! $file_path || false !== strpos( $file_path, '..' ) ) {
			return null;
		}

		$next_gen_file_path = $file_path . '.' . $next_gen_format;

		if ( file_exists( $next_gen_file_path ) ) {
			return $original_url . '.' . $next_gen_format;
		}

		return null;
	}
}
