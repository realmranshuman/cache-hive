<?php
/**
 * Media Optimizer settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for Media optimization settings.
 */
class Cache_Hive_REST_Optimizers_Media {

	/**
	 * Returns the key prefix for Media settings.
	 *
	 * @return string
	 */
	private static function get_prefix() {
		return 'media_';
	}

	/**
	 * Retrieves the current Media optimization settings.
	 *
	 * @since 1.2.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings = Cache_Hive_Settings::get_settings();
		$prefix   = self::get_prefix();

		$media_settings = array(
			'lazyloadImages'        => $settings[ $prefix . 'lazyload_images' ] ?? false,
			'lazyloadIframes'       => $settings[ $prefix . 'lazyload_iframes' ] ?? false,
			'imageExcludes'         => $settings[ $prefix . 'image_excludes' ] ?? array(),
			'iframeExcludes'        => $settings[ $prefix . 'iframe_excludes' ] ?? array(),
			'addMissingSizes'       => $settings[ $prefix . 'add_missing_sizes' ] ?? false,
			'responsivePlaceholder' => $settings[ $prefix . 'responsive_placeholder' ] ?? false,
			'optimizeUploads'       => $settings[ $prefix . 'optimize_uploads' ] ?? false,
			'optimizationQuality'   => $settings[ $prefix . 'optimization_quality' ] ?? 82,
			'autoResizeUploads'     => $settings[ $prefix . 'auto_resize_uploads' ] ?? false,
			'resizeWidth'           => $settings[ $prefix . 'resize_width' ] ?? 0,
			'resizeHeight'          => $settings[ $prefix . 'resize_height' ] ?? 0,
		);

		return new WP_REST_Response( $media_settings, 200 );
	}

	/**
	 * Updates the Media optimization settings.
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

		$key_map = array(
			'lazyloadImages'        => $prefix . 'lazyload_images',
			'lazyloadIframes'       => $prefix . 'lazyload_iframes',
			'imageExcludes'         => $prefix . 'image_excludes',
			'iframeExcludes'        => $prefix . 'iframe_excludes',
			'addMissingSizes'       => $prefix . 'add_missing_sizes',
			'responsivePlaceholder' => $prefix . 'responsive_placeholder',
			'optimizeUploads'       => $prefix . 'optimize_uploads',
			'optimizationQuality'   => $prefix . 'optimization_quality',
			'autoResizeUploads'     => $prefix . 'auto_resize_uploads',
			'resizeWidth'           => $prefix . 'resize_width',
			'resizeHeight'          => $prefix . 'resize_height',
		);

		foreach ( $key_map as $frontend_key => $backend_key ) {
			if ( isset( $params[ $frontend_key ] ) ) {
				$updated_settings[ $backend_key ] = $params[ $frontend_key ];
			}
		}

		$new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
		update_option( 'cache_hive_settings', $new_settings, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $new_settings );

		return self::get_settings();
	}
}
