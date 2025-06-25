<?php
/**
 * Handles all settings for Cache Hive.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final class for managing Cache Hive settings.
 *
 * This class provides a centralized way to get, set, and sanitize
 * all plugin settings, using a static singleton pattern for the settings array.
 *
 * @since 1.0.0
 */
final class Cache_Hive_Settings {

	/**
	 * The array of plugin settings.
	 *
	 * @var array
	 */
	private static $settings;

	/**
	 * Get all settings, merged with defaults.
	 *
	 * @since 1.0.0
	 * @param bool $force_refresh Whether to force a refresh from the database.
	 * @return array The plugin settings.
	 */
	public static function get_settings( $force_refresh = false ) {
		if ( ! isset( self::$settings ) || $force_refresh ) {
			$db_settings    = get_option( 'cache_hive_settings', array() );
			self::$settings = wp_parse_args( $db_settings, self::get_default_settings() );
		}
		return self::$settings;
	}

	/**
	 * Get a specific setting value.
	 *
	 * @since 1.0.0
	 * @param string $key The setting key.
	 * @param mixed  $default_value The default value if not found.
	 * @return mixed The setting value.
	 */
	public static function get( $key, $default_value = null ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}

	/**
	 * Get the default settings structure for the entire plugin.
	 *
	 * @since 1.0.0
	 * @return array The default settings.
	 */
	public static function get_default_settings() {
		// Define the multiline values first for readability.
		$default_mobile_agents = array(
			'Mobile',
			'Android',
			'Silk/',
			'Kindle',
			'BlackBerry',
			'Opera Mini',
			'Opera Mobi',
		);

		$default_exclude_uris = array(
			'/wp-admin/',
			'/wp-login.php',
			'/cart/',
			'/checkout/',
		);

		$default_exclude_queries = array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'fbclid',
		);

		$default_exclude_cookies = array(
			'wordpress_logged_in',
			'wp-postpass',
			'woocommerce_cart_hash',
		);

		$defaults = array(
			// --- Cache Tab ---
			'enableCache'                     => true,
			'cacheLoggedUsers'                => false,
			'cacheCommenters'                 => true,
			'cacheRestApi'                    => false,
			'cacheMobile'                     => true,
			'mobileUserAgents'                => implode( "\n", $default_mobile_agents ),

			// --- TTL Tab ---
			'publicCacheTTL'                  => 604800, // 7 days in seconds.
			'privateCacheTTL'                 => 1800,  // 30 minutes in seconds.
			'frontPageTTL'                    => 604800,
			'feedTTL'                         => 604800,
			'restTTL'                         => 604800,

			// --- Auto Purge Tab ---
			'autoPurgeEntireSite'             => false, // Default to false.
			'autoPurgeFrontPage'              => true,
			'autoPurgeHomePage'               => false,
			'autoPurgePages'                  => true,
			'autoPurgeAuthorArchive'          => false,
			'autoPurgePostTypeArchive'        => true,
			'autoPurgeYearlyArchive'          => false,
			'autoPurgeMonthlyArchive'         => false,
			'autoPurgeDailyArchive'           => false,
			'autoPurgeTermArchive'            => true,
			'purgeOnUpgrade'                  => true,
			'serveStale'                      => false,
			'customPurgeHooks'                => "switch_theme\ndeactivated_plugin\nactivated_plugin\nwp_update_nav_menu\nwp_update_nav_menu_item",

			// --- Exclusions Tab ---
			'excludeUris'                     => implode( "\n", $default_exclude_uris ),
			'excludeQueryStrings'             => implode( "\n", $default_exclude_queries ),
			'excludeCookies'                  => implode( "\n", $default_exclude_cookies ),
			'excludeRoles'                    => array(),

			// --- Browser Cache Tab ---
			'browserCacheEnabled'             => true,
			'browserCacheTTL'                 => 604800, // 7 days in seconds.

			// --- Object Cache Tab ---
			'objectCacheEnabled'              => false,
			'objectCacheMethod'               => 'memcached',
			'objectCacheHost'                 => 'localhost',
			'objectCachePort'                 => '11211',
			'objectCacheLifetime'             => '3600',
			'objectCacheUsername'             => '',
			'objectCachePassword'             => '',
			'objectCacheGlobalGroups'         => '', // New: textarea, newline-delimited.
			'objectCacheNoCacheGroups'        => '', // New: textarea, newline-delimited.
			'objectCachePersistentConnection' => false, // New: boolean.

			// --- Cloudflare Integration ---
			'cloudflare_enabled'              => false,
			'cloudflare_api_method'           => 'token',
			'cloudflare_api_key'              => '',
			'cloudflare_api_token'            => '',
			'cloudflare_email'                => '',
			'cloudflare_domain'               => '',
			'cloudflare_zone_id'              => '',
		);

		return apply_filters( 'cache_hive_default_settings', $defaults );
	}

	/**
	 * Sanitizes settings received from the REST API.
	 *
	 * @since 1.0.0
	 * @param array $input The raw settings array.
	 * @return array The sanitized settings array.
	 */
	public static function sanitize_settings( $input ) {
		$defaults  = self::get_default_settings();
		$sanitized = array();
		// Only allow keys that exist in defaults.
		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $input[ $key ] ) ) {
				$value = $input[ $key ];
				if ( is_bool( $default_value ) ) {
					$sanitized[ $key ] = (bool) $value;
				} elseif ( is_int( $default_value ) ) {
					$sanitized[ $key ] = absint( $value );
				} elseif ( is_array( $default_value ) ) {
					$sanitized[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
				} else {
					// Handle textarea fields that need to preserve line breaks.
					if ( in_array( $key, array( 'objectCacheGlobalGroups', 'objectCacheNoCacheGroups' ), true ) ) {
						if ( is_array( $value ) ) {
							// Sanitize each value and remove empties.
							$lines             = array_filter( array_map( 'sanitize_text_field', $value ) );
							$sanitized[ $key ] = array_values( $lines );
						} else {
							// Accept string, split by newline, trim, sanitize, remove empties.
							$lines             = explode( "\n", $value );
							$lines             = array_filter( array_map( 'trim', $lines ) );
							$lines             = array_filter( array_map( 'sanitize_text_field', $lines ) );
							$sanitized[ $key ] = array_values( $lines );
						}
					} elseif ( in_array( $key, array( 'mobileUserAgents', 'excludeUris', 'excludeQueryStrings', 'excludeCookies', 'customPurgeHooks' ), true ) ) {
						$lines             = explode( "\n", $value );
						$lines             = array_filter( array_map( 'trim', $lines ) );
						$sanitized[ $key ] = implode( "\n", $lines );
					} else {
						$sanitized[ $key ] = sanitize_text_field( $value );
					}
				}
			} else {
				// If a boolean key is missing from input, set it to false.
				if ( is_bool( $default_value ) ) {
					$sanitized[ $key ] = false;
				} else {
					$sanitized[ $key ] = $default_value;
				}
			}
		}
		// Remove any legacy keys from input.
		return $sanitized;
	}
}
