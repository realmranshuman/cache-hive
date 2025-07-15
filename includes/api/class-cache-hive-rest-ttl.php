<?php
/**
 * TTL settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Settings;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for TTL settings.
 */
class Cache_Hive_REST_TTL {

	/**
	 * Retrieves the current TTL settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'public_cache_ttl'  => (int) ( $settings['public_cache_ttl'] ?? 3600 ),
			'private_cache_ttl' => (int) ( $settings['private_cache_ttl'] ?? 1800 ),
			'front_page_ttl'    => (int) ( $settings['front_page_ttl'] ?? 3600 ),
			'feed_ttl'          => (int) ( $settings['feed_ttl'] ?? 3600 ),
			'rest_ttl'          => (int) ( $settings['rest_ttl'] ?? 3600 ),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the TTL settings.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params       = $request->get_json_params();
		$new_settings = Cache_Hive_Settings::sanitize_settings( $params );

		update_option( 'cache_hive_settings', $new_settings, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $new_settings );

		// Invalidate the static settings snapshot to ensure the next get_settings() call is fresh.
		Cache_Hive_Settings::invalidate_settings_snapshot();

		return self::get_settings();
	}
}
