<?php
/**
 * Handles browser cache settings and integration for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Includes\Helpers\Cache_Hive_Server_Rules_Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles browser cache settings and integration.
 */
final class Cache_Hive_Browser_Cache {

	/**
	 * Send browser cache headers if enabled in settings.
	 *
	 * @param array $settings The plugin settings array.
	 */
	public static function send_headers( $settings ) {
		if ( ! empty( $settings['browser_cache_enabled'] ) && is_singular() && ! is_user_logged_in() ) {
			$ttl_seconds = absint( $settings['browser_cache_ttl'] ?? 0 );
			if ( $ttl_seconds > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl_seconds );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl_seconds ) . ' GMT' );
			}
		}
	}

	/**
	 * Delegate server software detection to the helper.
	 *
	 * @return string
	 */
	public static function get_server_software() {
		return Cache_Hive_Server_Rules_Helper::get_server_software();
	}

	/**
	 * Delegate .htaccess rule generation to the helper.
	 *
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_htaccess_rules( $settings ) {
		$rules = Cache_Hive_Server_Rules_Helper::generate_browser_cache_htaccess_rules( $settings );
		if ( empty( $rules ) ) {
			return '';
		}
		return "# BEGIN Cache Hive Browser Cache\n" . $rules . "# END Cache Hive Browser Cache\n";
	}

	/**
	 * Delegate nginx rule generation to the helper.
	 *
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_nginx_rules( $settings ) {
		$rules = Cache_Hive_Server_Rules_Helper::generate_browser_cache_nginx_rules( $settings );
		if ( empty( $rules ) ) {
			return '';
		}
		return "# BEGIN Cache Hive Browser Cache\n" . $rules . "# END Cache Hive Browser Cache\n";
	}

	/**
	 * Delegate .htaccess file update to the helper.
	 *
	 * @param array $settings The plugin settings array.
	 * @return true|WP_Error
	 */
	public static function update_htaccess( $settings ) {
		$server = self::get_server_software();
		if ( 'apache' !== $server && 'litespeed' !== $server ) {
			return true;
		}
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess_file = trailingslashit( get_home_path() ) . '.htaccess';

		$rules = Cache_Hive_Server_Rules_Helper::generate_browser_cache_htaccess_rules( $settings );

		return Cache_Hive_Server_Rules_Helper::update_htaccess( $htaccess_file, 'Cache Hive Browser Cache', $rules );
	}
}
