<?php
/**
 * Exclusions settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

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
	 * @since 1.0.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_exclusions_settings() {
		$settings            = Cache_Hive_Settings::get_settings();
		$exclusions_settings = array(
			'excludeUris'         => $settings['excludeUris'] ?? array(),
			'excludeQueryStrings' => $settings['excludeQueryStrings'] ?? array(),
			'excludeCookies'      => $settings['excludeCookies'] ?? array(),
			'excludeRoles'        => $settings['excludeRoles'] ?? array(),
		);
		return new WP_REST_Response( $exclusions_settings, 200 );
	}

	/**
	 * Updates the cache exclusion settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_exclusions_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$settings         = Cache_Hive_Settings::get_settings();
		$updated_settings = $settings;

		foreach ( $params as $key => $value ) {
			switch ( $key ) {
				case 'excludeUris':
				case 'excludeQueryStrings':
				case 'excludeCookies':
				case 'excludeRoles':
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
		Cache_Hive_Disk::create_config_file( $new_settings );

		// Manually build the response from the known fresh data.
		$response_data = array(
			'excludeUris'         => $new_settings['excludeUris'] ?? array(),
			'excludeQueryStrings' => $new_settings['excludeQueryStrings'] ?? array(),
			'excludeCookies'      => $new_settings['excludeCookies'] ?? array(),
			'excludeRoles'        => $new_settings['excludeRoles'] ?? array(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}
}
