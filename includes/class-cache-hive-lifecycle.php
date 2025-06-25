<?php
/**
 * Handles the plugin's activation, deactivation, and uninstallation procedures.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final class for managing plugin lifecycle events.
 *
 * This class handles the setup required on activation, cleanup on deactivation,
 * and complete data removal on uninstallation.
 *
 * @since 1.0.0
 */
final class Cache_Hive_Lifecycle {

	/**
	 * On plugin activation.
	 * Sets up the necessary environment for the plugin to function.
	 *
	 * @since 1.0.0
	 * @param bool $network_wide True if this is a network-wide activation.
	 */
	public static function on_activation( $network_wide ) {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::setup_site();
				restore_current_blog();
			}
		} else {
			self::setup_site();
		}
	}

	/**
	 * On plugin deactivation.
	 * Cleans up the environment but leaves settings intact for re-activation.
	 *
	 * @since 1.0.0
	 * @param bool $network_wide True if this is a network-wide deactivation.
	 */
	public static function on_deactivation( $network_wide ) {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::cleanup_site();
				restore_current_blog();
			}
		} else {
			self::cleanup_site();
		}
	}

	/**
	 * On plugin uninstall.
	 * WARNING: This is a destructive action that removes all data.
	 * It is called from uninstall.php.
	 *
	 * @since 1.0.0
	 */
	public static function on_uninstall() {
		if ( is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::uninstall_site();
				restore_current_blog();
			}
		} else {
			self::uninstall_site();
		}
	}

	/**
	 * Helper method to set up a single site's environment.
	 *
	 * @since 1.0.0
	 */
	private static function setup_site() {
		// This will create the option with default values if it doesn't exist.
		$settings = Cache_Hive_Settings::get_settings( true );

		// This will create the config file, advanced-cache.php, and set WP_CACHE constant.
		Cache_Hive_Disk::setup_environment();

		// Now that the environment is set up, ensure the config file has the latest settings.
		Cache_Hive_Disk::create_config_file( $settings );
	}

	/**
	 * Helper method to clean up a single site's environment.
	 *
	 * @since 1.0.0
	 */
	private static function cleanup_site() {
		// Purge all cached files.
		Cache_Hive_Purge::purge_all();

		// This will remove advanced-cache.php, the WP_CACHE constant, and the config file.
		Cache_Hive_Disk::cleanup_environment();

		// Remove object-cache.php drop-in if it exists.
		$dropin = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/object-cache.php' : false;
		if ( $dropin && file_exists( $dropin ) ) {
			@unlink( $dropin );
		}
	}

	/**
	 * Helper method to completely uninstall from a single site.
	 *
	 * @since 1.0.0
	 */
	private static function uninstall_site() {
		// Perform all deactivation steps first.
		self::cleanup_site();

		// Delete the plugin settings from the options table.
		delete_option( 'cache_hive_settings' );

		// Physically remove the entire cache directory.
		self::delete_cache_directory();

		// Remove object-cache.php drop-in if it exists (redundant, but ensures cleanup).
		$dropin = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/object-cache.php' : false;
		if ( $dropin && file_exists( $dropin ) ) {
			@unlink( $dropin );
		}
	}

	/**
	 * Deletes the entire Cache Hive cache directory from wp-content.
	 *
	 * @since 1.0.0
	 */
	private static function delete_cache_directory() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';

		$wp_filesystem = new WP_Filesystem_Direct( null );
		$wp_filesystem->rmdir( CACHE_HIVE_CACHE_DIR, true );
	}
}
