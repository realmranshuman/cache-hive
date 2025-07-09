<?php
/**
 * Class for handling all disk-related operations.
 *
 * @since 1.2.3
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final class for handling all disk-related operations for Cache Hive.
 */
final class Cache_Hive_Disk {

	/**
	 * DELETED: The get_cache_file_path() method has been removed from this class.
	 * This class should only perform disk operations, not decide file paths.
	 * The Cache_Hive_Engine is now the single source of truth for path generation.
	 */

	/**
	 * Creates a static HTML file and its metadata file at a specific path.
	 *
	 * @since 1.2.3
	 * @param string $buffer The page content to cache.
	 * @param string $cache_file The full, explicit path where the cache file should be saved.
	 */
	public static function cache_page( $buffer, $cache_file ) {
		// THE FIX: This function no longer calculates its own path. It uses the
		// explicit path passed to it by the Cache_Hive_Engine.
		if ( empty( $cache_file ) ) {
			return; // Do not proceed without an explicit path.
		}

		// Per the design, this class is responsible for optimization before writing.
		// We process the raw buffer received from the Engine to get the final, optimized content.
		$optimized_buffer = Cache_Hive_HTML_Optimizer::process( $buffer );

		$meta_file = $cache_file . '.meta';
		$cache_dir = \dirname( $cache_file );

		if ( ! \is_dir( $cache_dir ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @\mkdir( $cache_dir, 0755, true ) ) {
				return;
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		// Write the *optimized* buffer to the cache file.
		$cache_created = \file_put_contents( $cache_file, $optimized_buffer . self::get_cache_signature(), LOCK_EX );

		if ( $cache_created ) {
			// The TTL logic can remain here, as it's part of writing the meta file.
			if ( false !== \strpos( $cache_file, '/user_' ) ) {
				$settings = Cache_Hive_Settings::get_settings();
				$ttl      = $settings['private_cache_ttl'] ?? 1800;
			} else {
				$ttl = Cache_Hive_Settings::get_current_page_ttl();
			}

			$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$url         = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_uri;

			$meta_data = array(
				'created' => \time(),
				'ttl'     => (int) $ttl,
				'url'     => $url,
			);
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			\file_put_contents( $meta_file, \json_encode( $meta_data ), LOCK_EX );
		}
	}

	/**
	 * Returns the cache signature comment appended to cached files.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_cache_signature() {
		// The signature now clearly indicates when the optimized cache file was generated.
		return '<!-- Optimized and cached by Cache Hive on ' . \gmdate( 'Y-m-d H:i:s' ) . ' UTC -->';
	}
}
