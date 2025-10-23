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
	 * @since 1.0.0
	 * @param int $attachment_id The attachment ID.
	 * @return array The newly generated metadata array.
	 */
	public static function generate_meta( int $attachment_id ): array {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array();
		}

		$base_format_meta = array(
			'status'  => 'pending',
			'savings' => 0,
		);

		$meta = array(
			'original_size' => filesize( $file_path ),
			'webp'          => $base_format_meta,
			'avif'          => $base_format_meta,
		);

		self::update_meta( $attachment_id, $meta );
		return $meta;
	}

	/**
	 * Check if an image is already optimized for a specific format.
	 *
	 * @since 1.2.0
	 * @param int    $attachment_id The attachment ID.
	 * @param string $format The format to check ('webp' or 'avif').
	 * @return bool True if status is 'optimized'.
	 */
	public static function is_optimized( int $attachment_id, string $format ): bool {
		$meta = self::get_meta( $attachment_id );
		return isset( $meta[ $format ]['status'] ) && 'optimized' === $meta[ $format ]['status'];
	}

	/**
	 * Updates the status of an image for a specific format.
	 *
	 * @since 1.2.0
	 * @param int    $attachment_id The attachment ID.
	 * @param string $format The format to update ('webp' or 'avif').
	 * @param string $status The new status (e.g., 'in-progress', 'optimized', 'failed').
	 * @param array  $data Optional data to merge into the format's meta.
	 */
	public static function update_format_status( int $attachment_id, string $format, string $status, array $data = array() ) {
		if ( 'webp' !== $format && 'avif' !== $format ) {
			return;
		}

		$meta = self::get_meta( $attachment_id );
		if ( ! $meta ) {
			$meta = self::generate_meta( $attachment_id );
		}

		// Ensure the format key exists.
		if ( ! isset( $meta[ $format ] ) ) {
			$meta[ $format ] = array(
				'status'  => 'pending',
				'savings' => 0,
			);
		}

		$meta[ $format ]['status'] = $status;
		$meta[ $format ]           = array_merge( $meta[ $format ], $data );

		self::update_meta( $attachment_id, $meta );
	}
}
