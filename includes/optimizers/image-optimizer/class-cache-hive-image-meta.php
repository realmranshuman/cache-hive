<?php
/**
 * Manages metadata for image optimization.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles reading, writing, and updating image optimization metadata.
 */
final class Cache_Hive_Image_Meta {

	/**
	 * The post meta key used to store all optimization data.
	 *
	 * @var string
	 */
	const META_KEY = '_cache_hive_image_meta';

	/**
	 * Get the full optimization meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 * @return array|false The meta array or false if not found.
	 */
	public static function get_meta( int $attachment_id ) {
		$meta = get_post_meta( $attachment_id, self::META_KEY, true );
		return is_array( $meta ) ? $meta : false;
	}

	/**
	 * Update the full optimization meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int   $attachment_id The attachment ID.
	 * @param array $meta The full meta array to save.
	 * @return bool True on success, false on failure.
	 */
	public static function update_meta( int $attachment_id, array $meta ): bool {
		return update_post_meta( $attachment_id, self::META_KEY, $meta );
	}

	/**
	 * Delete all optimization meta for an attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_meta( int $attachment_id ): bool {
		return delete_post_meta( $attachment_id, self::META_KEY );
	}

	/**
	 * Generate and store initial metadata for a new attachment.
	 *
	 * This sets the status to 'pending' and gathers information about all
	 * available thumbnail sizes for the attachment.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 * @return array The newly generated metadata array.
	 */
	public static function generate_meta( int $attachment_id ): array {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array();
		}

		$meta = array(
			'status'         => 'unoptimized', // unoptimized, in-progress, optimized, failed.
			'original_size'  => filesize( $file_path ),
			'optimized_size' => 0,
			'savings'        => 0,
			'error'          => '',
			'sizes'          => array(),
		);

		$attachment_meta = wp_get_attachment_metadata( $attachment_id );

		// Add original file.
		$meta['sizes']['full'] = array(
			'file'           => basename( $file_path ),
			'original_size'  => $meta['original_size'],
			'optimized_size' => 0,
			'webp_size'      => 0,
			'avif_size'      => 0,
			'status'         => 'pending',
		);

		// Add thumbnails.
		if ( ! empty( $attachment_meta['sizes'] ) ) {
			foreach ( $attachment_meta['sizes'] as $size => $size_data ) {
				$thumbnail_path = dirname( $file_path ) . '/' . $size_data['file'];
				if ( file_exists( $thumbnail_path ) ) {
					$meta['sizes'][ $size ] = array(
						'file'           => $size_data['file'],
						'original_size'  => filesize( $thumbnail_path ),
						'optimized_size' => 0,
						'webp_size'      => 0,
						'avif_size'      => 0,
						'status'         => 'pending',
					);
				}
			}
		}

		self::update_meta( $attachment_id, $meta );
		return $meta;
	}

	/**
	 * Check if an image is already optimized.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 * @return bool True if status is 'optimized'.
	 */
	public static function is_optimized( int $attachment_id ): bool {
		$meta = self::get_meta( $attachment_id );
		return isset( $meta['status'] ) && 'optimized' === $meta['status'];
	}

	/**
	 * Updates the status of an image.
	 *
	 * @since 1.0.0
	 * @param int    $attachment_id The attachment ID.
	 * @param string $status The new status (e.g., 'in-progress', 'optimized', 'failed').
	 */
	public static function update_status( int $attachment_id, string $status ) {
		$meta = self::get_meta( $attachment_id );
		if ( ! $meta ) {
			$meta = self::generate_meta( $attachment_id );
		}
		$meta['status'] = $status;
		self::update_meta( $attachment_id, $meta );
	}
}
