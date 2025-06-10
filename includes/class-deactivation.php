<?php
/**
 * Deactivation Handler
 *
 * This file contains the class responsible for cleanup tasks when the plugin is deactivated.
 *
 * @package CacheHive
 */

namespace CacheHive\Includes;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles plugin deactivation.
 */
class Deactivation {

	/**
	 * Performs cleanup tasks when the plugin is deactivated.
	 *
	 * This method removes the advanced-cache.php drop-in, unschedules cron jobs,
	 * and flushes rewrite rules to ensure a clean deactivation state.
	 */
	public static function deactivate() {
		self::uninstall_dropin(); // <-- Add this

		// Unschedule the cron job.
		wp_clear_scheduled_hook( \CacheHive\Includes\Caching\Page_Cache_Manager::CRON_HOOK );
		flush_rewrite_rules();
	}

	/**
	 * Removes the advanced-cache.php drop-in from the wp-content directory.
	 * It checks for a unique signature to ensure we only delete our own file.
	 */
	private static function uninstall_dropin() {
		$dropin_file = WP_CONTENT_DIR . '/advanced-cache.php';

		if ( file_exists( $dropin_file ) ) {
			// Read the first line of the file to check for our signature.
			$file_handle = fopen( $dropin_file, 'r' );
			$first_line  = fgets( $file_handle );
			fclose( $file_handle );

			// If it's our file, delete it.
			if ( false !== strpos( $first_line, '# CacheHive Advanced Cache' ) ) {
				unlink( $dropin_file );
			}
		}
	}
}
