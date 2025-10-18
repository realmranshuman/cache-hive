<?php
/**
 * JS Optimizer settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for JS optimization settings.
 */
class Cache_Hive_REST_Optimizers_JS {

	/**
	 * Retrieves the current JS optimization settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'js_minify'                  => (bool) ( $settings['js_minify'] ?? false ),
			'js_combine'                 => (bool) ( $settings['js_combine'] ?? false ),
			'js_combine_external_inline' => (bool) ( $settings['js_combine_external_inline'] ?? false ),
			'js_defer_mode'              => $settings['js_defer_mode'] ?? 'off',
			'js_excludes'                => $settings['js_excludes'] ?? array(),
			'js_defer_excludes'          => $settings['js_defer_excludes'] ?? array(),
			'is_network_admin'           => is_multisite() && is_network_admin(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the JS optimization settings.
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

		// Invalidate the static settings snapshot to ensure the next get_settings() call is fresh.
		Cache_Hive_Settings::invalidate_settings_snapshot();

		return self::get_settings();
	}
}
