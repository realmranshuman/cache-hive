<?php
/**
 * TTL settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

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
	 * @since 1.0.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_ttl_settings() {
		$settings     = Cache_Hive_Settings::get_settings();
		$ttl_settings = array(
			'publicCacheTTL'  => $settings['publicCacheTTL'] ?? 3600,
			'privateCacheTTL' => $settings['privateCacheTTL'] ?? 3600,
			'frontPageTTL'    => $settings['frontPageTTL'] ?? 3600,
			'feedTTL'         => $settings['feedTTL'] ?? 3600,
			'restTTL'         => $settings['restTTL'] ?? 3600,
		);
		return new WP_REST_Response( $ttl_settings, 200 );
	}

	/**
	 * Updates the TTL settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_ttl_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$settings         = Cache_Hive_Settings::get_settings();
		$updated_settings = $settings;

		foreach ( $params as $key => $value ) {
			switch ( $key ) {
				case 'publicCacheTTL':
				case 'privateCacheTTL':
				case 'frontPageTTL':
				case 'feedTTL':
				case 'restTTL':
					$updated_settings[ $key ] = intval( $value );
					break;
				default:
			}
		}

		$new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
		update_option( 'cache_hive_settings', $new_settings, 'yes' );
		Cache_Hive_Disk::create_config_file( $new_settings );

		// Manually build the response from the known fresh data.
		$response_data = array(
			'publicCacheTTL'  => $new_settings['publicCacheTTL'] ?? 3600,
			'privateCacheTTL' => $new_settings['privateCacheTTL'] ?? 3600,
			'frontPageTTL'    => $new_settings['frontPageTTL'] ?? 3600,
			'feedTTL'         => $new_settings['feedTTL'] ?? 3600,
			'restTTL'         => $new_settings['restTTL'] ?? 3600,
		);
		return new WP_REST_Response( $response_data, 200 );
	}
}
