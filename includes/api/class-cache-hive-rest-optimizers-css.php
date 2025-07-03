<?php
/**
 * CSS Optimizer settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for CSS optimization settings.
 */
class Cache_Hive_REST_Optimizers_CSS {

	/**
	 * Returns the key prefix for CSS settings.
	 *
	 * @return string
	 */
	private static function get_prefix() {
		return 'css_';
	}

	/**
	 * Retrieves the current CSS optimization settings.
	 *
	 * @since 1.2.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings = Cache_Hive_Settings::get_settings();
		$prefix   = self::get_prefix();

		$css_settings = array(
			'minify'                => $settings[ $prefix . 'minify' ] ?? false,
			'combine'               => $settings[ $prefix . 'combine' ] ?? false,
			'combineExternalInline' => $settings[ $prefix . 'combine_external_inline' ] ?? false,
			'fontOptimization'      => $settings[ $prefix . 'font_optimization' ] ?? 'default',
			'excludes'              => $settings[ $prefix . 'excludes' ] ?? array(),
		);

		return new WP_REST_Response( $css_settings, 200 );
	}

	/**
	 * Updates the CSS optimization settings.
	 *
	 * @since 1.2.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$settings         = Cache_Hive_Settings::get_settings();
		$updated_settings = $settings;
		$prefix           = self::get_prefix();

		// Map frontend keys to backend setting keys.
		$key_map = array(
			'minify'                => $prefix . 'minify',
			'combine'               => $prefix . 'combine',
			'combineExternalInline' => $prefix . 'combine_external_inline',
			'fontOptimization'      => $prefix . 'font_optimization',
			'excludes'              => $prefix . 'excludes',
		);

		foreach ( $key_map as $frontend_key => $backend_key ) {
			if ( isset( $params[ $frontend_key ] ) ) {
				$updated_settings[ $backend_key ] = $params[ $frontend_key ];
			}
		}

		$new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
		update_option( 'cache_hive_settings', $new_settings, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $new_settings );

		// Return the new state of this section for optimistic UI updates.
		return self::get_settings();
	}
}
