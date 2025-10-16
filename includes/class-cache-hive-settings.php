<?php
/**
 * Handles all settings for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages Cache Hive settings.
 *
 * Provides a centralized way to get, set, and sanitize all plugin settings.
 */
final class Cache_Hive_Settings {

	/**
	 * The array of plugin settings.
	 *
	 * @var array
	 */
	private static $settings;

	/**
	 * Invalidates the current settings snapshot.
	 *
	 * This forces the next call to get_settings() to re-read the settings fresh
	 * from the database, preventing race conditions within a single request.
	 */
	public static function invalidate_settings_snapshot() {
		self::$settings = null;
	}

	/**
	 * Get all settings, merged with defaults and wp-config.php overrides.
	 *
	 * @param bool $force_refresh Whether to force a refresh from the database.
	 * @return array The plugin settings.
	 */
	public static function get_settings( $force_refresh = false ) {
		if ( isset( self::$settings ) && ! $force_refresh ) {
			return self::$settings;
		}

		$db_settings = get_option( 'cache_hive_settings', array() );
		$defaults    = self::get_default_settings();

		$merged_settings = wp_parse_args( $db_settings, $defaults );

		// Self-correction for migrating old string values to arrays.
		foreach ( $merged_settings as $key => &$value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_string( $value ) ) {
				$value = array_values( array_filter( array_map( 'trim', explode( "\n", $value ) ) ) );
			}
		}

		$wp_config_overrides = self::get_wp_config_overrides( $merged_settings );
		self::$settings      = array_merge( $merged_settings, $wp_config_overrides );

