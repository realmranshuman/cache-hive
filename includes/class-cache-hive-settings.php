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
			// Pass the currently merged settings so the override logic can be method-aware.
			$wp_config_overrides = self::get_wp_config_overrides( $merged_settings );
			$merged_settings     = array_merge( $merged_settings, $wp_config_overrides );

			self::$settings = $merged_settings;
		}
		return self::$settings;
	}

	/**
	 * Gathers all defined object cache constants from wp-config.php.
	 *
	 * This function is "method-aware" and uses a data-driven approach to check
	 * only for the allowed, specific constants for the active cache method.
	 *
	 * @since 1.1.0
	 * @param array $current_settings The current settings array, used to determine the method if not defined in a constant.
	 * @return array An array of settings defined in wp-config.php.
	 */
	private static function get_wp_config_overrides( $current_settings ) {
		$overrides = array();

		// Determine the active method (priority: constant > settings).
		$method = defined( 'CACHE_HIVE_OBJECT_CACHE_METHOD' )
			? constant( 'CACHE_HIVE_OBJECT_CACHE_METHOD' )
			: ( $current_settings['objectCacheMethod'] ?? 'redis' );

		// Handle non-method-specific constants first.
		$simple_constants = array(
			'objectCacheMethod'               => 'CACHE_HIVE_OBJECT_CACHE_METHOD',
			'objectCacheClient'               => 'CACHE_HIVE_OBJECT_CACHE_CLIENT',
			'objectCacheHost'                 => 'CACHE_HIVE_OBJECT_CACHE_HOST',
			'objectCachePort'                 => 'CACHE_HIVE_OBJECT_CACHE_PORT',
			'objectCacheTimeout'              => 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT',
			'objectCacheLifetime'             => 'CACHE_HIVE_OBJECT_CACHE_LIFETIME',
			'objectCachePersistentConnection' => 'CACHE_HIVE_OBJECT_CACHE_PERSISTENT',
		);

		foreach ( $simple_constants as $setting_key => $constant_name ) {
			if ( defined( $constant_name ) ) {
				$overrides[ $setting_key ] = constant( $constant_name );
			}
		}

		// Define the mapping of settings to their method-specific constants.
		$method_specific_constants = array(
			'objectCacheUsername' => array(
				'redis'     => 'CACHE_HIVE_REDIS_USERNAME',
				'memcached' => 'CACHE_HIVE_MEMCACHED_USERNAME',
			),
			'objectCachePassword' => array(
				'redis'     => 'CACHE_HIVE_REDIS_PASSWORD',
				'memcached' => 'CACHE_HIVE_MEMCACHED_PASSWORD',
			),
			'objectCacheDatabase' => array(
				'redis' => 'CACHE_HIVE_REDIS_DATABASE',
			),
		);

		// Process the method-specific constants based on the active method.
		foreach ( $method_specific_constants as $setting_key => $method_map ) {
			// Check if there is a constant defined for the current setting and active method.
			if ( isset( $method_map[ $method ] ) ) {
				$constant_name = $method_map[ $method ];
				if ( defined( $constant_name ) ) {
					$overrides[ $setting_key ] = constant( $constant_name );
				}
			}
		}

		// Handle Redis-specific TLS options.
		$tls_constants = array(
			'ca_cert'     => 'CACHE_HIVE_REDIS_TLS_CA_CERT',
			'verify_peer' => 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER',
		);

		foreach ( $tls_constants as $nested_key => $nested_constant ) {
			if ( defined( $nested_constant ) ) {
				if ( ! isset( $overrides['objectCacheTlsOptions'] ) ) {
					$overrides['objectCacheTlsOptions'] = array();
				}
				$overrides['objectCacheTlsOptions'][ $nested_key ] = constant( $nested_constant );
			}
		}

		return $overrides;
	}

	/**
	 * Returns an array of setting keys that are currently overridden by wp-config.php constants.
	 *
	 * THIS IS THE NEW PUBLIC METHOD
	 *
	 * @since 1.1.1
	 * @return string[] An array of setting keys.
	 */
	public static function get_overridden_keys() {
		// We pass an empty array because at this stage we only care about which keys are defined,
		// and the logic correctly falls back to checking all possibilities.
		return array_keys( self::get_wp_config_overrides( array() ) );
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
		$default_mobile_agents   = array(
			'Mobile',
			'Android',
			'Silk/',
			'Kindle',
			'BlackBerry',
			'Opera Mini',
			'Opera Mobi',
			'iPhone',
			'iPad',
		);
		$default_exclude_uris    = array(
			'/wp-admin/',
			'/wp-login.php',
			'/cart/',
			'/checkout/',
			'/my-account/.*',
		);
		$default_exclude_queries = array(
			'utm_source',
			'utm_medium',
			'utm_campaign',
			'fbclid',
			'preview',
			'edit',
			'_ga',
		);
		$default_exclude_cookies = array(
			'wordpress_logged_in',
			'wp-postpass',
			'woocommerce_cart_hash',
			'comment_author_',
		);
		$default_custom_hooks    = array(
			'switch_theme',
			'deactivated_plugin',
			'activated_plugin',
			'wp_update_nav_menu',
			'wp_update_nav_menu_item',
		);

		return array(
			'enableCache'                     => true,
			'cacheLoggedUsers'                => false,
			'cacheCommenters'                 => false,
			'cacheRestApi'                    => false,
			'cacheMobile'                     => false,
			'mobileUserAgents'                => $default_mobile_agents,
			'publicCacheTTL'                  => 604800,
			'privateCacheTTL'                 => 1800,
			'frontPageTTL'                    => 604800,
			'feedTTL'                         => 604800,
			'restTTL'                         => 604800,
			'autoPurgeEntireSite'             => false,
			'autoPurgeFrontPage'              => true,
			'autoPurgeHomePage'               => false,
			'autoPurgePages'                  => false,
			'autoPurgeAuthorArchive'          => true,
			'autoPurgePostTypeArchive'        => true,
			'autoPurgeYearlyArchive'          => true,
			'autoPurgeMonthlyArchive'         => true,
			'autoPurgeDailyArchive'           => true,
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
			'objectCacheClient'               => 'phpredis',
			'objectCacheHost'                 => '127.0.0.1',
			'objectCachePort'                 => 6379,
			'objectCacheLifetime'             => 3600,
			'objectCacheUsername'             => '',
			'objectCachePassword'             => '',
			'objectCacheDatabase'             => 0,
			'objectCacheTimeout'              => 2.0,
			'objectCacheKey'                  => '',
			'objectCacheGlobalGroups'         => array(
				'blog-details',
				'blog-lookup',
				'global-posts',
				'networks',
				'rss',
				'sites',
				'site-details',
				'site-lookup',
				'site-options',
				'site-transient',
				'users',
				'useremail',
				'userlogins',
				'usermeta',
				'user_meta',
				'userslugs',
				'blog_meta',
				'image_editor',
				'network-queries',
				'site-queries',
				'theme_files',
				'translation_files',
				'user-queries',
			),
			'objectCacheNoCacheGroups'        => array(
				'comment',
				'plugins',
				'theme_json',
				'themes',
				'wc_session_id',
			),
			'objectCacheTlsOptions'           => array(),
			'objectCachePersistentConnection' => false,
			'prefetch'                        => true,
			'flush_async'                     => true,
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
	 * Builds the final, unified runtime configuration array for the backends and drop-in.
	 *
	 * This method translates the stored camelCase settings into the simple key format
	 * expected by the backend clients. It is now method-aware to avoid sending
	 * incorrect parameters to the backend.
	 *
	 * @param array $settings The combined settings from DB, UI, and wp-config.php.
	 * @return array The derived runtime configuration.
	 */
	public static function get_object_cache_runtime_config( $settings ) {
		$config = array();
		$method = $settings['objectCacheMethod'] ?? 'redis';

		// --- Common Configuration ---
		$host = $settings['objectCacheHost'] ?? '127.0.0.1';
		$port = (int) ( $settings['objectCachePort'] ?? ( 'redis' === $method ? 6379 : 11211 ) );

		if ( str_starts_with( $host, 'tls://' ) ) {
			$config['scheme'] = 'tls';
			$config['host']   = substr( $host, 6 );
		} elseif ( 0 === $port || str_starts_with( $host, '/' ) ) {
			$config['scheme'] = 'unix';
			$config['host']   = $host;
		} else {
			$config['scheme'] = 'tcp';
			$config['host']   = $host;
		}
		$config['port'] = $port;

		$config['user']            = $settings['objectCacheUsername'] ?? '';
		$config['pass']            = $settings['objectCachePassword'] ?? '';
		$config['timeout']         = (float) ( $settings['objectCacheTimeout'] ?? 2.0 );
		$config['persistent']      = ! empty( $settings['objectCachePersistentConnection'] );
		$config['prefetch']        = ! empty( $settings['prefetch'] );
		$config['flush_async']     = ! empty( $settings['flush_async'] );
		$config['key_prefix']      = $settings['objectCacheKey'] ?? '';
		$config['lifetime']        = $settings['objectCacheLifetime'] ?? 3600;
		$config['global_groups']   = $settings['objectCacheGlobalGroups'] ?? array();
		$config['no_cache_groups'] = $settings['objectCacheNoCacheGroups'] ?? array();
		$serializers_available     = array( 'igbinary' => extension_loaded( 'igbinary' ) );
		$config['serializer']      = $serializers_available['igbinary'] ? 'igbinary' : 'php';

		// --- Method-Specific Configuration ---
		if ( 'redis' === $method ) {
			$clients_available = array(
				'phpredis' => class_exists( 'Redis' ),
				'predis'   => class_exists( 'Predis\\Client' ),
				'credis'   => class_exists( 'Credis_Client' ),
			);

			$config['client']   = $settings['objectCacheClient'] ?? ( $clients_available['phpredis'] ? 'phpredis' : ( $clients_available['predis'] ? 'predis' : 'credis' ) );
			$config['database'] = (int) ( $settings['objectCacheDatabase'] ?? 0 );

			if ( 'phpredis' === $config['client'] ) {
				$compression_available = array(
					'zstd' => extension_loaded( 'zstd' ),
					'lz4'  => extension_loaded( 'lz4' ),
					'lzf'  => extension_loaded( 'lzf' ),
				);
				if ( $compression_available['zstd'] ) {
					$config['compression'] = 'zstd';
				} elseif ( $compression_available['lz4'] ) {
					$config['compression'] = 'lz4';
				} elseif ( $compression_available['lzf'] ) {
					$config['compression'] = 'lzf';
				} else {
					$config['compression'] = 'none';
				}
			}

			// Handle TLS options with a secure default.
			if ( 'tls' === $config['scheme'] ) {
				$tls_defaults          = array(
					'verify_peer' => true,
					'ca_cert'     => null,
				);
				$config['tls_options'] = wp_parse_args( $settings['objectCacheTlsOptions'] ?? array(), $tls_defaults );
			}
		} elseif ( 'memcached' === $method ) {
			$config['client'] = 'memcached';
			// Memcached does not use database, compression, or TLS options in this context.
			// The config array remains clean of these Redis-specific values.
		}

		return $config;
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
				if ( 'objectCachePassword' === $key && '********' === $value ) {
					// Don't update the password if the obfuscated string is sent back.
					continue;
				}
				$default_value = $defaults[ $key ];
				if ( is_bool( $default_value ) ) {
					$sanitized[ $key ] = (bool) $value;
				} elseif ( is_int( $default_value ) ) {
					$sanitized[ $key ] = absint( $value );
				} elseif ( is_float( $default_value ) ) {
					$sanitized[ $key ] = (float) $value;
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
