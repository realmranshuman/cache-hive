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

	const STATS_OPTION_KEY       = 'cache_hive_image_stats';
	const SYNC_STATE_OPTION_KEY  = 'cache_hive_image_sync_state';
	const STATS_DIRTY_TRANSTIENT = 'cache_hive_stats_dirty';

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

		// Define MIME types to pass as individual arguments.
		$mime1 = 'image/jpeg';
		$mime2 = 'image/png';
		$mime3 = 'image/gif';

		$total_images = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND post_mime_type IN (%s, %s, %s)", $mime1, $mime2, $mime3 ) );

		$optimized_meta_like = '%' . $wpdb->esc_like( 's:6:"status";s:9:"optimized";' ) . '%';
		$optimized_images    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p WHERE p.post_type = 'attachment' AND p.post_status = 'inherit' AND p.post_mime_type IN (%s, %s, %s) AND EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = %s AND pm.meta_value LIKE %s)", $mime1, $mime2, $mime3, Cache_Hive_Image_Meta::META_KEY, $optimized_meta_like ) );

		return self::update_stats_from_array(
			array(
				'total_images'     => $total_images,
				'optimized_images' => $optimized_images,
			)
		);
	}

	/**
	 * A central helper to update the stats option from an array.
	 *
	 * @since 1.0.0
	 * @param array $stats The array with total_images and optimized_images.
	 * @return array The final, complete stats array that was saved.
	 */
	private static function update_stats_from_array( array $stats ): array {
		$total_images                  = (int) ( $stats['total_images'] ?? 0 );
		$optimized_images              = (int) ( $stats['optimized_images'] ?? 0 );
		$stats['unoptimized_images']   = max( 0, $total_images - $optimized_images );
		$stats['optimization_percent'] = ( $total_images > 0 ) ? ( $optimized_images / $total_images ) * 100 : 0.0;
		$stats['optimization_percent'] = round( $stats['optimization_percent'], 1 );

		\update_option( self::STATS_OPTION_KEY, $stats, 'no' );
		\wp_cache_delete( self::STATS_OPTION_KEY, 'options' );
		\set_transient( self::STATS_DIRTY_TRANSTIENT, true, MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Starts or resumes a manual sync process.
	 *
	 * @since 1.2.0
	 * @return array The initial or current state of the sync.
	 */
	public static function start_manual_sync(): array {
		$state = self::get_sync_state();

		// If a sync is already running or paused, just return its current state.
		if ( $state && ! empty( $state['is_running'] ) ) {
			return $state;
		}

		$unoptimized_count = self::get_unoptimized_count( true );
		$is_finished       = ( 0 === $unoptimized_count );
		$state             = array(
			'total_to_optimize' => $unoptimized_count,
			'processed'         => 0,
			'is_finished'       => $is_finished,
			'is_running'        => ! $is_finished,
			'start_time'        => time(),
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
	 * Processes the next single image for a manual, browser-driven sync.
	 *
	 * @since 1.2.0
	 * @return array The updated sync state.
	 */
	public static function process_next_manual_item(): array {
		$state = self::get_sync_state();

		// If there's no active sync, return a finished state.
		if ( ! $state || ! $state['is_running'] || ! empty( $state['is_finished'] ) ) {
			return $state ? $state : array(
				'is_finished' => true,
				'is_running'  => false,
			);
		}

		global $wpdb;
		$meta_key = Cache_Hive_Image_Meta::META_KEY;

		$not_optimized_like = '%' . $wpdb->esc_like( 's:6:"status";s:9:"optimized";' ) . '%';
		$not_excluded_like  = '%' . $wpdb->esc_like( 's:6:"status";s:8:"excluded";' ) . '%';

		// Direct, performant query for a single unoptimized image.
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID
				 FROM {$wpdb->posts} p
				 LEFT JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = %s)
				 WHERE p.post_type = 'attachment'
				   AND p.post_status = 'inherit'
				   AND p.post_mime_type IN ('image/jpeg', 'image/png', 'image/gif')
				   AND (pm.meta_value IS NULL OR (pm.meta_value NOT LIKE %s AND pm.meta_value NOT LIKE %s))
				 ORDER BY p.ID ASC
				 LIMIT 1",
				$meta_key,
				$not_optimized_like,
				$not_excluded_like
			)
		);

		if ( $attachment_id ) {
			// We found an image, process it.
			Cache_Hive_Image_Optimizer::optimize_attachment( (int) $attachment_id );

			// Increment the processed count.
			++$state['processed'];

			// Update the state in the database.
			\update_option( self::SYNC_STATE_OPTION_KEY, $state, 'no' );
		} else {
			// No more images were found, the sync is complete.
			$state['is_finished'] = true;
			$state['is_running']  = false;
			self::clear_sync_state(); // This also recalculates final stats.
		}

		return $state;
	}

	/**
	 * Gets an accurate count of unoptimized images, respecting URL exclusions.
	 * This version is fully compliant with WordPress Coding Standards.
	 *
	 * @since 1.0.0
	 * @param bool $respect_exclusions Whether to filter the count based on URL exclusion rules.
	 * @return int The number of unoptimized images.
	 */
	public static function get_unoptimized_count( bool $respect_exclusions = false ): int {
		global $wpdb;

		$stats = self::get_stats();
		if ( ! $respect_exclusions ) {
			return $stats['unoptimized_images'];
		}

		$settings       = Cache_Hive_Settings::get_settings();
		$url_exclusions = $settings['image_exclude_images'] ?? array();

		if ( empty( $url_exclusions ) ) {
			return $stats['unoptimized_images'];
		}

		$excluded_ids = array();

		// Iterate through the small list of rules and run a simple query for each.
		// This is performant and WPCS compliant.
		foreach ( $url_exclusions as $rule ) {
			$trimmed_rule = trim( $rule );
			if ( empty( $trimmed_rule ) ) {
				continue; }
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit' AND guid LIKE %s", '%' . $wpdb->esc_like( $trimmed_rule ) . '%' ) );
			if ( ! empty( $ids ) ) {
				$excluded_ids = array_merge( $excluded_ids, $ids );
			}
		}

		if ( empty( $excluded_ids ) ) {
			return $stats['unoptimized_images'];
		}

		// Count only the unique IDs of images that match exclusion rules.
		$excluded_count = count( array_unique( $excluded_ids ) );

		// Return the total unoptimized count minus the number of those that are excluded.
		return max( 0, $stats['unoptimized_images'] - $excluded_count );
	}
}
