<?php
/**
 * Auto Purge settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for auto-purge settings.
 */
class Cache_Hive_REST_Autopurge {

	/**
	 * Retrieves the current auto-purge settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'auto_purge_entire_site'       => (bool) ( $settings['auto_purge_entire_site'] ?? false ),
			'auto_purge_front_page'        => (bool) ( $settings['auto_purge_front_page'] ?? true ),
			'auto_purge_home_page'         => (bool) ( $settings['auto_purge_home_page'] ?? false ),
			'auto_purge_pages'             => (bool) ( $settings['auto_purge_pages'] ?? false ),
			'auto_purge_author_archive'    => (bool) ( $settings['auto_purge_author_archive'] ?? true ),
			'auto_purge_post_type_archive' => (bool) ( $settings['auto_purge_post_type_archive'] ?? true ),
			'auto_purge_yearly_archive'    => (bool) ( $settings['auto_purge_yearly_archive'] ?? true ),
			'auto_purge_monthly_archive'   => (bool) ( $settings['auto_purge_monthly_archive'] ?? true ),
			'auto_purge_daily_archive'     => (bool) ( $settings['auto_purge_daily_archive'] ?? true ),
			'auto_purge_term_archive'      => (bool) ( $settings['auto_purge_term_archive'] ?? true ),
			'purge_on_upgrade'             => (bool) ( $settings['purge_on_upgrade'] ?? true ),
			'serve_stale'                  => (bool) ( $settings['serve_stale'] ?? false ),
			'custom_purge_hooks'           => $settings['custom_purge_hooks'] ?? array(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the auto-purge settings.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params       = $request->get_json_params();
		$new_settings = Cache_Hive_Settings::sanitize_settings( $params );

		update_option( 'cache_hive_settings', $new_settings, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $new_settings );

		return self::get_settings();
	}
}
