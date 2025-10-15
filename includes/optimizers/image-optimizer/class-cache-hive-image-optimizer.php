<?php
/**
 * Image optimizer for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

use Cache_Hive\Includes\Cache_Hive_Base_Optimizer;
use Cache_Hive\Includes\Cache_Hive_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all image optimization logic for Cache Hive.
 */
class Cache_Hive_Image_Optimizer extends Cache_Hive_Base_Optimizer {

	/**
	 * Main entry point to optimize a single WordPress attachment and its thumbnails.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The ID of the attachment to optimize.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function optimize_attachment( int $attachment_id ) {
		$settings = Cache_Hive_Settings::get_settings();

		if ( ! \wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error( 'not_an_image', 'The provided ID is not an image.' );
		}

		$old_meta              = Cache_Hive_Image_Meta::get_meta( $attachment_id );
		$was_already_optimized = ( $old_meta && 'optimized' === $old_meta['status'] );

		// Set status to in-progress.
		Cache_Hive_Image_Meta::update_status( $attachment_id, 'in-progress' );

		$full_path   = \get_attached_file( $attachment_id );
		$meta        = \wp_get_attachment_metadata( $attachment_id );
		$image_sizes = isset( $meta['sizes'] ) ? $meta['sizes'] : array();

		// Add the original image to the list of sizes to process.
		$image_sizes['full'] = array( 'file' => basename( $full_path ) );

		$total_savings      = 0;
		$total_original     = 0;
		$optimization_error = null;

		foreach ( $image_sizes as $size => $data ) {
			// Check if this size should be optimized.
			if ( 'full' !== $size && ! in_array( $size, $settings['image_selected_thumbnails'] ?? array(), true ) ) {
				continue;
			}
			if ( 'full' === $size && empty( $settings['image_optimize_original'] ) ) {
				continue;
			}

			$file_path = dirname( $full_path ) . '/' . $data['file'];
			$result    = self::optimize_single_file( $file_path, $settings );

			if ( \is_wp_error( $result ) ) {
				$optimization_error = $result;
				continue;
			}

			$total_original += $result['original_size'];
			$total_savings  += $result['savings'];
		}

		// Finalize metadata.
		$final_meta = Cache_Hive_Image_Meta::get_meta( $attachment_id );
		if ( ! $final_meta ) {
			$final_meta = Cache_Hive_Image_Meta::generate_meta( $attachment_id );
		}

		if ( $optimization_error ) {
			$final_meta['status'] = 'failed';
			$final_meta['error']  = $optimization_error->get_error_message();
		} else {
			$final_meta['status']         = 'optimized';
			$final_meta['original_size']  = $total_original;
			$final_meta['optimized_size'] = $total_original - $total_savings;
			$final_meta['savings']        = $total_savings;
		}
		Cache_Hive_Image_Meta::update_meta( $attachment_id, $final_meta );

		// If the status changed to 'optimized', increment the counter.
		if ( ! $was_already_optimized && 'optimized' === $final_meta['status'] ) {
			Cache_Hive_Image_Stats::increment_optimized_count();
		}

		return $optimization_error ? $optimization_error : true;
	}

	/**
	 * Optimizes a single image file based on plugin settings.
	 *
	 * @since 1.0.0
	 * @param string $file_path The absolute path to the image file.
	 * @param array  $settings  The plugin settings.
	 * @return array|\WP_Error An array with optimization results or WP_Error on failure.
	 */
	public static function optimize_single_file( string $file_path, array $settings ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', 'Image file does not exist.' );
		}

		$original_size = filesize( $file_path );
		$library       = $settings['image_optimization_library'];

		// Auto-resize original image if enabled.
		if ( ! empty( $settings['image_auto_resize'] ) && false !== strpos( \get_attached_file( (int) \get_the_ID() ), basename( $file_path ) ) ) {
			self::resize_image( $file_path, intval( $settings['image_max_width'] ), intval( $settings['image_max_height'] ) );
		}

		// Remove EXIF if enabled.
		if ( ! empty( $settings['image_remove_exif'] ) && 'imagemagick' === $library ) {
			self::remove_exif( $file_path );
		}

