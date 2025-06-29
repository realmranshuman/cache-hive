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
	 * Get all settings, merged with defaults and wp-config.php overrides.
	 *
	 * @since 1.0.0
	 * @param bool $force_refresh Whether to force a refresh from the database.
	 * @return array The plugin settings.
	 */
	public static function get_settings( $force_refresh = false ) {
		if ( ! isset( self::$settings ) || $force_refresh ) {
			$db_settings = get_option( 'cache_hive_settings', array() );
			$defaults    = self::get_default_settings();

			// Base settings: DB values parsed over defaults.
			$merged_settings = wp_parse_args( $db_settings, $defaults );

			// Self-correction for migrating old string values to arrays.
			foreach ( $merged_settings as $key => &$value ) {
				if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_string( $value ) ) {
					$value = array_values( array_filter( array_map( 'trim', explode( "\n", $value ) ) ) );
				}
			}

			// Get wp-config.php overrides and merge them on top. They have the final say.
			$wp_config_overrides = self::get_wp_config_overrides();
			$merged_settings     = array_merge( $merged_settings, $wp_config_overrides );

			self::$settings = $merged_settings;
		}
		return self::$settings;
	}

	/**
	 * Gathers all defined object cache constants from wp-config.php.
	 *
	 * @since 1.1.0
	 * @return array An array of settings defined in wp-config.php.
	 */
	private static function get_wp_config_overrides() {
		$overrides = array();
		$constants = array(
			// General.
			'objectCacheMethod'               => 'CACHE_HIVE_OBJECT_CACHE_METHOD', // redis or memcached.
			'client'                          => 'CACHE_HIVE_OBJECT_CACHE_CLIENT', // phpredis, predis, credis.
			'objectCacheHost'                 => 'CACHE_HIVE_OBJECT_CACHE_HOST',
			'objectCachePort'                 => 'CACHE_HIVE_OBJECT_CACHE_PORT',
			'objectCacheLifetime'             => 'CACHE_HIVE_OBJECT_CACHE_LIFETIME',
			'timeout'                         => 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT',
			'objectCachePersistentConnection' => 'CACHE_HIVE_OBJECT_CACHE_PERSISTENT',

			// Redis Specific.
			'database'                        => 'CACHE_HIVE_REDIS_DATABASE',
			'objectCacheUsername'             => 'CACHE_HIVE_REDIS_USERNAME',
			'objectCachePassword'             => 'CACHE_HIVE_REDIS_PASSWORD',

			// Memcached Specific.
			'memcached_user'                  => 'CACHE_HIVE_MEMCACHED_USERNAME',
			'memcached_pass'                  => 'CACHE_HIVE_MEMCACHED_PASSWORD',

			// Simplified TLS Options for Redis.
			'tls_options'                     => array(
				'ca_cert'     => 'CACHE_HIVE_REDIS_TLS_CA_CERT',
				'verify_peer' => 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER',
			),
		);

		foreach ( $constants as $setting_key => $constant_name ) {
			if ( is_array( $constant_name ) ) {
				// Handle nested options like tls_options.
				foreach ( $constant_name as $nested_key => $nested_constant ) {
					if ( defined( $nested_constant ) ) {
						if ( ! isset( $overrides[ $setting_key ] ) ) {
							$overrides[ $setting_key ] = array();
						}
						$overrides[ $setting_key ][ $nested_key ] = constant( $nested_constant );
					}
				}
			} elseif ( defined( $constant_name ) ) {
				$overrides[ $setting_key ] = constant( $constant_name );
			}
		}

		return $overrides;
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
		// All multi-line textareas are now defined as arrays by default.
		$default_mobile_agents   = array( 'Mobile', 'Android', 'Silk/', 'Kindle', 'BlackBerry', 'Opera Mini', 'Opera Mobi', 'iPhone', 'iPad' );
		$default_exclude_uris    = array( '/wp-admin/', '/wp-login.php', '/cart/', '/checkout/', '/my-account/.*' );
		$default_exclude_queries = array( 'utm_source', 'utm_medium', 'utm_campaign', 'fbclid', 'preview', 'edit', '_ga' );
		$default_exclude_cookies = array( 'wordpress_logged_in', 'wp-postpass', 'woocommerce_cart_hash', 'comment_author_' );
		$default_custom_hooks    = array( 'switch_theme', 'deactivated_plugin', 'activated_plugin', 'wp_update_nav_menu', 'wp_update_nav_menu_item' );

		return array(
			'enableCache'                     => true,
			'cacheLoggedUsers'                => false,
			'cacheCommenters'                 => true,
			'cacheRestApi'                    => false,
			'cacheMobile'                     => true,
			'mobileUserAgents'                => $default_mobile_agents,
			'publicCacheTTL'                  => 604800,
			'privateCacheTTL'                 => 1800,
			'frontPageTTL'                    => 604800,
			'feedTTL'                         => 604800,
			'restTTL'                         => 604800,
			'autoPurgeEntireSite'             => false,
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
			'customPurgeHooks'                => $default_custom_hooks,
			'excludeUris'                     => $default_exclude_uris,
			'excludeQueryStrings'             => $default_exclude_queries,
			'excludeCookies'                  => $default_exclude_cookies,
			'excludeRoles'                    => array(),
			'browserCacheEnabled'             => true,
			'browserCacheTTL'                 => 604800,
			'objectCacheEnabled'              => false,
			'objectCacheMethod'               => 'redis',
			'objectCacheHost'                 => '127.0.0.1',
			'objectCachePort'                 => 6379,
			'objectCacheLifetime'             => 3600,
			'objectCacheUsername'             => '',
			'objectCachePassword'             => '',
			'objectCacheKey'                  => '',
			'objectCacheGlobalGroups'         => array(),
			'objectCacheNoCacheGroups'        => array(),
			'objectCachePersistentConnection' => false,
			'prefetch'                        => false,
			'flush_async'                     => false,
			'cloudflare_enabled'              => false,
			'cloudflare_api_method'           => 'token',
			'cloudflare_api_key'              => '',
			'cloudflare_api_token'            => '',
			'cloudflare_email'                => '',
			'cloudflare_domain'               => '',
			'cloudflare_zone_id'              => '',
		);
	}

	/**
	 * Sanitizes settings received from the REST API.
	 *
	 * @since 1.0.0
	 * @param array $input The raw settings array from a specific form.
	 * @return array The fully merged and sanitized settings array.
	 */
	public static function sanitize_settings( $input ) {
		// Start with the current complete settings to preserve unsent data.
		$sanitized = self::get_settings( true ); // Force refresh from DB.
		$defaults  = self::get_default_settings();

		foreach ( $input as $key => $value ) {
			if ( array_key_exists( $key, $defaults ) ) {
				$default_value = $defaults[ $key ];
				if ( is_bool( $default_value ) ) {
					$sanitized[ $key ] = (bool) $value;
				} elseif ( is_int( $default_value ) ) {
					$sanitized[ $key ] = absint( $value );
				} elseif ( is_array( $default_value ) ) {
					$sanitized[ $key ] = is_array( $value ) ? array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) ) : array();
				} else {
					$sanitized[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		return $sanitized;
	}
}
