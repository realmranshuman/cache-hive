<?php
/**
 * Handles the plugin's activation, deactivation, and uninstallation procedures.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Includes\Helpers\Cache_Hive_Server_Rules_Helper;
use WP_Filesystem_Direct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin lifecycle events.
 */
final class Cache_Hive_Lifecycle {

	/**
	 * Initializes hooks related to the plugin's lifecycle, like multisite sitemap updates.
	 *
	 * @since 1.1.0
	 */
	public static function init_hooks() {
		add_action( 'wp_insert_blog', array( __CLASS__, 'update_multisite_map' ), 10, 0 );
		add_action( 'wp_update_blog_details', array( __CLASS__, 'update_multisite_map' ), 10, 0 );
		add_action( 'wp_delete_blog', array( __CLASS__, 'update_multisite_map' ), 10, 0 );
	}

	/**
	 * On plugin activation.
	 *
	 * @since 1.0.0
	 * @param bool $network_wide True if this is a network-wide activation.
	 */
	public static function on_activation( $network_wide ) {
		self::setup_environment();

		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::setup_site();
				restore_current_blog();
			}
			self::update_multisite_map();
		} else {
			self::setup_site();
		}
	}

	/**
	 * On plugin deactivation.
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
		self::cleanup_environment();
	}

	/**
	 * On plugin uninstall (called from uninstall.php).
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
			delete_site_option( 'cache_hive_settings' );
		} else {
			self::uninstall_site();
		}
		self::delete_root_directories();
	}

	/**
	 * Helper method to set up a single site's environment.
	 *
	 * @since 1.1.0
	 */
	private static function setup_site() {
		$settings                     = Cache_Hive_Settings::get_settings( true );
		$settings['object_cache_key'] = 'ch-' . wp_generate_password( 10, false );
		update_option( 'cache_hive_settings', $settings );
		self::setup_site_directories();
		self::create_config_file( $settings );
	}

	/**
	 * Creates all necessary directories for a single site.
	 *
	 * @since 1.1.0
	 */
	private static function setup_site_directories() {
		$dirs_to_create = array( \CACHE_HIVE_BASE_CACHE_DIR, \CACHE_HIVE_PUBLIC_CACHE_DIR, \CACHE_HIVE_PRIVATE_CACHE_DIR, \CACHE_HIVE_PRIVATE_USER_CACHE_DIR, \CACHE_HIVE_PRIVATE_URL_INDEX_DIR, \CACHE_HIVE_IMAGE_CACHE_DIR, \CACHE_HIVE_CONFIG_DIR );
		foreach ( $dirs_to_create as $dir ) {
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true );
			}
		}
	}

	/**
	 * Sets up the global environment (drop-in, wp-config constant, and server config files).
	 *
	 * @since 1.1.0
	 */
	public static function setup_environment() {
		self::create_advanced_cache_file();
		self::set_wp_cache_constant( true );

		$server = Cache_Hive_Server_Rules_Helper::get_server_software();
		if ( in_array( $server, array( 'apache', 'litespeed' ), true ) ) {
			self::create_security_files();
			Cache_Hive_Server_Rules_Helper::update_root_htaccess();
		} elseif ( 'nginx' === $server ) {
			Cache_Hive_Server_Rules_Helper::update_nginx_file();
		}
	}

	/**
	 * Cleans up the global environment.
	 *
	 * @since 1.1.0
	 */
	public static function cleanup_environment() {
		$advanced_cache_file = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( file_exists( $advanced_cache_file ) ) {
			$content = file_get_contents( $advanced_cache_file, false, null, 0, 128 );
			if ( false !== $content && false !== strpos( $content, 'Cache Hive - Advanced Cache Drop-in' ) ) {
				unlink( $advanced_cache_file );
			}
		}
		self::set_wp_cache_constant( false );

		$server = Cache_Hive_Server_Rules_Helper::get_server_software();
		if ( in_array( $server, array( 'apache', 'litespeed' ), true ) ) {
			self::remove_security_files();
			Cache_Hive_Server_Rules_Helper::remove_root_htaccess_rules();
		} elseif ( 'nginx' === $server ) {
			Cache_Hive_Server_Rules_Helper::delete_nginx_file();
		}
	}

	/**
	 * Sets or unsets the WP_CACHE constant in wp-config.php.
	 *
	 * @since 1.1.0
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
			$config_content = preg_replace( '/(<\?php\s*)/', '$1' . $define_string . "\n", $config_content, 1 );
		}
		file_put_contents( $config_path, $config_content, LOCK_EX );
	}

	/**
	 * Creates security files (like .htaccess) to protect cache directories.
	 *
	 * @since 1.2.0
	 */
	public static function create_security_files() {
		$cache_dir     = CACHE_HIVE_ROOT_CACHE_DIR;
		$htaccess_file = $cache_dir . '/.htaccess';
		$rules         = Cache_Hive_Server_Rules_Helper::get_security_htaccess_rules();

		if ( ! is_dir( $cache_dir ) ) {
			$created = mkdir( $cache_dir, 0755, true );
			// If directory creation fails, we can't proceed.
			if ( ! $created ) {
				return;
			}
		}

		// file_put_contents can return false on failure.
		$result = file_put_contents( $htaccess_file, $rules );

		if ( false === $result ) {
			// Optional: You could log an error here if you have a logging system.
			// error_log( 'Cache Hive: Failed to write security .htaccess file.' );.
		}
	}

	/**
	 * Removes security files from cache directories.
	 *
	 * @since 1.2.0
	 */
	private static function remove_security_files() {
		$htaccess_file = CACHE_HIVE_ROOT_CACHE_DIR . '/.htaccess';
		if ( file_exists( $htaccess_file ) ) {
			unlink( $htaccess_file );
		}
	}


	/**
	 * Creates or updates the config file for the current site using the secure "PHP Guard" method.
	 *
	 * @since 1.1.0
	 * @param array $settings The settings to save.
	 */
	public static function create_config_file( $settings ) {
		if ( ! defined( 'CACHE_HIVE_CONFIG_DIR' ) ) {
			return;
		}
		if ( ! is_dir( \CACHE_HIVE_CONFIG_DIR ) ) {
			mkdir( \CACHE_HIVE_CONFIG_DIR, 0755, true );
		}

		if ( is_multisite() && is_main_site() ) {
			global $wpdb;
			$blogs                     = $wpdb->get_results( "SELECT blog_id, domain, path FROM {$wpdb->blogs} ORDER BY CHAR_LENGTH(path) DESC" );
			$settings['multisite_map'] = $blogs;
		} elseif ( ! is_multisite() ) {
			$settings['multisite_map'] = array();
		}

		$json_string = wp_json_encode( $settings );

		$file_contents  = "<?php\n";
		$file_contents .= "// Prevent direct access.\n";
		$file_contents .= "if ( ! defined( 'ABSPATH' ) ) {\n\texit;\n}\n\n";
		$file_contents .= '// Return the configuration as a JSON string.' . "\n";
		$file_contents .= 'return ' . var_export( $json_string, true ) . ';';

		$config_file = \CACHE_HIVE_CONFIG_DIR . '/config.php';
		file_put_contents( $config_file, $file_contents, LOCK_EX );

		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			opcache_invalidate( $config_file, true );
		}
	}

	/**
	 * Generates/updates the multisite map and saves it to the main site's config file.
	 *
	 * @since 1.1.0
	 */
	public static function update_multisite_map() {
		if ( ! is_multisite() ) {
			return;
		}
		switch_to_blog( get_main_site_id() );
		$settings = Cache_Hive_Settings::get_settings( true );
		self::create_config_file( $settings );
		restore_current_blog();
	}

	/**
	 * Deletes the config file for the current site.
	 *
	 * @since 1.1.0
	 */
	public static function delete_config_file() {
		if ( ! defined( 'CACHE_HIVE_CONFIG_DIR' ) ) {
			return;
		}
		$config_file = \CACHE_HIVE_CONFIG_DIR . '/config.php';
		if ( file_exists( $config_file ) ) {
			unlink( $config_file );
		}
		if ( is_dir( \CACHE_HIVE_CONFIG_DIR ) ) {
			rmdir( \CACHE_HIVE_CONFIG_DIR );
		}
	}

	/**
	 * Creates the advanced-cache.php file in wp-content.
	 *
	 * @since 1.1.0
	 * @return bool Success or failure.
	 */
	public static function create_advanced_cache_file() {
		if ( ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}
		$source      = \CACHE_HIVE_DIR . 'class-cache-hive-advanced-cache.php';
		$destination = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( ! is_readable( $source ) ) {
			return false;
		}
		return copy( $source, $destination );
	}

	/**
	 * Finds the path to wp-config.php.
	 *
	 * @since 1.1.0
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
	 * Helper method to clean up a single site's environment on deactivation.
	 *
	 * @since 1.1.0
	 */
	private static function cleanup_site() {
		$cron_hook_suffix = '';
		if ( is_multisite() ) {
			$cron_hook_suffix = '_' . get_current_blog_id();
		}

		// Unschedule the image optimization cron.
		$image_cron_hook = 'cache_hive_image_optimization_batch' . $cron_hook_suffix;
		$timestamp       = wp_next_scheduled( $image_cron_hook );
		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, $image_cron_hook );
			$timestamp = wp_next_scheduled( $image_cron_hook );
		}

		// These classes should be available during deactivation but not necessarily uninstall.
		if ( class_exists( 'Cache_Hive\Includes\Cache_Hive_Purge' ) ) {
			Cache_Hive_Purge::purge_disk_cache();
		}
		if ( class_exists( 'Cache_Hive\Includes\Cache_Hive_Object_Cache' ) ) {
			Cache_Hive_Object_Cache::disable();
		}

		self::delete_config_file();
	}

	/**
	 * Helper method to completely uninstall from a single site.
	 *
	 * @since 1.1.0
	 */
	private static function uninstall_site() {
		self::cleanup_site();
		delete_option( 'cache_hive_settings' );
		self::delete_site_directories();
	}

	/**
	 * Deletes the cache and config directories for the current site.
	 *
	 * @since 1.1.0
	 */
	private static function delete_site_directories() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wp_filesystem = new WP_Filesystem_Direct( null );
		if ( defined( 'CACHE_HIVE_BASE_CACHE_DIR' ) ) {
			$wp_filesystem->rmdir( \CACHE_HIVE_BASE_CACHE_DIR, true );
		}
		if ( defined( 'CACHE_HIVE_CONFIG_DIR' ) ) {
			$wp_filesystem->rmdir( \CACHE_HIVE_CONFIG_DIR, true );
		}
	}

	/**
	 * Deletes the entire root cache and config directories during uninstall.
	 *
	 * @since 1.1.0
	 */
	private static function delete_root_directories() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		$wp_filesystem = new WP_Filesystem_Direct( null );
		$wp_filesystem->rmdir( \CACHE_HIVE_ROOT_CACHE_DIR, true );
		$wp_filesystem->rmdir( \CACHE_HIVE_ROOT_CONFIG_DIR, true );
	}
}

// Initialize hooks that need to run on every page load.
Cache_Hive_Lifecycle::init_hooks();
