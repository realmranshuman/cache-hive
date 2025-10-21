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
	 * @return true|'skipped'|\WP_Error True on success, 'skipped' if excluded, WP_Error on failure.
	 */
	public static function optimize_attachment( int $attachment_id ) {
		$settings = Cache_Hive_Settings::get_settings();

		// Check if the image is excluded from optimization by URL.
		$url_exclusions = $settings['image_exclude_images'] ?? array();
		if ( ! empty( $url_exclusions ) ) {
			$attachment_url = \wp_get_attachment_url( $attachment_id );
			if ( $attachment_url ) {
				// Sanitize and check each rule.
				foreach ( $url_exclusions as $rule ) {
					$trimmed_rule = trim( $rule );
					if ( ! empty( $trimmed_rule ) && str_contains( $attachment_url, $trimmed_rule ) ) {
						// Mark the image as excluded in the database so it's not picked up again.
						Cache_Hive_Image_Meta::update_status( $attachment_id, 'excluded' );
						return 'skipped';
					}
				}
			}
		}

		if ( ! \wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error( 'not_an_image', 'The provided ID is not an image.' );
		}

		$old_meta              = Cache_Hive_Image_Meta::get_meta( $attachment_id );
		$was_already_optimized = ( $old_meta && 'optimized' === $old_meta['status'] );

		Cache_Hive_Image_Meta::update_status( $attachment_id, 'in-progress' );

		$full_path   = \get_attached_file( $attachment_id );
		$meta        = \wp_get_attachment_metadata( $attachment_id );
		$image_sizes = isset( $meta['sizes'] ) ? $meta['sizes'] : array();

		$image_sizes['full'] = array( 'file' => basename( $full_path ) );

		$total_savings      = 0;
		$total_original     = 0;
		$optimization_error = null;

		foreach ( $image_sizes as $size => $data ) {
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

		if ( ! empty( $settings['image_auto_resize'] ) && false !== strpos( \get_attached_file( (int) \get_the_ID() ), basename( $file_path ) ) ) {
			self::resize_image( $file_path, intval( $settings['image_max_width'] ), intval( $settings['image_max_height'] ) );
		}

		if ( ! empty( $settings['image_remove_exif'] ) && 'imagemagick' === $library ) {
			self::remove_exif( $file_path );
		}

		$editor = \wp_get_image_editor( $file_path );
		if ( \is_wp_error( $editor ) ) {
			return $editor;
		}

		// Get supported formats directly from Imagick if available.
		$supported_formats = array();
		if ( 'imagemagick' === $library && \extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			try {
				$supported_formats = array_map( 'strtoupper', \Imagick::queryFormats() );
			} catch ( \Exception $e ) {
				// Could not query formats, proceed with WP default check.
			}
		}

		$quality = $settings['image_optimize_losslessly'] ? 100 : (int) $settings['image_quality'];
		$editor->set_quality( $quality );

		$next_gen_format = $settings['image_next_gen_format'] ?? 'webp';
		$optimized_path  = '';
		$mime_type       = '';
		$is_supported    = false;

		if ( 'webp' === $next_gen_format ) {
			$is_supported = ( ! empty( $supported_formats ) ) ? in_array( 'WEBP', $supported_formats, true ) : $editor->supports_mime_type( 'image/webp' );
			if ( $is_supported ) {
				$optimized_path = $file_path . '.webp';
				$mime_type      = 'image/webp';
			}
		} elseif ( 'avif' === $next_gen_format ) {
			$is_supported = ( ! empty( $supported_formats ) ) ? in_array( 'AVIF', $supported_formats, true ) : $editor->supports_mime_type( 'image/avif' );
			if ( $is_supported ) {
				$optimized_path = $file_path . '.avif';
				$mime_type      = 'image/avif';
			}
		}

		$savings = 0;
		if ( $optimized_path && $mime_type && $is_supported ) {
			$saved = $editor->save( $optimized_path, $mime_type );

			if ( \is_wp_error( $saved ) || ! file_exists( $saved['path'] ) || 0 === filesize( $saved['path'] ) ) {
				if ( isset( $saved['path'] ) && file_exists( $saved['path'] ) ) {
					@unlink( $saved['path'] );
				}
				$error_message = \is_wp_error( $saved ) ? $saved->get_error_message() : 'Generated file is empty or missing.';
				return new \WP_Error( 'image_save_error', $error_message );
			}

			$optimized_size  = filesize( $saved['path'] );
			$current_savings = $original_size - $optimized_size;

			if ( $current_savings < 0 ) {
				@unlink( $saved['path'] );
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
	 * Deletes all optimized files and metadata from the database in a scalable, cache-aware way.
	 *
	 * @since 1.0.0
	 * @return array|\WP_Error The new stats array on success, or a WP_Error object.
	 */
	public static function delete_all_data() {
		global $wpdb;

		$batch_size = 5000; // Process 5000 records at a time to prevent memory issues.

		// First, handle the metadata and cache invalidation in batches.
		while ( true ) {
			// 1. Get a batch of post IDs that have our metadata. This is fast and memory-efficient.
			$attachment_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d",
					Cache_Hive_Image_Meta::META_KEY,
					$batch_size
				)
			);

			// If no more IDs are found, we are done.
			if ( empty( $attachment_ids ) ) {
				break;
			}

			// 2. Loop through the batch and delete metadata and invalidate cache for each.
			// This is the WPCS-compliant way to handle bulk operations without dynamic IN clauses.
			foreach ( $attachment_ids as $attachment_id ) {
				// Delete metadata for the specific attachment.
				$wpdb->delete(
					$wpdb->postmeta,
					array(
						'post_id'  => $attachment_id,
						'meta_key' => Cache_Hive_Image_Meta::META_KEY,
					),
					array(
						'%d',
						'%s',
					)
				);

				// 3. CRITICAL: Invalidate the object cache for each post ID we just cleaned up.
				\wp_cache_delete( $attachment_id, 'post_meta' );
			}
		}

		// Second, handle file cleanup.
		$upload_dir = \wp_upload_dir();
		$path       = \trailingslashit( $upload_dir['basedir'] );

		try {
			$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && in_array( $file->getExtension(), array( 'webp', 'avif' ), true ) ) {
					$original_file = str_replace( '.' . $file->getExtension(), '', $file->getPathname() );
					if ( file_exists( $original_file ) ) {
						@unlink( $file->getPathname() );
					}
				}
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'file_scan_error', 'Could not scan uploads directory to delete files.' );
		}

		// Finally, clean up the global stats and options.
		\delete_option( Cache_Hive_Image_Stats::STATS_OPTION_KEY );
		\delete_option( Cache_Hive_Image_Stats::SYNC_STATE_OPTION_KEY );

		\wp_cache_delete( Cache_Hive_Image_Stats::STATS_OPTION_KEY, 'options' );
		\wp_cache_delete( Cache_Hive_Image_Stats::SYNC_STATE_OPTION_KEY, 'options' );

		// Recalculate and return the fresh stats.
		return Cache_Hive_Image_Stats::recalculate_stats();
	}


	/**
	 * Detects if the installed Imagick version has known transparency bugs with WebP.
	 *
	 * This check is based on known issues where specific versions of ImageMagick
	 * improperly handled the alpha channel when converting from PNG or GIF to WebP.
	 *
	 * @since 1.0.0
	 * @return bool True if the Imagick version is known to be problematic, false otherwise.
	 */
	public static function is_imagick_old() {
		if ( ! \extension_loaded( 'imagick' ) || ! class_exists( 'Imagick' ) ) {
			return false;
		}

		$v = \Imagick::getVersion();
		if ( ! isset( $v['versionString'] ) ) {
			return false; // Cannot determine version.
		}

		// Use preg_match to extract the version number, e.g., "ImageMagick 6.9.12-98 ...".
		if ( ! preg_match( '/ImageMagick (\d+\.\d+\.\d+)-?(\d+)?/', $v['versionString'], $matches ) ) {
			return false; // Could not parse version string.
		}

		$version = $matches[1];
		$patch   = $matches[2] ?? 0;

		// Bug Condition 1 (PNG->WebP alpha issues): Affects ImageMagick 6.9.10-0 to 6.9.12-40.
		// The bug was fixed in 6.9.12-41.
		if ( \version_compare( $version, '6.9.10', '>=' ) && \version_compare( $version, '6.9.12', '<' ) ) {
			return true;
		}
		if ( \version_compare( $version, '6.9.12', '==' ) && $patch < 41 ) {
			return true;
		}

		// Bug Condition 2 (GIF->WebP transparency issues): Affects early ImageMagick 7.1.0 builds.
		// The bug was fixed in 7.1.0-22.
		if ( \version_compare( $version, '7.1.0', '==' ) && $patch < 22 ) {
			return true;
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
	 * Gathers and returns all registered thumbnail sizes for the frontend.
	 *
	 * @since 1.1.0
	 * @return array A list of thumbnail sizes with their properties.
	 */
	public static function get_all_thumbnail_sizes(): array {
		$sizes_data         = array();
		$intermediate_sizes = \get_intermediate_image_sizes();

		foreach ( $intermediate_sizes as $size_name ) {
			// WordPress default sizes are stored in options.
			if ( in_array( $size_name, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ) {
				$width  = (int) \get_option( "{$size_name}_size_w" );
				$height = (int) \get_option( "{$size_name}_size_h" );
			} else {
				// Custom sizes are stored in a global variable.
				global $_wp_additional_image_sizes;
				if ( isset( $_wp_additional_image_sizes[ $size_name ] ) ) {
					$width  = (int) $_wp_additional_image_sizes[ $size_name ]['width'];
					$height = (int) $_wp_additional_image_sizes[ $size_name ]['height'];
				} else {
					// Skip if size details can't be found.
					continue;
				}
			}

			$sizes_data[] = array(
				'id'   => esc_attr( $size_name ),
				'name' => ucwords( str_replace( array( '_', '-' ), ' ', $size_name ) ),
				'size' => "{$width}x{$height}",
			);
		}
		return $sizes_data;
	}

	/**
	 * Gathers and returns the server's image processing capabilities.
	 *
	 * This is the definitive method for checking what the server supports and should be
	 * used to generate the `server_capabilities` object for the frontend API.
	 *
	 * @since 1.1.0
	 * @return array An array of server capabilities.
	 */
	public static function get_server_capabilities(): array {
		$capabilities = array(
			'gd_support'           => false,
			'gd_webp_support'      => false,
			'gd_avif_support'      => false,
			'imagick_support'      => false,
			'imagick_version'      => '',
			'imagick_webp_support' => false,
			'imagick_avif_support' => false,
			'is_imagick_old'       => false,
			'thumbnail_sizes'      => array(),
		);

		// 1. Check for GD support
		if ( extension_loaded( 'gd' ) && function_exists( 'gd_info' ) ) {
			$capabilities['gd_support'] = true;
			$gd_info                    = gd_info();
			if ( isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'] ) {
				$capabilities['gd_webp_support'] = true;
			}
			if ( isset( $gd_info['AVIF Support'] ) && $gd_info['AVIF Support'] ) {
				$capabilities['gd_avif_support'] = true;
			}
		}

		// 2. Check for Imagick support using the direct and reliable query method
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$capabilities['imagick_support'] = true;

			try {
				$version_info = \Imagick::getVersion();
				if ( isset( $version_info['versionString'] ) && preg_match( '/ImageMagick (\d+\.\d+\.\d+)/', $version_info['versionString'], $matches ) ) {
					$capabilities['imagick_version'] = $matches[1];
				}

				// Directly query supported formats. Format names are returned in uppercase.
				$supported_formats = \Imagick::queryFormats();

				if ( in_array( 'WEBP', $supported_formats, true ) ) {
					$capabilities['imagick_webp_support'] = true;
				}
				if ( in_array( 'AVIF', $supported_formats, true ) ) {
					$capabilities['imagick_avif_support'] = true;
				}
			} catch ( \Exception $e ) {
				// If Imagick throws an error, we mark it as not supported.
				$capabilities['imagick_support'] = false;
			}

			// Check for old Imagick version with transparency bugs.
			$capabilities['is_imagick_old'] = self::is_imagick_old();
		}

		// 3. Get all registered thumbnail sizes
		$capabilities['thumbnail_sizes'] = self::get_all_thumbnail_sizes();

		return $capabilities;
	}
}
