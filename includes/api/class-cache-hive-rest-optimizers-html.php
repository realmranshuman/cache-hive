<?php
/**
 * HTML Optimizer settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for HTML optimization settings.
 */
class Cache_Hive_REST_Optimizers_HTML {

	/**
	 * Retrieves the current HTML optimization settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'html_minify'          => (bool) ( $settings['html_minify'] ?? false ),
			'html_dns_prefetch'    => $settings['html_dns_prefetch'] ?? array(),
			'html_dns_preconnect'  => $settings['html_dns_preconnect'] ?? array(),
			'auto_dns_prefetch'    => (bool) ( $settings['auto_dns_prefetch'] ?? false ),
			'google_fonts_async'   => (bool) ( $settings['google_fonts_async'] ?? false ),
			'html_keep_comments'   => (bool) ( $settings['html_keep_comments'] ?? false ),
			'remove_emoji_scripts' => (bool) ( $settings['remove_emoji_scripts'] ?? false ),
			'html_remove_noscript' => (bool) ( $settings['html_remove_noscript'] ?? false ),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the HTML optimization settings.
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
