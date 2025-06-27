<?php
/**
 * Auto Purge settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for auto-purge settings.
 */
class Cache_Hive_REST_AutoPurge {
	/**
	 * Retrieves the current auto-purge settings.
	 *
	 * @since 1.0.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_autopurge_settings() {
		$settings           = Cache_Hive_Settings::get_settings();
		$autopurge_settings = array(
			'autoPurgeEntireSite'      => $settings['autoPurgeEntireSite'] ?? false,
			'autoPurgeFrontPage'       => $settings['autoPurgeFrontPage'] ?? false,
			'autoPurgeHomePage'        => $settings['autoPurgeHomePage'] ?? false,
			'autoPurgePages'           => $settings['autoPurgePages'] ?? false,
			'autoPurgeAuthorArchive'   => $settings['autoPurgeAuthorArchive'] ?? false,
			'autoPurgePostTypeArchive' => $settings['autoPurgePostTypeArchive'] ?? false,
			'autoPurgeYearlyArchive'   => $settings['autoPurgeYearlyArchive'] ?? false,
			'autoPurgeMonthlyArchive'  => $settings['autoPurgeMonthlyArchive'] ?? false,
			'autoPurgeDailyArchive'    => $settings['autoPurgeDailyArchive'] ?? false,
			'autoPurgeTermArchive'     => $settings['autoPurgeTermArchive'] ?? false,
			'purgeOnUpgrade'           => $settings['purgeOnUpgrade'] ?? false,
			'serveStale'               => $settings['serveStale'] ?? false,
			'customPurgeHooks'         => $settings['customPurgeHooks'] ?? array(),
		);
		return new WP_REST_Response( $autopurge_settings, 200 );
	}

	/**
	 * Updates the auto-purge settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_autopurge_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$settings         = Cache_Hive_Settings::get_settings();
		$updated_settings = $settings;

		foreach ( $params as $key => $value ) {
			switch ( $key ) {
				case 'autoPurgeEntireSite':
				case 'autoPurgeFrontPage':
				case 'autoPurgeHomePage':
				case 'autoPurgePages':
				case 'autoPurgeAuthorArchive':
				case 'autoPurgePostTypeArchive':
				case 'autoPurgeYearlyArchive':
				case 'autoPurgeMonthlyArchive':
				case 'autoPurgeDailyArchive':
				case 'autoPurgeTermArchive':
				case 'purgeOnUpgrade':
				case 'serveStale':
					$updated_settings[ $key ] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
					break;
				case 'customPurgeHooks':
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
			'autoPurgeEntireSite'      => $new_settings['autoPurgeEntireSite'] ?? false,
			'autoPurgeFrontPage'       => $new_settings['autoPurgeFrontPage'] ?? false,
			'autoPurgeHomePage'        => $new_settings['autoPurgeHomePage'] ?? false,
			'autoPurgePages'           => $new_settings['autoPurgePages'] ?? false,
			'autoPurgeAuthorArchive'   => $new_settings['autoPurgeAuthorArchive'] ?? false,
			'autoPurgePostTypeArchive' => $new_settings['autoPurgePostTypeArchive'] ?? false,
			'autoPurgeYearlyArchive'   => $new_settings['autoPurgeYearlyArchive'] ?? false,
			'autoPurgeMonthlyArchive'  => $new_settings['autoPurgeMonthlyArchive'] ?? false,
			'autoPurgeDailyArchive'    => $new_settings['autoPurgeDailyArchive'] ?? false,
			'autoPurgeTermArchive'     => $new_settings['autoPurgeTermArchive'] ?? false,
			'purgeOnUpgrade'           => $new_settings['purgeOnUpgrade'] ?? false,
			'serveStale'               => $new_settings['serveStale'] ?? false,
			'customPurgeHooks'         => $new_settings['customPurgeHooks'] ?? array(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}
}
