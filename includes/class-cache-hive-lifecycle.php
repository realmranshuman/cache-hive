<?php
/**
 * Handles the plugin's activation, deactivation, and uninstallation procedures.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Rewrite;
use WP_Filesystem_Direct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin lifecycle events.
 */
final class Cache_Hive_Lifecycle {

	/**
	 * On plugin activation.
	 *
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
	 *
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
	 * On plugin uninstall (called from uninstall.php).
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
	 */
	private static function setup_site() {
		// This will create the option with default values if it doesn't exist.
		$settings = Cache_Hive_Settings::get_settings( true );

		// On non-Windows systems, symlinks are reliable. On Windows, they require special
		// permissions that a web server user typically does not have.
		if ( strtoupper( substr( PHP_OS, 0, 3 ) ) === 'WIN' ) {
			$settings['use_symlinks'] = false;
		} else {
			$settings['use_symlinks'] = true;
		}
		// Save the detected setting.
		update_option( 'cache_hive_settings', $settings );

		self::setup_environment();
		// Re-create the config file with the new OS-aware setting.
		self::create_config_file( $settings );

		// Add rewrite rules for image optimization if the method is 'rewrite'.
		if ( 'rewrite' === ( $settings['image_delivery_method'] ?? 'rewrite' ) ) {
			Cache_Hive_Image_Rewrite::insert_rules();
		}
	}


	/**
	 * Setup environment: create advanced-cache.php and set WP_CACHE constant.
	 */
	public static function setup_environment() {
		self::create_advanced_cache_file();
		self::set_wp_cache_constant( true );
	}

	/**
	 * Cleanup environment: remove advanced-cache.php and unset WP_CACHE constant.
	 */
	public static function cleanup_environment() {
		$advanced_cache_file = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( file_exists( $advanced_cache_file ) ) {
			// To be safe, only delete the file if it's ours.
			$content = file_get_contents( $advanced_cache_file );
			if ( false !== strpos( $content, 'Cache Hive - Advanced Cache Drop-in' ) ) {
				unlink( $advanced_cache_file );
			}
		}
		self::set_wp_cache_constant( false );
		self::delete_config_file();
	}

	/**
	 * Sets or unsets the WP_CACHE constant in wp-config.php.
	 *
	 * @param bool $enable True to set the constant, false to remove.
	 */
	private static function set_wp_cache_constant( $enable = true ) {
		$config_path = self::find_wp_config_path();
		if ( ! $config_path || ! is_writable( $config_path ) ) {
			return;
		}
		// Read the config file content.
		$config_content = file_get_contents( $config_path );
		$define_string  = "define( 'WP_CACHE', true ); // Added by Cache Hive.";

		// Remove any existing WP_CACHE definition.
		$config_content = preg_replace( "/^[\t\s]*define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*.*\s*\);.*?\R/mi", '', $config_content );

		if ( $enable ) {
			// Insert define at the top after <?php.
			$config_content = preg_replace( '/(<\?php\s*)/', '$1' . $define_string . "\n", $config_content, 1 );
		}

		file_put_contents( $config_path, $config_content, LOCK_EX );
	}

	/**
	 * Create the config file with current settings for advanced-cache.php to read.
	 *
	 * @param array $settings The settings array.
	 */
	public static function create_config_file( $settings ) {
		if ( ! is_dir( CACHE_HIVE_CONFIG_DIR ) ) {
			@mkdir( CACHE_HIVE_CONFIG_DIR, 0755, true );
		}
		$config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
		$contents    = '<?php return ' . var_export( $settings, true ) . ';';

		file_put_contents( $config_file, $contents, LOCK_EX );

		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			opcache_invalidate( $config_file, true );
		}
	}

	/**
	 * Deletes the config file.
	 */
	public static function delete_config_file() {
		$config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
		if ( file_exists( $config_file ) ) {
			@unlink( $config_file );
		}
		if ( is_dir( CACHE_HIVE_CONFIG_DIR ) ) {
			@rmdir( CACHE_HIVE_CONFIG_DIR );
		}
	}

	/**
	 * Creates the advanced-cache.php file in wp-content.
	 *
	 * @return bool Success or failure.
	 */
	public static function create_advanced_cache_file() {
		if ( ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}
		$source      = CACHE_HIVE_DIR . 'class-cache-hive-advanced-cache.php';
		$destination = WP_CONTENT_DIR . '/advanced-cache.php';

		if ( ! is_readable( $source ) ) {
			return false;
		}

		return copy( $source, $destination );
	}

	/**
	 * Finds the path to wp-config.php.
	 *
	 * @return string|false Path to wp-config.php or false if not found.
	 */
	private static function find_wp_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		$parent_config = dirname( ABSPATH ) . '/wp-config.php';
		if ( file_exists( $parent_config ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $parent_config;
		}
		return false;
	}

	/**
	 * Helper method to clean up a single site's environment.
	 */
	private static function cleanup_site() {
		// Unschedule the cron job to keep the site clean.
		wp_clear_scheduled_hook( 'cache_hive_garbage_collection' );
		wp_clear_scheduled_hook( 'cache_hive_image_optimization_batch' );

		Cache_Hive_Purge::purge_all();
		self::cleanup_environment();
		Cache_Hive_Object_Cache::disable();

		// Remove image optimization rewrite rules.
		Cache_Hive_Image_Rewrite::remove_rules();
	}

	/**
	 * Helper method to completely uninstall from a single site.
	 */
	private static function uninstall_site() {
		self::cleanup_site();
		delete_option( 'cache_hive_settings' );
		self::delete_cache_directory();
	}

	/**
	 * Deletes the entire Cache Hive cache directory from wp-content.
	 */
	private static function delete_cache_directory() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wp_filesystem = new WP_Filesystem_Direct( null );
		// Use the correct base directory constant to ensure everything is deleted.
		$wp_filesystem->rmdir( CACHE_HIVE_BASE_CACHE_DIR, true );
	}
}
