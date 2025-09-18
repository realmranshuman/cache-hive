<?php
/**
 * Media Optimizer settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for Media optimization settings.
 */
class Cache_Hive_REST_Optimizers_Media {

	/**
	 * Retrieves the current Media optimization settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'media_lazyload_images'        => (bool) ( $settings['media_lazyload_images'] ?? false ),
			'media_lazyload_iframes'       => (bool) ( $settings['media_lazyload_iframes'] ?? false ),
			'media_image_excludes'         => $settings['media_image_excludes'] ?? array(),
			'media_iframe_excludes'        => $settings['media_iframe_excludes'] ?? array(),
			'media_add_missing_sizes'      => (bool) ( $settings['media_add_missing_sizes'] ?? false ),
			'media_responsive_placeholder' => (bool) ( $settings['media_responsive_placeholder'] ?? false ),
			// Removed: media_optimize_uploads, media_optimization_quality, media_auto_resize_uploads, media_resize_width, media_resize_height
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the Media optimization settings.
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
