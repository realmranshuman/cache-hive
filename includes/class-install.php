<?php
/**
 * Installation Handler
 *
 * This file contains the class responsible for plugin installation and activation tasks.
 *
 * @package CacheHive
 */

namespace CacheHive\Includes;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles plugin activation.
 */
class Install {

	/**
	 * Main activation routine.
	 */
	public static function activate() {
		self::create_options();
		self::create_cache_directory();
		self::install_dropin();

		// Schedule cron job for cache maintenance.
		if ( ! wp_next_scheduled( \CacheHive\Includes\Caching\Page_Cache_Manager::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', \CacheHive\Includes\Caching\Page_Cache_Manager::CRON_HOOK );
		}

		flush_rewrite_rules();
	}

	/**
	 * Copies the advanced-cache.php drop-in to the wp-content directory.
	 */
	private static function install_dropin() {
		$source_file      = CACHEHIVE_PLUGIN_DIR . 'advanced-cache.php';
		$destination_file = WP_CONTENT_DIR . '/advanced-cache.php';

		// Only copy if the source exists and wp-content is writable.
		if ( is_readable( $source_file ) && is_writable( WP_CONTENT_DIR ) ) {
			// We don't check if the destination exists, we overwrite it.
			// This ensures our latest version is always installed on reactivation.
			copy( $source_file, $destination_file );
		}
	}

	/**
	 * Add default options to the database.
	 */
	private static function create_options() {
		if ( false === get_option( CACHEHIVE_SETTINGS_SLUG ) ) {
			require_once CACHEHIVE_PLUGIN_DIR . 'includes/class-settings.php';
			$settings = new Settings();
			update_option( CACHEHIVE_SETTINGS_SLUG, $settings->get_default_settings() );
		}
	}

	/**
	 * Create the main cache directory.
	 */
	private static function create_cache_directory() {
		$cache_path = WP_CONTENT_DIR . '/cache/cache-hive';
		if ( ! is_dir( $cache_path ) ) {
			wp_mkdir_p( $cache_path );
		}

		$security_file = $cache_path . '/index.php';
		if ( ! file_exists( $security_file ) ) {
			$result = file_put_contents( $security_file, '<?php // Silence is golden.' );
			if ( false === $result ) {
				// Log error or handle the failure gracefully.
				error_log( 'CacheHive: Failed to create security index.php file in cache directory.' );
			}
		}
	}
}
