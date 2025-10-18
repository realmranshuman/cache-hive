<?php
/**
 * Cloudflare settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for Cloudflare settings.
 */
class Cache_Hive_REST_Cloudflare {

	/**
	 * Retrieves the Cloudflare settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'cloudflare_enabled'   => (bool) ( $settings['cloudflare_enabled'] ?? false ),
			'cloudflare_api_token' => $settings['cloudflare_api_token'] ? '********' : '', // Never expose the token.
			'cloudflare_zone_id'   => $settings['cloudflare_zone_id'] ?? '',
			'is_network_admin'     => is_multisite() && is_network_admin(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the Cloudflare settings.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$new_settings     = Cache_Hive_Settings::sanitize_settings( $params );
		$is_network_admin = is_multisite() && is_network_admin();

		if ( $is_network_admin ) {
			update_site_option( 'cache_hive_settings', $new_settings );
		} else {
			update_option( 'cache_hive_settings', $new_settings, 'yes' );
		}

		Cache_Hive_Lifecycle::create_config_file( $new_settings );
		Cache_Hive_Settings::invalidate_settings_snapshot();

		return self::get_settings();
	}
}
