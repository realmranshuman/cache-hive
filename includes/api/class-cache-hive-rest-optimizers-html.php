<?php
/**
 * HTML Optimizer settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for HTML optimization settings.
 */
class Cache_Hive_REST_Optimizers_HTML {

	/**
	 * Returns the key prefix for HTML settings.
	 *
	 * @return string
	 */
	private static function get_prefix() {
		return 'html_';
	}

	/**
	 * Retrieves the current HTML optimization settings.
	 *
	 * @since 1.2.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings = Cache_Hive_Settings::get_settings();
		$prefix   = self::get_prefix();

		$html_settings = array(
			'minify'           => $settings[ $prefix . 'minify' ] ?? false,
			'dnsPrefetch'      => $settings[ $prefix . 'dns_prefetch' ] ?? array(),
			'dnsPreconnect'    => $settings[ $prefix . 'dns_preconnect' ] ?? array(),
			'autoDnsPrefetch'  => $settings['auto_dns_prefetch'] ?? false, // Note: unique key.
			'googleFontsAsync' => $settings['google_fonts_async'] ?? false, // Note: unique key.
			'keepComments'     => $settings[ $prefix . 'keep_comments' ] ?? false,
			'removeEmoji'      => $settings['remove_emoji_scripts'] ?? false, // Note: unique key.
			'removeNoscript'   => $settings[ $prefix . 'remove_noscript' ] ?? false,
		);

		return new WP_REST_Response( $html_settings, 200 );
	}

	/**
	 * Updates the HTML optimization settings.
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
			'minify'           => $prefix . 'minify',
			'dnsPrefetch'      => $prefix . 'dns_prefetch',
			'dnsPreconnect'    => $prefix . 'dns_preconnect',
			'autoDnsPrefetch'  => 'auto_dns_prefetch',
			'googleFontsAsync' => 'google_fonts_async',
			'keepComments'     => $prefix . 'keep_comments',
			'removeEmoji'      => 'remove_emoji_scripts',
			'removeNoscript'   => $prefix . 'remove_noscript',
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
