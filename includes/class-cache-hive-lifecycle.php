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
		self::setup_environment();

		// Now that the environment is set up, ensure the config file has the latest settings.
		Cache_Hive_Lifecycle::create_config_file( $settings );
	}

	/**
	 * Setup environment: create advanced-cache.php and set WP_CACHE constant.
	 *
	 * @since 1.0.0
	 */
	public static function setup_environment() {
		self::create_advanced_cache_file();
		self::set_wp_cache_constant( true );
	}

	/**
	 * Cleanup environment: remove advanced-cache.php and unset WP_CACHE constant.
	 *
	 * @since 1.0.0
	 */
	public static function cleanup_environment() {
		if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			@unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
		}
		self::set_wp_cache_constant( false );
		self::delete_config_file();
	}

	/**
	 * Sets or unsets the WP_CACHE constant in wp-config.php.
	 *
	 * @since 1.0.0
	 * @param bool $enable True to set the constant, false to remove.
	 */
	private static function set_wp_cache_constant( $enable = true ) {
		$config_path = self::find_wp_config_path();
		if ( ! $config_path || ! is_writable( $config_path ) ) {
			return;
		}

		$config_content = file_get_contents( $config_path );
		$define_string  = "define( 'WP_CACHE', true ); // Added by Cache Hive.";

		$config_content = preg_replace( "/^[\t\s]*define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*.*\s*\);.*?\R/mi", '', $config_content );

		if ( $enable ) {
			// Insert define at the top after <?php.
			$config_content = preg_replace( '/<\?php(.*?\n)/', "<?php\n$define_string\n", $config_content, 1 );
		}

		file_put_contents( $config_path, $config_content, LOCK_EX );
	}

	/**
	 * Create the config file with current settings for advanced-cache.php to read.
	 *
	 * @since 1.0.0
	 * @param array $settings The settings array.
	 */
	public static function create_config_file( $settings ) {
		if ( ! is_dir( CACHE_HIVE_CONFIG_DIR ) ) {
			@mkdir( CACHE_HIVE_CONFIG_DIR, 0755, true );
		}

		$config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
		$contents    = '<?php return ' . var_export( $settings, true ) . ';';
		file_put_contents( $config_file, $contents, LOCK_EX );

		// Invalidate OPcache for the config file.
		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			$result = opcache_invalidate( $config_file, true );
			if ( false === $result ) {
				// Optionally log or handle the failure to invalidate OPcache.
				error_log( 'Failed to invalidate OPcache for: ' . $config_file );
			}
		}
	}

	/**
	 * Deletes the config file.
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
	 * @return bool Success or failure.
	 */
	public static function create_advanced_cache_file() {
		if ( ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}
		$advanced_cache_source_file      = CACHE_HIVE_DIR . 'class-cache-hive-advanced-cache.php';
		$advanced_cache_destination_file = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( ! is_readable( $advanced_cache_source_file ) ) {
			return false;
		}
		return copy( $advanced_cache_source_file, $advanced_cache_destination_file );
	}

	/**
	 * Finds the path to wp-config.php.
	 *
	 * @since 1.0.0
	 * @return string|false Path to wp-config.php or false if not found.
	 */
	private static function find_wp_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}
		return false;
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
		self::cleanup_environment();

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
