<?php
/**
 * CSS Optimizer settings REST API logic for Cache Hive.
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
 * Handles REST API endpoints for CSS optimization settings.
 */
class Cache_Hive_REST_Optimizers_CSS {

	/**
	 * Retrieves the current CSS optimization settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings      = Cache_Hive_Settings::get_settings();
		$response_data = array(
			'css_minify'                  => (bool) ( $settings['css_minify'] ?? false ),
			'css_combine'                 => (bool) ( $settings['css_combine'] ?? false ),
			'css_combine_external_inline' => (bool) ( $settings['css_combine_external_inline'] ?? false ),
			'css_font_optimization'       => $settings['css_font_optimization'] ?? 'default',
			'css_excludes'                => $settings['css_excludes'] ?? array(),
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the CSS optimization settings.
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
