<?php
/**
 * Cache settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for general cache settings.
 */
class Cache_Hive_REST_Cache {

	/**
	 * Retrieves the general cache settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'enable_cache'       => (bool) ( $settings['enable_cache'] ?? false ),
			'cache_logged_users' => (bool) ( $settings['cache_logged_users'] ?? false ),
			'cache_commenters'   => (bool) ( $settings['cache_commenters'] ?? false ),
			'cache_rest_api'     => (bool) ( $settings['cache_rest_api'] ?? false ),
			'cache_mobile'       => (bool) ( $settings['cache_mobile'] ?? false ),
			'mobile_user_agents' => $settings['mobile_user_agents'] ?? array(),
			'is_network_admin'   => is_multisite() && is_network_admin(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the general cache settings.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		// Get old settings to see if the main cache toggle has changed.
		$old_settings = Cache_Hive_Settings::get_settings( true );

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

		// FEATURE IMPLEMENTATION: Manage the advanced-cache.php drop-in dynamically.
		$old_enabled = ! empty( $old_settings['enable_cache'] );
		$new_enabled = ! empty( $new_settings['enable_cache'] );

		if ( $new_enabled !== $old_enabled ) {
			if ( $new_enabled ) {
				// If caching is being enabled, set up the environment.
				Cache_Hive_Lifecycle::setup_environment();
			} else {
				// If caching is being disabled, clean up the environment.
				Cache_Hive_Lifecycle::cleanup_environment();
			}
		}

		return self::get_settings();
	}
}
