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
	 * Returns the cache signature comment appended to cached files.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_cache_signature() {
		return '<!-- Cache served by Cache Hive on ' . gmdate( 'Y-m-d H:i:s' ) . ' -->';
	}
}
