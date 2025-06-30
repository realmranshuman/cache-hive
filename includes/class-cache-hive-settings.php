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
			// General Object Cache.
			'objectCacheMethod'               => 'CACHE_HIVE_OBJECT_CACHE_METHOD',
			'objectCacheClient'               => 'CACHE_HIVE_OBJECT_CACHE_CLIENT',
			'objectCacheHost'                 => 'CACHE_HIVE_OBJECT_CACHE_HOST',
			'objectCachePort'                 => 'CACHE_HIVE_OBJECT_CACHE_PORT',
			'objectCacheUsername'             => 'CACHE_HIVE_OBJECT_CACHE_USERNAME',
			'objectCachePassword'             => 'CACHE_HIVE_OBJECT_CACHE_PASSWORD',
			'objectCacheDatabase'             => 'CACHE_HIVE_OBJECT_CACHE_DATABASE',
			'objectCacheTimeout'              => 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT',
			'objectCacheLifetime'             => 'CACHE_HIVE_OBJECT_CACHE_LIFETIME',
			'objectCachePersistentConnection' => 'CACHE_HIVE_OBJECT_CACHE_PERSISTENT',

			// TLS Options.
			'objectCacheTlsOptions'           => array(
				'ca_cert'     => 'CACHE_HIVE_OBJECT_CACHE_TLS_CA_CERT',
				'verify_peer' => 'CACHE_HIVE_OBJECT_CACHE_TLS_VERIFY_PEER',
			),
		);

		foreach ( $constants as $setting_key => $constant_name ) {
			if ( is_array( $constant_name ) ) {
				// Handle nested options like objectCacheTlsOptions.
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
	 * Returns an array of setting keys that are currently overridden by wp-config.php constants.
	 *
	 * THIS IS THE NEW PUBLIC METHOD
	 *
	 * @since 1.1.1
	 * @return string[] An array of setting keys.
	 */
	public static function get_overridden_keys() {
		return array_keys( self::get_wp_config_overrides() );
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
	 * expected by the backend clients.
	 *
	 * @param array $settings The combined settings from DB, UI, and wp-config.php.
	 * @return array The derived runtime configuration.
	 */
	public static function get_object_cache_runtime_config( $settings ) {
		$config = array();
		$method = $settings['objectCacheMethod'] ?? 'redis';

		// Determine which client to use based on availability, if not specified.
		$clients_available = array(
			'phpredis'  => class_exists( 'Redis' ),
			'predis'    => class_exists( 'Predis\\Client' ),
			'credis'    => class_exists( 'Credis_Client' ),
			'memcached' => class_exists( 'Memcached' ),
		);
		if ( 'redis' === $method ) {
			$config['client'] = $settings['objectCacheClient'] ?? ( $clients_available['phpredis'] ? 'phpredis' : ( $clients_available['predis'] ? 'predis' : 'credis' ) );
		} else {
			$config['client'] = 'memcached';
		}

		// Unify connection details from objectCacheHost.
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

		// Unify Auth, other parameters, and features.
		$config['user']            = $settings['objectCacheUsername'] ?? '';
		$config['pass']            = $settings['objectCachePassword'] ?? '';
		$config['database']        = (int) ( $settings['objectCacheDatabase'] ?? 0 );
		$config['timeout']         = (float) ( $settings['objectCacheTimeout'] ?? 2.0 );
		$config['persistent']      = ! empty( $settings['objectCachePersistentConnection'] );
		$config['prefetch']        = ! empty( $settings['prefetch'] );
		$config['flush_async']     = ! empty( $settings['flush_async'] );
		$config['key_prefix']      = $settings['objectCacheKey'] ?? '';
		$config['lifetime']        = $settings['objectCacheLifetime'] ?? 3600;
		$config['global_groups']   = $settings['objectCacheGlobalGroups'] ?? array();
		$config['no_cache_groups'] = $settings['objectCacheNoCacheGroups'] ?? array();

		// Set advanced options based on server capabilities.
		$serializers_available = array( 'igbinary' => extension_loaded( 'igbinary' ) );
		$config['serializer']  = $serializers_available['igbinary'] ? 'igbinary' : 'php';

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

		// Pass through TLS options.
		if ( isset( $settings['objectCacheTlsOptions'] ) ) {
			$config['tls_options'] = $settings['objectCacheTlsOptions'];
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
