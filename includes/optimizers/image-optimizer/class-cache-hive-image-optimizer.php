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

		// Check if the image is excluded from optimization by URL.
		$url_exclusions = $settings['image_exclude_images'] ?? array();
		if ( ! empty( $url_exclusions ) ) {
			$attachment_url = \wp_get_attachment_url( $attachment_id );
			if ( $attachment_url ) {
				// Sanitize and check each rule.
				foreach ( $url_exclusions as $rule ) {
					$trimmed_rule = trim( $rule );
					if ( ! empty( $trimmed_rule ) && str_contains( $attachment_url, $trimmed_rule ) ) {
						return true; // Skip optimization for this image.
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
	 * Deletes all optimized files and metadata from the database.
	 *
	 * @since 1.0.0
	 * @return true|\WP_Error True on success, or a WP_Error object.
	 */
	public static function delete_all_data() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s", Cache_Hive_Image_Meta::META_KEY ) );

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

		\delete_option( Cache_Hive_Image_Stats::STATS_OPTION_KEY );
		\delete_option( Cache_Hive_Image_Stats::SYNC_STATE_OPTION_KEY );

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
}