		$editor = \wp_get_image_editor( $file_path );
		if ( \is_wp_error( $editor ) ) {
			return $editor;
		}

		$quality = $settings['image_optimize_losslessly'] ? 100 : (int) $settings['image_quality'];
		$editor->set_quality( $quality );

		$next_gen_format = $settings['image_next_gen_format'];
		$optimized_path  = '';
		$mime_type       = '';

		if ( 'webp' === $next_gen_format && $editor->supports_mime_type( 'image/webp' ) ) {
			$optimized_path = $file_path . '.webp';
			$mime_type      = 'image/webp';
		} elseif ( 'avif' === $next_gen_format && $editor->supports_mime_type( 'image/avif' ) ) {
			$optimized_path = $file_path . '.avif';
			$mime_type      = 'image/avif';
		}

		$savings = 0;
		if ( $optimized_path && $mime_type ) {
			$saved = $editor->save( $optimized_path, $mime_type );

			if ( \is_wp_error( $saved ) || ! file_exists( $saved['path'] ) || 0 === filesize( $saved['path'] ) ) {
				if ( isset( $saved['path'] ) && file_exists( $saved['path'] ) ) {
					unlink( $saved['path'] );
				}
				$error_message = \is_wp_error( $saved ) ? $saved->get_error_message() : 'Generated file is empty or missing.';
				return new \WP_Error( 'image_save_error', $error_message );
			}

			$optimized_size  = filesize( $saved['path'] );
			$current_savings = $original_size - $optimized_size;

			// If the optimized version is larger, delete it.
			if ( $current_savings < 0 ) {
				unlink( $saved['path'] );
			} else {
				$savings = $current_savings;
			}
		}