		return self::$settings;
	}

	/**
	 * Gathers all defined object cache constants from wp-config.php.
	 *
	 * @param array $current_settings The current settings array.
	 * @return array An array of settings defined in wp-config.php.
	 */
	private static function get_wp_config_overrides( $current_settings ) {
		$overrides = array();
		$method    = defined( 'CACHE_HIVE_OBJECT_CACHE_METHOD' ) ? CACHE_HIVE_OBJECT_CACHE_METHOD : ( $current_settings['object_cache_method'] ?? 'redis' );

		$simple_constants = array(
			'object_cache_method'                => 'CACHE_HIVE_OBJECT_CACHE_METHOD',
			'object_cache_client'                => 'CACHE_HIVE_OBJECT_CACHE_CLIENT',
			'object_cache_host'                  => 'CACHE_HIVE_OBJECT_CACHE_HOST',
			'object_cache_port'                  => 'CACHE_HIVE_OBJECT_CACHE_PORT',
			'object_cache_timeout'               => 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT',
			'object_cache_lifetime'              => 'CACHE_HIVE_OBJECT_CACHE_LIFETIME',
			'object_cache_persistent_connection' => 'CACHE_HIVE_OBJECT_CACHE_PERSISTENT',
		);

		foreach ( $simple_constants as $setting_key => $constant_name ) {
			if ( defined( $constant_name ) ) {
				$overrides[ $setting_key ] = constant( $constant_name );
			}
		}

		$method_specific_constants = array(
			'object_cache_username' => array(
				'redis'     => 'CACHE_HIVE_REDIS_USERNAME',
				'memcached' => 'CACHE_HIVE_MEMCACHED_USERNAME',
			),
			'object_cache_password' => array(
				'redis'     => 'CACHE_HIVE_REDIS_PASSWORD',
				'memcached' => 'CACHE_HIVE_MEMCACHED_PASSWORD',
			),
			'object_cache_database' => array(
				'redis' => 'CACHE_HIVE_REDIS_DATABASE',
			),
		);

		foreach ( $method_specific_constants as $setting_key => $method_map ) {
			if ( isset( $method_map[ $method ] ) && defined( $method_map[ $method ] ) ) {
				$overrides[ $setting_key ] = constant( $method_map[ $method ] );
			}
		}

		$tls_constants = array(
			'ca_cert'     => 'CACHE_HIVE_REDIS_TLS_CA_CERT',
			'verify_peer' => 'CACHE_HIVE_REDIS_TLS_VERIFY_PEER',
		);

		foreach ( $tls_constants as $nested_key => $nested_constant ) {
			if ( defined( $nested_constant ) ) {
				$overrides['object_cache_tls_options'][ $nested_key ] = constant( $nested_constant );
			}
		}

		return $overrides;
	}

	/**
	 * Returns an array of setting keys that are currently overridden by wp-config.php constants.
	 *
	 * @return string[] An array of setting keys.
	 */
	public static function get_overridden_keys() {
		return array_keys( self::get_wp_config_overrides( array() ) );
	}

	/**
	 * Get a specific setting value.
	 *
	 * @param string $key           The setting key.
	 * @param mixed  $default_value The default value if not found.
	 * @return mixed The setting value.
	 */
	public static function get( $key, $default_value = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Get the default settings structure for the entire plugin.
	 *
	 * @return array The default settings.
	 */
	public static function get_default_settings() {
		return array(
			// General Cache Settings.
			'enable_cache'                       => true,
			'cache_logged_users'                 => false,
			'cache_commenters'                   => false,
			'cache_rest_api'                     => false,
			'cache_mobile'                       => false,
			'mobile_user_agents'                 => array(
				'Mobile',
				'Android',
				'Silk/',
				'Kindle',
				'BlackBerry',
				'Opera Mini',
				'Opera Mobi',
				'iPhone',
				'Windows Phone',
				'Nokia',
				'Samsung',
				'Galaxy',
				'Google Pixel',
				'OnePlus',
				'Xiaomi',
				'Firefox Mobile',
				'UC Browser',
				'iPad',
			),
			'serve_stale'                        => false,
			'purge_on_upgrade'                   => true,
			// OS-aware setting for symlink capability. Defaults to true.
			'use_symlinks'                       => true,

			// TTL Settings.
			'public_cache_ttl'                   => 604800,
			'private_cache_ttl'                  => 1800,
			'front_page_ttl'                     => 604800,
			'feed_ttl'                           => 604800,
			'rest_ttl'                           => 604800,

			// Auto Purge Settings.
			'auto_purge_entire_site'             => false,
			'auto_purge_front_page'              => true,
			'auto_purge_home_page'               => false,
			'auto_purge_pages'                   => false,
			'auto_purge_author_archive'          => true,
			'auto_purge_post_type_archive'       => true,
			'auto_purge_yearly_archive'          => true,
			'auto_purge_monthly_archive'         => true,
			'auto_purge_daily_archive'           => true,
			'auto_purge_term_archive'            => true,
			'custom_purge_hooks'                 => array(
				'switch_theme',
				'deactivated_plugin',
				'activated_plugin',
				'wp_update_nav_menu',
				'wp_update_nav_menu_item',
			),

			// Exclusion Settings.
			'exclude_uris'                       => array(
				'/wp-admin/',
				'/wp-login.php',
				'/cart/',
				'/checkout/',
				'/my-account/.*',
			),
			'exclude_query_strings'              => array(
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'fbclid',
				'preview',
				'edit',
				'_ga',
			),
			'exclude_query_strings'              => array(
				'utm_source',
				'utm_medium',
				'utm_campaign',
				'fbclid',
				'preview',
				'edit',
				'_ga',
			),
			'exclude_cookies'                    => array(
				'wordpress_logged_in',
				'wp-postpass',
				'woocommerce_cart_hash',
				'comment_author_',
				'wordpress_sec_',
				'wordpress_test_cookie',
				'wp_woocommerce_session_',
				'woocommerce_items_in_cart',
			),
			'exclude_roles'                      => array(),

			// Browser Cache Settings.
			'browser_cache_enabled'              => false,
			'browser_cache_ttl'                  => 604800,

			// Object Cache Settings.
			'object_cache_enabled'               => false,
			'object_cache_method'                => 'redis',
			'object_cache_client'                => 'phpredis',
			'object_cache_host'                  => '127.0.0.1',
			'object_cache_port'                  => 6379,
			'object_cache_lifetime'              => 3600,
			'object_cache_username'              => '',
			'object_cache_password'              => '',
			'object_cache_database'              => 0,
			'object_cache_timeout'               => 2.0,
			'object_cache_key'                   => '',
			'object_cache_global_groups'         => array(
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
			'object_cache_no_cache_groups'       => array(
				'comment',
				'plugins',
				'theme_json',
				'themes',
				'counts',
				'site-transient',
				'wc_session_id',
			),
			'object_cache_tls_options'           => array(),
			'object_cache_persistent_connection' => false,
			'prefetch'                           => true,
			'flush_async'                        => true,

			// Cloudflare Settings.
			'cloudflare_enabled'                 => false,
			'cloudflare_api_method'              => 'token',
			'cloudflare_api_key'                 => '',
			'cloudflare_api_token'               => '',
			'cloudflare_email'                   => '',
			'cloudflare_domain'                  => '',
			'cloudflare_zone_id'                 => '',

			// Page Optimization - CSS.
			'css_minify'                         => false,
			'css_combine'                        => false,
			'css_combine_external_inline'        => false,
			'css_font_optimization'              => 'default',
			'css_excludes'                       => array(),

			// Page Optimization - JS.
			'js_minify'                          => false,
			'js_combine'                         => false,
			'js_combine_external_inline'         => false,
			'js_defer_mode'                      => 'off',
			'js_excludes'                        => array(),
			'js_defer_excludes'                  => array(),

			// Page Optimization - HTML.
			'html_minify'                        => false,
			'html_dns_prefetch'                  => array(),
			'html_dns_preconnect'                => array(),
			'auto_dns_prefetch'                  => false,
			'google_fonts_async'                 => false,
			'html_keep_comments'                 => false,
			'remove_emoji_scripts'               => false,
			'html_remove_noscript'               => false,

			// Page Optimization - Media.
			'media_lazyload_images'              => false,
			'media_lazyload_iframes'             => false,
			'media_image_excludes'               => array(),
			'media_iframe_excludes'              => array(),
			'media_add_missing_sizes'            => false,
			'media_responsive_placeholder'       => false,

			// Image Optimization Settings.
			'image_optimization_library'         => 'gd',
			'image_optimize_losslessly'          => true,
			'image_optimize_original'            => true,
			'image_next_gen_format'              => 'webp',
			'image_quality'                      => 80,
			'image_delivery_method'              => 'rewrite',
			'image_remove_exif'                  => true,
			'image_auto_resize'                  => false,
			'image_max_width'                    => 1920,
			'image_max_height'                   => 1080,
			'image_batch_processing'             => false,
			'image_batch_size'                   => 10,
			'image_exclude_images'               => array(), // FIX: Changed from '' to array().
			'image_exclude_picture_rewrite'      => array(), // NEW: Added new setting.
			'image_selected_thumbnails'          => array( 'thumbnail', 'medium' ),
			'image_disable_png_gif'              => true,
		);
	}

	/**
	 * Builds the final, unified runtime configuration array for the backends and drop-in.
	 *
	 * @param array $settings The combined settings from DB, UI, and wp-config.php.
	 * @return array The derived runtime configuration.
	 */
	public static function get_object_cache_runtime_config( $settings ) {
		$config = array();
		$method = $settings['object_cache_method'] ?? 'redis';

		$host = $settings['object_cache_host'] ?? '127.0.0.1';
		$port = (int) ( $settings['object_cache_port'] ?? ( 'redis' === $method ? 6379 : 11211 ) );

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

		$config['user']            = $settings['object_cache_username'] ?? '';
		$config['pass']            = $settings['object_cache_password'] ?? '';
		$config['timeout']         = (float) ( $settings['object_cache_timeout'] ?? 2.0 );
		$config['persistent']      = ! empty( $settings['object_cache_persistent_connection'] );
		$config['prefetch']        = ! empty( $settings['prefetch'] );
		$config['flush_async']     = ! empty( $settings['flush_async'] );
		$config['key_prefix']      = $settings['object_cache_key'] ?? '';
		$config['lifetime']        = $settings['object_cache_lifetime'] ?? 3600;
		$config['global_groups']   = $settings['object_cache_global_groups'] ?? array();
		$config['no_cache_groups'] = $settings['object_cache_no_cache_groups'] ?? array();
		$config['serializer']      = extension_loaded( 'igbinary' ) ? 'igbinary' : 'php';

		if ( 'redis' === $method ) {
			$config['client']   = $settings['object_cache_client'] ?? ( class_exists( 'Redis' ) ? 'phpredis' : ( class_exists( 'Predis\\Client' ) ? 'predis' : 'credis' ) );
			$config['database'] = (int) ( $settings['object_cache_database'] ?? 0 );

			if ( 'phpredis' === $config['client'] ) {
				if ( extension_loaded( 'zstd' ) ) {
					$config['compression'] = 'zstd';
				} elseif ( extension_loaded( 'lz4' ) ) {
					$config['compression'] = 'lz4';
				} elseif ( extension_loaded( 'lzf' ) ) {
					$config['compression'] = 'lzf';
				} else {
					$config['compression'] = 'none';
				}
			}

			if ( 'tls' === $config['scheme'] ) {
				$tls_defaults          = array(
					'verify_peer' => true,
					'ca_cert'     => null,
				);
				$config['tls_options'] = wp_parse_args( $settings['object_cache_tls_options'] ?? array(), $tls_defaults );
			}
		} elseif ( 'memcached' === $method ) {
			$config['client'] = 'memcached';
		}

		return $config;
	}

	/**
	 * Sanitizes settings received from the REST API.
	 *
	 * @param array $input The raw settings array from a specific form.
	 * @return array The fully merged and sanitized settings array.
	 */
	public static function sanitize_settings( $input ) {
		$sanitized = self::get_settings( true );
		$defaults  = self::get_default_settings();

		foreach ( $input as $key => $value ) {
			if ( ! array_key_exists( $key, $defaults ) ) {
				continue;
			}

			if ( 'object_cache_password' === $key && '********' === $value ) {
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
				if ( is_string( $value ) ) {
					$value = array_values( array_filter( array_map( 'trim', explode( "\n", $value ) ) ) );
				}
				$sanitized[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Gets the TTL (time to live) for the current page based on context.
	 *
	 * @return int TTL in seconds.
	 */
	public static function get_current_page_ttl() {
		$settings = self::get_settings();
		if ( function_exists( 'is_front_page' ) && ( is_front_page() || is_home() ) ) {
			return (int) ( $settings['front_page_ttl'] ?? 0 );
		}
		if ( function_exists( 'is_feed' ) && is_feed() ) {
			return (int) ( $settings['feed_ttl'] ?? 0 );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return (int) ( $settings['rest_ttl'] ?? 0 );
		}
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return (int) ( $settings['private_cache_ttl'] ?? 0 );
		}
		return (int) ( $settings['public_cache_ttl'] ?? 0 );
	}
}
