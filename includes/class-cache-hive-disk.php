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
	 * Creates a static HTML file and its metadata file at a specific path.
	 * This method now orchestrates all content optimizations.
	 *
	 * @since 1.2.3
	 * @param string $buffer The raw page content to cache from the output buffer.
	 * @param string $cache_file The full, explicit path where the cache file should be saved.
	 */
	public static function cache_page( $buffer, $cache_file ) {
		if ( empty( $cache_file ) ) {
			return;
		}

		$cache_dir = \dirname( $cache_file );
		if ( ! \is_dir( $cache_dir ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( ! @\mkdir( $cache_dir, 0755, true ) ) {
				return;
			}
		}

		// --- Optimization Pipeline ---
		// Step 1: Process CSS optimizations.
		$buffer = Cache_Hive_CSS_Optimizer::process( $buffer, $cache_file );

		// Step 2: NEW - Process JS optimizations.
		$buffer = Cache_Hive_JS_Optimizer::process( $buffer, $cache_file );

		// Step 3: Feed the fully optimized buffer into the HTML optimizer.
		$optimized_buffer = Cache_Hive_HTML_Optimizer::process( $buffer );
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