		return array(
			'original_size' => $original_size,
			'savings'       => $savings,
		);
	}

	/**
	 * Hooks into `add_attachment` to optimize new uploads automatically.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The ID of the new attachment.
	 */
	public static function auto_optimize_on_upload( int $attachment_id ) {
		if ( ! Cache_Hive_Settings::get( 'image_batch_processing', false ) ) {
			// Only optimize if not using batch processing.
			self::optimize_attachment( $attachment_id );
		}
	}

	/**
	 * Hooks into `delete_attachment` to clean up optimized files and metadata.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The ID of the attachment being deleted.
	 */
	public static function cleanup_on_delete( int $attachment_id ) {
		$meta = \wp_get_attachment_metadata( $attachment_id );

		$full_path = \get_attached_file( $attachment_id );
		if ( ! $full_path ) {
			return;
		}

		$files_to_delete = array(
			$full_path . '.webp',
			$full_path . '.avif',
		);

		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size_data ) {
				$thumb_path        = dirname( $full_path ) . '/' . $size_data['file'];
				$files_to_delete[] = $thumb_path . '.webp';
				$files_to_delete[] = $thumb_path . '.avif';
			}
		}

		foreach ( $files_to_delete as $file ) {
			if ( file_exists( $file ) ) {
				@unlink( $file );
			}
		}

		Cache_Hive_Image_Meta::delete_meta( $attachment_id );
	}

	/**
	 * Converts a local WordPress URL to an absolute server file path.
	 *
	 * @since 1.0.0
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
	 * Checks if an image should be excluded based on a set of rules.
	 * This works backwards from the image's position
	 * to accurately find its ancestors instead of scanning the entire document.
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
		$exclusions = $settings['image_exclude_images'] ?? '';
		if ( empty( $exclusions ) ) {
			return false;
		}

		$rules = array_filter( array_map( 'trim', explode( "\n", $exclusions ) ) );
		if ( empty( $rules ) ) {
			return false;
		}

		$image_src = $attributes['src'] ?? '';

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
				$search_tag  = '<' . ( $tag ?? '' ); // If tag is empty, searches for any tag '<'.

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

			// Rule: Partial or Full URL.
			if ( false !== strpos( $image_src, $rule ) ) {
				return true;
			}
		}

		return false;
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
	 * Deletes all optimized files and metadata from the database.
	 *
	 * @since 1.0.0
	 * @return true|\WP_Error True on success, or a WP_Error object.
	 */
	public static function delete_all_data() {
		global $wpdb;

		// 1. Delete all metadata.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", Cache_Hive_Image_Meta::META_KEY ) );

		// 2. Delete all generated files. This is more complex and best done with a scan.
		$upload_dir = \wp_upload_dir();
		$path       = \trailingslashit( $upload_dir['basedir'] );

		try {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && in_array( $file->getExtension(), array( 'webp', 'avif' ), true ) ) {
					// To be safe, check if the original file exists before deleting.
					$original_file = str_replace( '.' . $file->getExtension(), '', $file->getPathname() );
					if ( file_exists( $original_file ) ) {
						@unlink( $file->getPathname() );
					}
				}
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'file_scan_error', 'Could not scan uploads directory to delete files.' );
		}

		// 3. Delete stats options.
		\delete_option( Cache_Hive_Image_Stats::STATS_OPTION_KEY );
		\delete_option( Cache_Hive_Image_Stats::SYNC_STATE_OPTION_KEY );

		// 4. Corrected: Bust the object cache for options.
		\wp_cache_delete( Cache_Hive_Image_Stats::STATS_OPTION_KEY, 'options' );
		\wp_cache_delete( Cache_Hive_Image_Stats::SYNC_STATE_OPTION_KEY, 'options' );

		Cache_Hive_Image_Stats::recalculate_stats();

		return true;
	}

	/**
	 * Detects if Imagick is old (< 7.x) for PNG/GIF safety.
	 *
	 * @return bool
	 */
	public static function is_imagick_old() {
		if ( \extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$v = \Imagick::getVersion();
			if ( isset( $v['versionString'] ) && preg_match( '/ImageMagick ([0-6])\./', $v['versionString'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove EXIF/ICC metadata from an image (Imagick only).
	 *
	 * @param string $file_path The path to the image file.
	 * @return bool True on success, false on failure.
	 */
	public static function remove_exif( string $file_path ): bool {
		try {
			$image    = new \Imagick( $file_path );
			$profiles = $image->getImageProfiles( 'icc', true );
			$image->stripImage();
			if ( ! empty( $profiles['icc'] ) ) {
				$image->profileImage( 'icc', $profiles['icc'] );
			}
			$result = $image->writeImage( $file_path );
			$image->clear();
			$image->destroy();
			return $result;
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Resize an image file if it exceeds max dimensions.
	 *
	 * @param string $file_path  The path to the image file.
	 * @param int    $max_width  The maximum allowed width.
	 * @param int    $max_height The maximum allowed height.
	 * @return bool True if resized, false otherwise.
	 */
	public static function resize_image( string $file_path, int $max_width, int $max_height ): bool {
		$editor = \wp_get_image_editor( $file_path );
		if ( \is_wp_error( $editor ) ) {
			return false;
		}

		$size = $editor->get_size();
		if ( $size['width'] <= $max_width && $size['height'] <= $max_height ) {
			return false;
		}

		$editor->resize( $max_width, $max_height, false );
		$saved = $editor->save( $file_path );

		return ! \is_wp_error( $saved );
	}

	/**
	 * Rewrites CSS background-image URLs to next-gen formats (WebP/AVIF).
	 *
	 * @param string $css      The CSS content.
	 * @param array  $settings Plugin image optimization settings.
	 * @return string The modified CSS with rewritten background-image URLs.
	 */
	public static function rewrite_css_background_images( string $css, array $settings ): string {
		$delivery_method = $settings['image_delivery_method'] ?? 'rewrite';
		$next_gen_format = $settings['image_next_gen_format'] ?? 'webp';

		// Only apply when using picture/rewrite and next-gen formats.
		if ( 'picture' !== $delivery_method || 'default' === $next_gen_format ) {
			return $css;
		}

		$site_url = \site_url();

		// Match background URLs like:
		// url(image.jpg)
		// url('image.png')
		// url("https://example.com/image.jpg").
		$css = \preg_replace_callback(
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

		return $css;
	}
}
