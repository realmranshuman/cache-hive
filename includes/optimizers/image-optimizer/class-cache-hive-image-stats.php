<?php
/**
 * Manages statistics and progress for image optimization using atomic counters.
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
 * Handles the calculation and atomic updating of optimization stats.
 */
final class Cache_Hive_Image_Stats {

	/**
	 * The option key for storing overall optimization statistics.
	 *
	 * @var string
	 */
	const STATS_OPTION_KEY = 'cache_hive_image_stats';

	/**
	 * The option key for storing the current sync/batch process state.
	 *
	 * @var string
	 */
	const SYNC_STATE_OPTION_KEY = 'cache_hive_image_sync_state';

	/**
	 * Gets the current statistics from the options table.
	 * If the option doesn't exist, it triggers a full recalculation.
	 *
	 * @since 1.0.0
	 * @param bool $force_recalculate Forces a recalculation from the database.
	 * @return array The statistics array.
	 */
	public static function get_stats( bool $force_recalculate = false ): array {
		if ( $force_recalculate ) {
			return self::recalculate_stats();
		}

		$stats = \get_option( self::STATS_OPTION_KEY );

		// If stats don't exist, this is likely the first run. Recalculate everything.
		if ( false === $stats ) {
			return self::recalculate_stats();
		}

		return \wp_parse_args(
			(array) $stats,
			array(
				'total_images'         => 0,
				'optimized_images'     => 0,
				'unoptimized_images'   => 0,
				'optimization_percent' => 0.0,
			)
		);
	}

	/**
	 * Increments the total image count.
	 *
	 * @since 1.0.0
	 */
	public static function increment_total_count() {
		$stats = self::get_stats();
		++$stats['total_images'];
		self::update_stats_from_array( $stats );
	}

	/**
	 * Decrements the total image count.
	 *
	 * @since 1.0.0
	 */
	public static function decrement_total_count() {
		$stats                 = self::get_stats();
		$stats['total_images'] = max( 0, $stats['total_images'] - 1 );
		self::update_stats_from_array( $stats );
	}

	/**
	 * Increments the optimized image count.
	 *
	 * @since 1.0.0
	 */
	public static function increment_optimized_count() {
		$stats = self::get_stats();
		++$stats['optimized_images'];
		self::update_stats_from_array( $stats );
	}

	/**
	 * Decrements the optimized image count.
	 *
	 * @since 1.0.0
	 */
	public static function decrement_optimized_count() {
		$stats                     = self::get_stats();
		$stats['optimized_images'] = max( 0, $stats['optimized_images'] - 1 );
		self::update_stats_from_array( $stats );
	}

	/**
	 * Recalculates all statistics from scratch. Resource-intensive.
	 *
	 * @since 1.0.0
	 * @return array The newly calculated statistics.
	 */
	public static function recalculate_stats(): array {
		global $wpdb;

		// ** START: CORRECTED TOTAL IMAGES QUERY **
		// This now matches the Media Library's logic by checking post_status.
		$total_images = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE %s AND post_status = 'inherit'",
				'image/%'
			)
		);
		// ** END: CORRECTED TOTAL IMAGES QUERY **

		// The meta value is a serialized PHP string, not JSON. The query must match the serialized format.
		$optimized_images = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND pm.meta_key = %s AND pm.meta_value LIKE %s",
				Cache_Hive_Image_Meta::META_KEY,
				'%' . $wpdb->esc_like( 's:6:"status";s:9:"optimized";' ) . '%'
			)
		);

		$stats = array(
			'total_images'     => $total_images,
			'optimized_images' => $optimized_images,
		);

		return self::update_stats_from_array( $stats );
	}

	/**
	 * A central helper to update the stats option from an array.
	 *
	 * @since 1.0.0
	 * @param array $stats The array with total_images and optimized_images.
	 * @return array The final, complete stats array that was saved.
	 */
	private static function update_stats_from_array( array $stats ): array {
		$total_images     = (int) ( $stats['total_images'] ?? 0 );
		$optimized_images = (int) ( $stats['optimized_images'] ?? 0 );

		$stats['unoptimized_images']   = max( 0, $total_images - $optimized_images );
		$stats['optimization_percent'] = ( $total_images > 0 ) ? ( $optimized_images / $total_images ) * 100 : 0.0;
		$stats['optimization_percent'] = round( $stats['optimization_percent'], 1 );

		\update_option( self::STATS_OPTION_KEY, $stats, 'no' );
		\wp_cache_delete( self::STATS_OPTION_KEY, 'options' );

		return $stats;
	}

	/**
	 * Starts a new manual sync process.
	 *
	 * @since 1.0.0
	 * @return array The initial state of the sync.
	 */
	public static function start_manual_sync(): array {
		$stats = self::recalculate_stats(); // Get the most accurate current count.

		$state = array(
			'method'            => 'manual',
			'total_to_optimize' => $stats['unoptimized_images'],
			'processed'         => 0,
			'is_finished'       => ( 0 === $stats['unoptimized_images'] ), // Immediately finish if there's nothing to do.
			'start_time'        => time(),
		);

		// Only save the state if a sync is actually starting.
		if ( ! $state['is_finished'] ) {
			\update_option( self::SYNC_STATE_OPTION_KEY, $state, 'no' );
		}

		return $state;
	}

	/**
	 * Gets the current state of the sync process.
	 *
	 * @since 1.0.0
	 * @return array|false The sync state array or false if not syncing.
	 */
	public static function get_sync_state() {
		return \get_option( self::SYNC_STATE_OPTION_KEY, false );
	}

	/**
	 * Clears the sync state and recalculates final stats.
	 *
	 * @since 1.0.0
	 */
	public static function clear_sync_state() {
		\delete_option( self::SYNC_STATE_OPTION_KEY );
		self::recalculate_stats();
	}

	/**
	 * Processes the next batch of images for a manual sync. This is the worker.
	 *
	 * @since 1.0.0
	 * @return array The updated sync state.
	 */
	public static function process_next_manual_batch(): array {
		$state = self::get_sync_state();
		if ( ! $state || ! empty( $state['is_finished'] ) ) {
			return $state ?: array( 'is_finished' => true );
		}

		$settings   = Cache_Hive_Settings::get_settings();
		$batch_size = (int) ( $settings['image_batch_size'] ?? 10 );

		$unoptimized_images_query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $batch_size,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => Cache_Hive_Image_Meta::META_KEY,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => Cache_Hive_Image_Meta::META_KEY,
						'value'   => 's:6:"status";s:9:"optimized";',
						'compare' => 'NOT LIKE',
					),
				),
			)
		);

		$attachment_ids = $unoptimized_images_query->posts;
		$found_count    = count( $attachment_ids );

		if ( ! empty( $attachment_ids ) ) {
			foreach ( $attachment_ids as $attachment_id ) {
				Cache_Hive_Image_Optimizer::optimize_attachment( $attachment_id );
				++$state['processed'];
			}
		}

		if ( $found_count < $batch_size ) {
			$state['is_finished'] = true;
			self::clear_sync_state();
		} else {
			\update_option( self::SYNC_STATE_OPTION_KEY, $state, 'no' );
		}

		return $state;
	}
}
