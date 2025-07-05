<?php
/**
 * Manages all Cloudflare API interactions for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\Integrations;

use Cache_Hive\Includes\Cache_Hive_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Cloudflare API interactions.
 */
final class Cache_Hive_Cloudflare {
	/**
	 * Cloudflare API base URL.
	 *
	 * @var string
	 */
	private static $api_base = 'https://api.cloudflare.com/client/v4/';

	/**
	 * Initializes hooks for Cloudflare integration.
	 */
	public static function init() {
		add_action( 'update_option_cache_hive_settings', array( __CLASS__, 'sync_browser_cache_ttl' ), 10, 2 );
	}

	/**
	 * Purges the entire Cloudflare cache for the configured zone.
	 *
	 * @return array An array containing the success status and a message.
	 */
	public static function purge_all() {
		if ( ! Cache_Hive_Settings::get( 'cloudflare_enabled' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cloudflare integration is not enabled.', 'cache-hive' ),
			);
		}

		$zone_id = Cache_Hive_Settings::get( 'cloudflare_zone_id' );
		if ( empty( $zone_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cloudflare Zone ID is not configured.', 'cache-hive' ),
			);
		}

		return self::make_request( "zones/{$zone_id}/purge_cache", 'POST', array( 'purge_everything' => true ) );
	}

	/**
	 * Syncs the browser cache TTL setting with Cloudflare.
	 *
	 * @param array $old_settings The old plugin settings.
	 * @param array $new_settings The new plugin settings.
	 */
	public static function sync_browser_cache_ttl( $old_settings, $new_settings ) {
		if ( empty( $new_settings['cloudflare_enabled'] ) || ( $old_settings['browser_cache_ttl'] ?? 0 ) === ( $new_settings['browser_cache_ttl'] ?? 0 ) ) {
			return;
		}

		$zone_id = $new_settings['cloudflare_zone_id'];
		$ttl     = (int) $new_settings['browser_cache_ttl'];

		if ( ! empty( $zone_id ) && $ttl > 0 ) {
			self::make_request( "zones/{$zone_id}/settings/browser_cache_ttl", 'PATCH', array( 'value' => $ttl ) );
		}
	}

	/**
	 * Makes a request to the Cloudflare API.
	 *
	 * @param string $endpoint The API endpoint to request.
	 * @param string $method   The HTTP method.
	 * @param array  $body     The request body.
	 * @return array An array containing the success status, a message, and the response body.
	 */
	private static function make_request( $endpoint, $method = 'GET', $body = array() ) {
		$headers = array( 'Content-Type' => 'application/json' );
		$token   = Cache_Hive_Settings::get( 'cloudflare_api_token' );

		if ( empty( $token ) ) {
			return array(
				'success' => false,
				'message' => __( 'Cloudflare API Token is missing.', 'cache-hive' ),
			);
		}
		$headers['Authorization'] = 'Bearer ' . $token;

		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( self::$api_base . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => 'WP_Error: ' . $response->get_error_message(),
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $response_code >= 200 && $response_code < 300 && ( $response_body['success'] ?? false ) ) {
			return array(
				'success' => true,
				'message' => __( 'Request successful.', 'cache-hive' ),
				'body'    => $response_body,
			);
		}

		$error_message = $response_body['errors'][0]['message'] ?? $response_body['error'] ?? __( 'An unknown error occurred.', 'cache-hive' );

		return array(
			'success' => false,
			'message' => 'Cloudflare API Error: ' . $error_message,
			'body'    => $response_body,
		);
	}
}
