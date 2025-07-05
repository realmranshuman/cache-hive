<?php
/**
 * Class for handling all disk-related operations.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all disk-related operations for Cache Hive.
 */
final class Cache_Hive_Disk {

	/**
	 * Get the full path to the cache file for the current request.
	 *
	 * @return string The cache file path.
	 */
	public static function get_cache_file_path() {
		$uri = strtok( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ), '?' );
		$uri = rtrim( $uri, '/' );
		$uri = empty( $uri ) ? '/__index__' : $uri;

		$host     = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) );
		$dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$settings = Cache_Hive_Settings::get_settings();
			if ( ! empty( $settings['cache_logged_users'] ) ) {
				$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cache_hive_default_salt';
				$user      = wp_get_current_user();
				$user_hash = 'user_' . md5( $user->ID . $auth_key );
				$dir_path .= '/' . $user_hash;
			}
		}

		$file_name = ( method_exists( Cache_Hive_Engine::class, 'is_mobile' ) && Cache_Hive_Engine::is_mobile() ) ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	/**
	 * Creates a static HTML file and its metadata file after running optimizations.
	 *
	 * @param string $buffer The page content to cache.
	 */
	public static function cache_page( $buffer ) {
		$optimized_buffer = Cache_Hive_Base_Optimizer::optimize( $buffer );
		$final_buffer     = ! empty( $optimized_buffer ) ? $optimized_buffer : $buffer;

		if ( empty( trim( $final_buffer ) ) ) {
			return;
		}

		$cache_file = self::get_cache_file_path();
		$meta_file  = $cache_file . '.meta';
		$cache_dir  = dirname( $cache_file );

		if ( ! is_dir( $cache_dir ) ) {
			if ( ! @mkdir( $cache_dir, 0755, true ) ) {
				return;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		$cache_created = file_put_contents( $cache_file, $final_buffer . self::get_cache_signature(), LOCK_EX );

		if ( $cache_created ) {
			$ttl = str_contains( $cache_file, '/user_' )
				? ( Cache_Hive_Settings::get( 'private_cache_ttl' ) ?? 1800 )
				: Cache_Hive_Settings::get_current_page_ttl();

			$http_host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) );
			$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
			$url         = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_uri;

			$meta_data = array(
				'created' => time(),
				'ttl'     => (int) $ttl,
				'url'     => esc_url_raw( $url ),
			);
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			file_put_contents( $meta_file, wp_json_encode( $meta_data ), LOCK_EX );
		}
	}

	/**
	 * Returns the cache signature comment appended to cached files.
	 *
	 * @return string
	 */
	public static function get_cache_signature() {
		return '<!-- Cache served by Cache Hive on ' . gmdate( 'Y-m-d H:i:s' ) . ' -->';
	}
}
