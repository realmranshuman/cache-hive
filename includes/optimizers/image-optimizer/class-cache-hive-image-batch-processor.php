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
		if ( ! \wp_next_scheduled( self::CRON_HOOK ) ) {
			// Schedule to run every five minutes, a standard WP interval.
			\wp_schedule_event( time(), 'five_minutes', self::CRON_HOOK );
		}
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

		// ** START: CORRECTED & UNIFIED QUERY LOGIC **
		$query_args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					// Condition 1: The optimization meta key does not exist at all.
					'key'     => Cache_Hive_Image_Meta::META_KEY,
					'compare' => 'NOT EXISTS',
				),
				array(
					// Condition 2: The meta key exists, but the status is NOT 'optimized'.
					// This correctly handles 'unoptimized', 'failed', 'in-progress', etc.
					'key'     => Cache_Hive_Image_Meta::META_KEY,
					'value'   => 's:6:"status";s:9:"optimized";',
					'compare' => 'NOT LIKE',
				),
			),
		);
		// ** END: CORRECTED & UNIFIED QUERY LOGIC **

		$attachments = new \WP_Query( $query_args );

		if ( $attachments->have_posts() ) {
			foreach ( $attachments->posts as $attachment ) {
				Cache_Hive_Image_Optimizer::optimize_attachment( $attachment->ID );
			}
		}

		// Release the lock.
		\delete_transient( self::LOCK_TRANSIENT );
	}
}
