<?php
/**
 * Handles cron-based batch image optimization.
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
 * Manages the background processing of images via WP-Cron.
 */
final class Cache_Hive_Image_Batch_Processor {

	/**
	 * The cron hook name.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'cache_hive_image_optimization_batch';

	/**
	 * The transient key used as a lock to prevent concurrent runs.
	 *
	 * @var string
	 */
	const LOCK_TRANSIENT = 'cache_hive_image_batch_lock';

	/**
	 * Schedules the cron event if batch processing is enabled.
	 *
	 * @since 1.0.0
	 */
	public static function schedule_event() {
		// First, add our custom schedule to WordPress.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_fifteen_minute_schedule' ) );

		// Then, schedule the event using our custom interval.
		if ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			\wp_schedule_event( time(), 'fifteen_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Adds a custom 'fifteen_minutes' cron schedule.
	 *
	 * This method is hooked into the 'cron_schedules' filter to make the
	 * custom interval available to WordPress.
	 *
	 * @since 1.0.0
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public static function add_fifteen_minute_schedule( $schedules ) {
		if ( ! isset( $schedules['fifteen_minutes'] ) ) {
			$schedules['fifteen_minutes'] = array(
				'interval' => 900, // 15 minutes in seconds (15 * 60).
				'display'  => esc_html__( 'Every Fifteen Minutes', 'cache-hive' ),
			);
		}
		return $schedules;
	}

	/**
	 * Clears the scheduled cron event.
	 *
	 * @since 1.0.0
	 */
	public static function clear_scheduled_event() {
		\wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * The main cron callback function to process a batch of images.
	 *
	 * @since 1.0.0
	 */
	public static function process_batch() {
		// Check for a lock. If it exists, another process is running.
		if ( \get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}

		// Set a lock to prevent concurrent runs, with a 15-minute expiration.
		\set_transient( self::LOCK_TRANSIENT, true, 15 * MINUTE_IN_SECONDS );

		$settings   = Cache_Hive_Settings::get_settings();
		$batch_size = (int) ( $settings['image_batch_size'] ?? 10 );

		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
			'fields'         => 'ids', // More efficient to only get IDs.
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					// Condition 1: The optimization meta key does not exist at all.
					'key'     => Cache_Hive_Image_Meta::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					// Condition 2: The meta key exists, but the status is NOT 'optimized'.
					'key'     => Cache_Hive_Image_Meta::META_KEY,
					'value'   => 's:6:"status";s:9:"optimized";',
					'compare' => 'NOT LIKE',
				),
			),
		);

		$attachments = new \WP_Query( $query_args );

		if ( $attachments->have_posts() ) {
			foreach ( $attachments->posts as $attachment_id ) {
				Cache_Hive_Image_Optimizer::optimize_attachment( $attachment_id );
			}
		}

		// Release the lock.
		\delete_transient( self::LOCK_TRANSIENT );
	}
}
