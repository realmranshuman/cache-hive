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
				$optimization_error = $result; // Store first error and continue.
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

			// ** START: ROBUST VERIFICATION **
			// Check if save returned an error, or if the file was not created or is empty.
			if ( \is_wp_error( $saved ) || ! file_exists( $saved['path'] ) || filesize( $saved['path'] ) === 0 ) {
				// Clean up empty file if it exists.
				if ( isset( $saved['path'] ) && file_exists( $saved['path'] ) ) {
					unlink( $saved['path'] );
				}
				$error_message = \is_wp_error( $saved ) ? $saved->get_error_message() : 'Generated file is empty or missing.';
				return new \WP_Error( 'image_save_error', $error_message );
			}
			// ** END: ROBUST VERIFICATION **

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
	 * Rewrites HTML image references (<img>, data-*, video poster, inline backgrounds)
	 * to deliver next-gen formats (e.g., WebP, AVIF) via <picture> elements and URL rewrites.
	 *
	 * @param string $html     The HTML buffer.
	 * @param array  $settings Plugin image optimization settings.
	 * @return string Modified HTML with next-gen format handling.
	 */
	public static function rewrite_html_with_picture_tags( string $html, array $settings ): string {
		$delivery_method = $settings['image_delivery_method'] ?? 'rewrite';
		$next_gen_format = $settings['image_next_gen_format'] ?? 'webp';

		if ( 'picture' !== $delivery_method || 'default' === $next_gen_format ) {
			return $html;
		}

		$site_url = \site_url();

		// ---------------------------------------------------------------------
		// 1️⃣ <img> → <picture> conversion
		// ---------------------------------------------------------------------
		$html = \preg_replace_callback(
			'/<img\s+([^>]*?)src=([\'"])([^\'"]+?)\.(jpe?g|png)\2([^>]*)>/i',
			function ( $m ) use ( $next_gen_format, $site_url ) {
				$original_tag = $m[0];
				$src          = $m[3] . '.' . $m[4];

				// Skip external or already next-gen URLs.
				if (
					( preg_match( '/\.(webp|avif)$/i', $src ) ) ||
					( strpos( $src, $site_url ) === false && strpos( $src, '/' ) !== 0 )
				) {
					return $original_tag;
				}

				// Rewrite srcset if present.
				$updated_tag = \preg_replace_callback(
					'/srcset=([\'"])([^\'"]+)\1/i',
					function ( $sm ) use ( $next_gen_format ) {
						$srcset = \preg_replace(
							'/\.(jpe?g|png)(\s+\d+[wx])?/',
							'.$1.' . $next_gen_format . '$2',
							$sm[2]
						);
						return 'srcset="' . esc_attr( $srcset ) . '"';
					},
					$original_tag
				);

				// Build <picture> with next-gen source.
				$next_gen_src = $src . '.' . $next_gen_format;
				$picture_tag  = '<picture>';
				$picture_tag .= '<source srcset="' . esc_attr( $next_gen_src ) . '" type="image/' . esc_attr( $next_gen_format ) . '">';
				$picture_tag .= $updated_tag;
				$picture_tag .= '</picture>';

				return $picture_tag;
			},
			$html
		);

		// ---------------------------------------------------------------------
		// 2️⃣ Rewrite data-* image attributes & <video poster>
		// ---------------------------------------------------------------------
		$data_attributes = array(
			'data-thumb',
			'data-src',
			'data-lazyload',
			'data-large_image',
			'data-retina_logo_url',
			'data-parallax-image',
			'data-vc-parallax-image',
			'poster',
		);

		foreach ( $data_attributes as $attr ) {
			$pattern = '/(' . preg_quote( $attr, '/' ) . ')=([\'"])([^\'"]+?)\.(jpe?g|png)\2/i';
			$html    = \preg_replace_callback(
				$pattern,
				function ( $m ) use ( $next_gen_format, $site_url ) {
					$url = $m[3] . '.' . $m[4];

					if (
						preg_match( '/\.(webp|avif)$/i', $url ) ||
						( strpos( $url, $site_url ) === false && strpos( $url, '/' ) !== 0 )
					) {
						return $m[0];
					}

					return sprintf(
						'%s="%s.%s"',
						$m[1],
						esc_attr( $m[3] . '.' . $m[4] ),
						esc_attr( $next_gen_format )
					);
				},
				$html
			);
		}

		// ---------------------------------------------------------------------
		// 3️⃣ Rewrite inline background-image URLs (style="background-image: url(...)")
		// ---------------------------------------------------------------------
		$html = \preg_replace_callback(
			'/background-image\s*:\s*url\((["\']?)([^)\'"]+?)\.(jpe?g|png)\1\)/i',
			function ( $m ) use ( $next_gen_format, $site_url ) {
				$url = $m[2] . '.' . $m[3];

				if (
					preg_match( '/\.(webp|avif)$/i', $url ) ||
					( strpos( $url, $site_url ) === false && strpos( $url, '/' ) !== 0 )
				) {
					return $m[0];
				}

				return 'background-image: url("' . esc_attr( $m[2] . '.' . $m[3] . '.' . $next_gen_format ) . '")';
			},
			$html
		);

		return $html;
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
}
