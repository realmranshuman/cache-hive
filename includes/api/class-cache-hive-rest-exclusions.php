<?php
/**
 * Exclusions settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for cache exclusion settings.
 */
class Cache_Hive_REST_Exclusions {

	/**
	 * Retrieves the current cache exclusion settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'exclude_uris'          => $settings['exclude_uris'] ?? array(),
			'exclude_query_strings' => $settings['exclude_query_strings'] ?? array(),
			'exclude_cookies'       => $settings['exclude_cookies'] ?? array(),
			'exclude_roles'         => $settings['exclude_roles'] ?? array(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the cache exclusion settings.
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

		// This will now correctly return the updated settings.
		return self::get_settings();
	}
}
