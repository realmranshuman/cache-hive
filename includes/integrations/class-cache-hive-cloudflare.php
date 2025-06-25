<?php
/**
 * Manages all Cloudflare API interactions for Cache Hive.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

/**
 * This class handles credential verification, purging, and syncing settings
 * with the Cloudflare API.
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
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Hooks to sync settings, e.g., when browser cache TTL is changed in the plugin.
		add_action( 'update_option_cache_hive_settings', array( __CLASS__, 'sync_browser_cache_ttl' ), 10, 2 );
	}

	/**
	 * Purges the entire Cloudflare cache for the configured zone.
	 *
	 * @since 1.0.0
	 * @return array An array containing the success status and a message.
	 */
	public static function purge_all() {
		if ( ! Cache_Hive_Settings::get( 'cloudflare_enabled' ) ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare integration is not enabled.',
			);
		}

		$zone_id = Cache_Hive_Settings::get( 'cloudflare_zone_id' );
		if ( empty( $zone_id ) ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare Zone ID is not configured.',
			);
		}

		return self::make_request( "zones/{$zone_id}/purge_cache", 'POST', array( 'purge_everything' => true ) );
	}

	/**
	 * Sets the Cloudflare Development Mode for the configured zone.
	 *
	 * @since 1.0.0
	 * @param bool $status True to turn Dev Mode on, false to turn it off.
	 * @return array An array containing the success status and a message.
	 */
	public static function set_dev_mode( $status ) {
		$zone_id = Cache_Hive_Settings::get( 'cloudflare_zone_id' );
		if ( empty( $zone_id ) ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare Zone ID is not configured.',
			);
		}
		$value = $status ? 'on' : 'off';
		return self::make_request( "zones/{$zone_id}/settings/development_mode", 'PATCH', array( 'value' => $value ) );
	}

	/**
	 * Verifies the configured Cloudflare credentials by making a test API call.
	 *
	 * @since 1.0.0
	 * @return array An array containing the success status and a message.
	 */
	public static function verify_credentials() {
		// A simple way to verify is to try to fetch the zone details.
		$zone_id = Cache_Hive_Settings::get( 'cloudflare_zone_id' );
		if ( empty( $zone_id ) ) {
			return array(
				'success' => false,
				'message' => 'Cloudflare Zone ID is required for verification.',
			);
		}
		$response = self::make_request( "zones/{$zone_id}" );
		if ( $response['success'] ) {
			$response['message'] = 'Successfully connected to Cloudflare.';
		}
		return $response;
	}

	/**
	 * Syncs the browser cache TTL setting with Cloudflare when updated in the plugin.
	 *
	 * @since 1.0.0
	 * @param array $old_settings The old plugin settings.
	 * @param array $new_settings The new plugin settings.
	 */
	public static function sync_browser_cache_ttl( $old_settings, $new_settings ) {
		// Only run if Cloudflare is enabled and the TTL has changed.
		if ( empty( $new_settings['cloudflare_enabled'] ) || $old_settings['browserCacheTTL'] === $new_settings['browserCacheTTL'] ) {
			return;
		}

		$zone_id = $new_settings['cloudflare_zone_id'];
		$ttl     = (int) $new_settings['browserCacheTTL'];

		if ( ! empty( $zone_id ) && $ttl > 0 ) {
			self::make_request( "zones/{$zone_id}/settings/browser_cache_ttl", 'PATCH', array( 'value' => $ttl ) );
		}
	}

	/**
	 * Makes a request to the Cloudflare API.
	 *
	 * @since 1.0.0
	 * @param string $endpoint The API endpoint to request.
	 * @param string $method   The HTTP method (GET, POST, PATCH, etc.).
	 * @param array  $body     The request body for POST/PATCH requests.
	 * @return array An array containing the success status, a message, and the response body.
	 */
	private static function make_request( $endpoint, $method = 'GET', $body = array() ) {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		// Use API Token or API Key based on settings.
		$api_method = Cache_Hive_Settings::get( 'cloudflare_api_method' );
		if ( 'token' === $api_method ) {
			$token = Cache_Hive_Settings::get( 'cloudflare_api_token' );
			if ( empty( $token ) ) {
				return array(
					'success' => false,
					'message' => 'Cloudflare API Token is missing.',
				);
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		} else {
			$email = Cache_Hive_Settings::get( 'cloudflare_email' );
			$key   = Cache_Hive_Settings::get( 'cloudflare_api_key' );
			if ( empty( $email ) || empty( $key ) ) {
				return array(
					'success' => false,
					'message' => 'Cloudflare Email or Global API Key is missing.',
				);
			}
			$headers['X-Auth-Email'] = $email;
			$headers['X-Auth-Key']   = $key;
		}

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

		if ( $response_code >= 200 && $response_code < 300 ) {
			if ( isset( $response_body['success'] ) && true === $response_body['success'] ) {
				return array(
					'success' => true,
					'message' => 'Request successful.',
					'body'    => $response_body,
				);
			}
		}

		// Handle errors from Cloudflare API response.
		$error_message = 'An unknown error occurred.';
		if ( ! empty( $response_body['errors'][0]['message'] ) ) {
			$error_message = 'Cloudflare API Error: ' . $response_body['errors'][0]['message'];
		} elseif ( ! empty( $response_body['error'] ) ) {
			$error_message = 'Cloudflare API Error: ' . $response_body['error'];
		}

		return array(
			'success' => false,
			'message' => $error_message,
			'body'    => $response_body,
		);
	}
}
