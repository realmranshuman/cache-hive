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
 * Handles the calculation and updating of optimization stats.
 */
final class Cache_Hive_Image_Stats {

	const STATS_OPTION_KEY      = 'cache_hive_image_stats';
	const SYNC_STATE_OPTION_KEY = 'cache_hive_image_sync_state';

	/**
	 * Gets the current statistics. If stats are old or missing, triggers a recalculation.
	 *
	 * @since 1.0.0
	 * @param bool $force_recalculate Forces a recalculation from the database.
	 * @return array The statistics array.
	 */
	public static function get_stats( bool $force_recalculate = false ): array {
		$stats = \get_option( self::STATS_OPTION_KEY );

		if ( ! $force_recalculate && is_array( $stats ) && isset( $stats['webp'] ) ) {
			return $stats;
		}

		return self::recalculate_stats();
	}

	/**
	 * Increments the optimized image count for a specific format.
	 *
	 * @since 1.2.0
	 * @param string $format The format ('webp' or 'avif').
	 * @param int    $savings The savings in bytes for this optimization.
	 */
	public static function increment_optimized_count( string $format, int $savings ) {
		if ( 'webp' !== $format && 'avif' !== $format ) {
			return;
		}
		$stats = self::get_stats();
		++$stats[ $format ]['optimized_count'];
		$stats[ $format ]['savings'] += $savings;
		self::update_stats( $stats );
	}

	/**
	 * Decrements the optimized image count for a specific format.
	 *
	 * @since 1.2.0
	 * @param string $format The format ('webp' or 'avif').
	 * @param int    $savings The savings in bytes that are being reverted.
	 */
	public static function decrement_optimized_count( string $format, int $savings ) {
		if ( 'webp' !== $format && 'avif' !== $format ) {
			return;
		}
		$stats                               = self::get_stats();
		$stats[ $format ]['optimized_count'] = max( 0, $stats[ $format ]['optimized_count'] - 1 );
		$stats[ $format ]['savings']         = max( 0, $stats[ $format ]['savings'] - $savings );
		self::update_stats( $stats );
	}

	/**
	 * Recalculates all statistics from scratch. Resource-intensive.
	 *
	 * @since 1.0.0
	 * @return array The newly calculated statistics.
	 */
	public static function recalculate_stats(): array {
		global $wpdb;

		$total_images = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')" );

		$all_meta = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", Cache_Hive_Image_Meta::META_KEY ) );

		$new_stats = array(
			'total_images' => $total_images,
			'webp'         => array(
				'optimized_count' => 0,
				'savings'         => 0,
			),
			'avif'         => array(
				'optimized_count' => 0,
				'savings'         => 0,
			),
		);

		foreach ( $all_meta as $serialized_meta ) {
			$meta = maybe_unserialize( $serialized_meta );
			if ( ! is_array( $meta ) ) {
				continue;
			}

			if ( ! empty( $meta['webp']['status'] ) && 'optimized' === $meta['webp']['status'] ) {
				++$new_stats['webp']['optimized_count'];
				$new_stats['webp']['savings'] += (int) ( $meta['webp']['savings'] ?? 0 );
			}
			if ( ! empty( $meta['avif']['status'] ) && 'optimized' === $meta['avif']['status'] ) {
				++$new_stats['avif']['optimized_count'];
				$new_stats['avif']['savings'] += (int) ( $meta['avif']['savings'] ?? 0 );
			}
		}

		return self::update_stats( $new_stats );
	}

	/**
	 * A central helper to update the stats option and calculate percentages.
	 *
	 * @since 1.2.0
	 * @param array $stats The array with total counts and savings.
	 * @return array The final, complete stats array that was saved.
	 */
	private static function update_stats( array $stats ): array {
		$total = (int) ( $stats['total_images'] ?? 0 );

		foreach ( array( 'webp', 'avif' ) as $format ) {
			$optimized_count = (int) ( $stats[ $format ]['optimized_count'] ?? 0 );
			$unoptimized     = max( 0, $total - $optimized_count );

			$stats[ $format ]['unoptimized_images']   = $unoptimized;
			$stats[ $format ]['optimization_percent'] = ( $total > 0 ) ? ( $optimized_count / $total ) * 100 : 0.0;
		}

		\update_option( self::STATS_OPTION_KEY, $stats, 'no' );
		\wp_cache_delete( self::STATS_OPTION_KEY, 'options' );

		return $stats;
	}

	/**
	 * Starts a manual sync by building a queue of items to process.
	 *
	 * @since 1.2.0
	 * @param string $format The format to sync ('webp' or 'avif').
	 * @return array The initial state of the sync.
	 */
	public static function start_manual_sync( string $format ): array {
		global $wpdb;

		$optimized_pattern = '%' . $wpdb->esc_like( '"' . $format . '";a:2:{s:6:"status";s:9:"optimized";' ) . '%';
		$excluded_pattern  = '%' . $wpdb->esc_like( '"' . $format . '";a:1:{s:6:"status";s:8:"excluded";}' ) . '%';

		// This query is now run only once at the start to build the queue. It's more complex but reliable.
		$queue = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
                 WHERE p.post_type = 'attachment'
                   AND p.post_status = 'inherit'
                   AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
                   AND (pm.meta_value IS NULL OR (pm.meta_value NOT LIKE %s AND pm.meta_value NOT LIKE %s))
                 ORDER BY p.ID ASC",
				Cache_Hive_Image_Meta::META_KEY,
				$optimized_pattern,
				$excluded_pattern
			)
		);

		$total_to_optimize = count( $queue );
		$is_finished       = ( 0 === $total_to_optimize );

		$state = array(
			'format'            => $format,
			'total_to_optimize' => $total_to_optimize,
			'processed'         => 0,
			'is_finished'       => $is_finished,
			'is_running'        => ! $is_finished,
			'queue'             => $queue,
		);

		if ( ! $is_finished ) {
			\update_option( self::SYNC_STATE_OPTION_KEY, $state, 'no' );
		} else {
			self::clear_sync_state();
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
	 * Processes the next single item from the manual sync queue.
	 *
	 * @since 1.2.0
	 * @return array The updated sync state.
	 */
	public static function process_next_manual_item(): array {
		$state = self::get_sync_state();

		if ( ! $state || empty( $state['is_running'] ) || ! empty( $state['is_finished'] ) ) {
			$finished_state = array(
				'is_finished' => true,
				'is_running'  => false,
			);
			return ! empty( $state ) ? $state : $finished_state;
		}

		// Get the next item from the queue.
		$attachment_id = array_shift( $state['queue'] );

		if ( $attachment_id ) {
			// Process this item.
			Cache_Hive_Image_Optimizer::optimize_attachment( (int) $attachment_id, $state['format'] );
			++$state['processed'];
			\update_option( self::SYNC_STATE_OPTION_KEY, $state, 'no' );
		} else {
			// If the queue is empty, the sync is finished.
			$state['is_finished'] = true;
			$state['is_running']  = false;
			self::clear_sync_state();
		}

		return $state;
	}

	/**
	 * Gets an accurate count of unoptimized images for a specific format.
	 *
	 * @since 1.2.0
	 * @param string $format The format to count ('webp' or 'avif').
	 * @return int The number of unoptimized images.
	 */
	public static function get_unoptimized_count( string $format ): int {
		$stats = self::get_stats();
		if ( isset( $stats[ $format ]['unoptimized_images'] ) ) {
			return (int) $stats[ $format ]['unoptimized_images'];
		}
		return (int) ( $stats['total_images'] ?? 0 );
	}
}
