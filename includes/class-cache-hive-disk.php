<?php
/**
 * Class for handling all disk-related operations.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final class for handling all disk-related operations for Cache Hive.
 *
 * This class is responsible for low-level file and directory operations only.
 *
 * @since 1.0.0
 */
final class Cache_Hive_Disk {
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
	 * Get the full path to the cache file for the current request.
	 *
	 * @since 1.0.0
	 * @return string The cache file path.
	 */
	public static function get_cache_file_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' ) : '';
		$uri = rtrim( $uri, '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__';
		}

		$host     = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		$dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

		// For logged-in users, add a hashed user folder. This maintains the subdirectory structure.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$settings = Cache_Hive_Settings::get_settings();
			if ( ! empty( $settings['cacheLoggedUsers'] ) ) {
				$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive';
				$user      = wp_get_current_user();
				$user_hash = 'user_' . md5( $user->ID . $auth_key );
				$dir_path .= '/' . $user_hash;
			}
		}

		// Use different filenames for mobile and desktop cache.
		$file_name = ( method_exists( 'Cache_Hive_Engine', 'is_mobile' ) && Cache_Hive_Engine::is_mobile() ) ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	/**
	 * Creates a static HTML file and its metadata file.
	 *
	 * @since 1.0.0
	 * @param string $buffer The page content to cache.
	 */
	public static function cache_page( $buffer ) {
		$cache_file = self::get_cache_file_path();
		$meta_file  = $cache_file . '.meta';
		$cache_dir  = dirname( $cache_file );

		if ( ! is_dir( $cache_dir ) ) {
			if ( ! @mkdir( $cache_dir, 0755, true ) ) {
				return;
			}
		}

		$cache_created = file_put_contents( $cache_file, $buffer . self::get_cache_signature(), LOCK_EX );

		if ( $cache_created ) {
			if ( false !== strpos( $cache_file, '/user_' ) ) {
				$settings = Cache_Hive_Settings::get_settings();
				$ttl      = $settings['privateCacheTTL'] ?? 1800;
			} else {
				$ttl = Cache_Hive_Settings::get_current_page_ttl();
			}

			$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$url         = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_uri;

			$meta_data = array(
				'created' => time(),
				'ttl'     => (int) $ttl,
				'url'     => $url,
			);
			file_put_contents( $meta_file, json_encode( $meta_data ), LOCK_EX );
		}
	}

	/**
	 * Checks if a cache file is valid (exists and is not expired).
	 *
	 * @since 1.0.0
	 * @param string $cache_file The full path to the cache file.
	 * @return bool
	 */
	public static function is_cache_valid( $cache_file ) {
		$meta_file = $cache_file . '.meta';

		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return false;
		}

		$meta_data_json = @file_get_contents( $meta_file );
		if ( ! $meta_data_json ) {
			return false;
		}

		$meta_data = json_decode( $meta_data_json, true );

		if ( empty( $meta_data['created'] ) || ! isset( $meta_data['ttl'] ) ) {
			return false;
		}

		if ( 0 === (int) $meta_data['ttl'] ) {
			return true;
		}

		return ( $meta_data['created'] + (int) $meta_data['ttl'] ) > time();
	}

	/**
	 * Recursively deletes a directory and its contents.
	 *
	 * @since 1.0.0
	 * @param string $dir The directory path to delete.
	 */
	public static function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		}
		$it    = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getRealPath() );
			} else {
				@unlink( $file->getRealPath() );
			}
		}
		@rmdir( $dir );
	}

	/**
	 * Returns the cache signature comment appended to cached files.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_cache_signature() {
		return '<!-- Cache served by Cache Hive on ' . gmdate( 'Y-m-d H:i:s' ) . ' -->';
	}
}
