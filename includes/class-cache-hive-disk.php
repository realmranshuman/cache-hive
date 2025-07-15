<?php
/**
 * Class for handling all disk-related operations.
 *
 * @since 1.0.0
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
	 * Creates a static HTML file and its metadata file at a specific path.
	 * This method orchestrates all content optimizations via the Base Optimizer.
	 *
	 * @since 1.0.0
	 * @param string $buffer The raw page content to cache from the output buffer.
	 * @param string $cache_file The full, explicit path where the cache file should be saved.
	 */
	public static function cache_page( $buffer, $cache_file ) {
		if ( empty( $cache_file ) || ! is_string( $buffer ) ) {
			return;
		}

		$cache_dir = \dirname( $cache_file );
		if ( ! \is_dir( $cache_dir ) ) {
			if ( ! @\mkdir( $cache_dir, 0755, true ) ) {
				return;
			}
		}

		// --- Single-Pass Optimization Pipeline ---
		// A single call to the orchestrator handles all CSS, JS, and HTML optimizations
		// in the most efficient order, parsing the DOM only once if needed.
		$optimized_buffer = Cache_Hive_Base_Optimizer::process_all( $buffer, $cache_file );
		// --- End of Pipeline ---

		$meta_file = $cache_file . '.meta';

		// Write the *fully optimized* buffer to the cache file.
		$cache_created = \file_put_contents( $cache_file, $optimized_buffer . self::get_cache_signature(), LOCK_EX );

		if ( $cache_created ) {
			$settings = Cache_Hive_Settings::get_settings();

			// Determine TTL based on whether the cache is private or public.
			if ( false !== \strpos( $cache_file, CACHE_HIVE_PRIVATE_USER_CACHE_DIR ) ) {
				$ttl = $settings['private_cache_ttl'] ?? 1800;
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
		// The signature indicates when the optimized cache file was generated.
		return '<!-- Optimized and cached by Cache Hive on ' . \gmdate( 'Y-m-d H:i:s' ) . ' UTC -->';
	}
}
