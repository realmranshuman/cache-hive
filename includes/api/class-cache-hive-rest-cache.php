<?php
/**
 * Cache settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

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
	 * @since 1.0.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_cache_settings() {
		$settings       = Cache_Hive_Settings::get_settings();
		$cache_settings = array(
			'enableCache'      => $settings['enableCache'] ?? false,
			'cacheLoggedUsers' => $settings['cacheLoggedUsers'] ?? false,
			'cacheCommenters'  => $settings['cacheCommenters'] ?? false,
			'cacheRestApi'     => $settings['cacheRestApi'] ?? false,
			'cacheMobile'      => $settings['cacheMobile'] ?? false,
			'mobileUserAgents' => $settings['mobileUserAgents'] ?? array(),
		);
		return new WP_REST_Response( $cache_settings, 200 );
	}

	/**
	 * Updates the general cache settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_cache_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$settings         = Cache_Hive_Settings::get_settings();
		$updated_settings = $settings;

		foreach ( $params as $key => $value ) {
			switch ( $key ) {
				case 'enableCache':
				case 'cacheLoggedUsers':
				case 'cacheCommenters':
				case 'cacheRestApi':
				case 'cacheMobile':
					$updated_settings[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'mobileUserAgents':
					if ( is_array( $value ) ) {
						$updated_settings[ $key ] = array_map( 'sanitize_text_field', $value );
					} else {
						$updated_settings[ $key ] = array();
					}
					break;
				default:
					continue 2;
			}
		}

		$new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
		update_option( 'cache_hive_settings', $new_settings, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $new_settings );

		// Manually build the response from the known fresh data. Do not call the get function.
		$response_data = array(
			'enableCache'      => $new_settings['enableCache'] ?? false,
			'cacheLoggedUsers' => $new_settings['cacheLoggedUsers'] ?? false,
			'cacheCommenters'  => $new_settings['cacheCommenters'] ?? false,
			'cacheRestApi'     => $new_settings['cacheRestApi'] ?? false,
			'cacheMobile'      => $new_settings['cacheMobile'] ?? false,
			'mobileUserAgents' => $new_settings['mobileUserAgents'] ?? array(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}
}
