<?php
/**
 * Browser Cache settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

require_once dirname( __DIR__ ) . '/class-cache-hive-browser-cache.php';

use Cache_Hive\Includes\Cache_Hive_Browser_Cache;
use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for Browser Cache settings.
 */
class Cache_Hive_REST_BrowserCache {

	/**
	 * Retrieves the browser cache settings and current server status.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings = Cache_Hive_Settings::get_settings();
		$server   = Cache_Hive_Browser_Cache::get_server_software();
		$status   = array(
			'settings' => array(
				'browser_cache_enabled' => (bool) ( $settings['browser_cache_enabled'] ?? false ),
				'browser_cache_ttl'     => (int) ( $settings['browser_cache_ttl'] ?? 0 ),
			),
			'server'   => $server,
		);

		if ( 'apache' === $server || 'litespeed' === $server ) {
			$htaccess_file               = trailingslashit( get_home_path() ) . '.htaccess';
			$status['htaccess_writable'] = is_writable( $htaccess_file );
			$status['rules']             = Cache_Hive_Browser_Cache::generate_htaccess_rules( $settings );
			$status['rules_present']     = false;

			if ( file_exists( $htaccess_file ) ) {
				$contents = @file_get_contents( $htaccess_file );
				if ( $contents && str_contains( $contents, '# BEGIN Cache Hive Browser Cache' ) ) {
					$status['rules_present'] = true;
				}
			}
		} elseif ( 'nginx' === $server ) {
			$status['rules'] = Cache_Hive_Browser_Cache::generate_nginx_rules( $settings );
		}

		return new WP_REST_Response( $status, 200 );
	}

	/**
	 * Updates the browser cache settings and applies them to .htaccess if possible.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated status.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params       = $request->get_json_params();
		$new_settings = Cache_Hive_Settings::sanitize_settings( $params );

		update_option( 'cache_hive_settings', $new_settings );
		Cache_Hive_Lifecycle::create_config_file( $new_settings );

		$server = Cache_Hive_Browser_Cache::get_server_software();
		if ( 'apache' === $server || 'litespeed' === $server ) {
			$result = Cache_Hive_Browser_Cache::update_htaccess( $new_settings );
			if ( is_wp_error( $result ) ) {
				$status          = self::get_settings()->get_data();
				$status['error'] = $result->get_error_message();
				return new WP_REST_Response( $status, 500 );
			}
		}

		// Invalidate the static settings snapshot to ensure the next get_settings() call is fresh.
		Cache_Hive_Settings::invalidate_settings_snapshot();

		return self::get_settings();
	}

	/**
	 * Verifies if Nginx browser cache headers are being served correctly.
	 *
	 * @return WP_REST_Response The response object with verification results.
	 */
	public static function verify_nginx_browser_cache() {
		$test_url = site_url( '/wp-includes/css/dashicons.min.css' );
		$response = wp_remote_head( $test_url, array( 'timeout' => 5 ) );
		$ttl      = 0;
		$verified = false;
		$message  = __( 'Browser cache headers not detected.', 'cache-hive' );

		if ( ! is_wp_error( $response ) && isset( $response['headers']['cache-control'] ) ) {
			$cc = $response['headers']['cache-control'];
			if ( preg_match( '/max-age=(\d+)/', $cc, $m ) ) {
				$ttl      = (int) $m[1];
				$verified = true;
				/* translators: %d: number of seconds */
				$message = sprintf( __( 'Browser cache detected (max-age=%d seconds).', 'cache-hive' ), $ttl );
			}
		}

		return new WP_REST_Response(
			array(
				'verified' => $verified,
				'ttl'      => $ttl,
				'message'  => $message,
			),
			200
		);
	}
}
