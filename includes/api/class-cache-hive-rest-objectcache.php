<?php
/**
 * Object Cache settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for Object Cache settings.
 */
class Cache_Hive_REST_ObjectCache {

	/**
	 * Gathers information about available server capabilities for object caching.
	 *
	 * @since 1.0.0
	 * @return array An array of server capabilities.
	 */
	private static function get_server_capabilities() {
		return array(
			'clients'     => array(
				'phpredis'  => class_exists( 'Redis' ),
				'predis'    => class_exists( 'Predis\\Client' ),
				'credis'    => class_exists( 'Credis_Client' ),
				'memcached' => class_exists( 'Memcached' ),
			),
			'serializers' => array(
				'igbinary' => extension_loaded( 'igbinary' ),
				'php'      => true,
			),
			'compression' => array(
				'zstd' => extension_loaded( 'zstd' ),
				'lz4'  => extension_loaded( 'lz4' ),
				'lzf'  => extension_loaded( 'lzf' ),
			),
		);
	}

	/**
	 * Retrieves the current object cache settings and status.
	 *
	 * @since 1.0.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_object_cache_settings() {
		// Get settings with wp-config.php overrides already applied.
		$settings = Cache_Hive_Settings::get_settings( true );

		$object_cache_settings = array(
			'objectCacheEnabled'              => ! empty( $settings['objectCacheEnabled'] ),
			'objectCacheMethod'               => $settings['objectCacheMethod'] ?? 'redis',
			'objectCacheHost'                 => $settings['objectCacheHost'] ?? '127.0.0.1',
			'objectCachePort'                 => $settings['objectCachePort'] ?? 6379,
			'objectCacheLifetime'             => $settings['objectCacheLifetime'] ?? 3600,
			'objectCacheUsername'             => $settings['objectCacheUsername'] ?? '',
			'objectCachePassword'             => $settings['objectCachePassword'] ? '********' : '', // Obfuscate password.
			'objectCacheKey'                  => $settings['objectCacheKey'] ?? '',
			'objectCacheGlobalGroups'         => $settings['objectCacheGlobalGroups'] ?? array(),
			'objectCacheNoCacheGroups'        => $settings['objectCacheNoCacheGroups'] ?? array(),
			'objectCachePersistentConnection' => ! empty( $settings['objectCachePersistentConnection'] ),
			'wpConfigOverrides'               => self::get_wp_config_overrides_status(),
		);

		$object_cache_settings['liveStatus'] = function_exists( 'wp_cache_get_info' ) ? wp_cache_get_info() : array(
			'status' => 'Disabled',
			'client' => 'Drop-in not active.',
		);

		$object_cache_settings['serverCapabilities'] = self::get_server_capabilities();
		return new WP_REST_Response( $object_cache_settings, 200 );
	}

	/**
	 * Checks which settings are currently being overridden by wp-config.php constants.
	 *
	 * @return array
	 */
	private static function get_wp_config_overrides_status() {
		$overrides = array();
		$constants = array(
			'objectCacheMethod',
			'client',
			'objectCacheHost',
			'objectCachePort',
			'objectCacheLifetime',
			'timeout',
			'objectCachePersistentConnection',
			'database',
			'objectCacheUsername',
			'objectCachePassword',
			'memcached_user',
			'memcached_pass',
			'tls_options',
		);
		// This uses the same logic as Cache_Hive_Settings but just checks for defined status.
		foreach ( $constants as $key ) {
			$const_name = 'CACHE_HIVE_' . strtoupper( str_replace( 'objectCache', 'OBJECT_CACHE_', $key ) );
			if ( 'client' === $key ) {
				$const_name = 'CACHE_HIVE_OBJECT_CACHE_CLIENT';
			}
			if ( defined( $const_name ) ) {
				$overrides[ $key ] = true;
			}
		}
		return $overrides;
	}

	/**
	 * Updates the object cache settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings and status.
	 */
	public static function update_object_cache_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		// Get current settings from DB and merge with wp-config.php constants.
		// This ensures constants are always respected.
		$settings_to_save = Cache_Hive_Settings::get_settings( true );

		// Now, merge the user's intent from the form, but only for fields
		// that are NOT overridden by wp-config.php constants.
		$overrides = self::get_wp_config_overrides_status();
		foreach ( $params as $key => $value ) {
			if ( ! isset( $overrides[ $key ] ) ) {
				// This key is not locked by a constant, so we can update it.
				// Basic sanitization.
				if ( is_bool( $settings_to_save[ $key ] ?? null ) ) {
					$settings_to_save[ $key ] = ! ! $value;
				} elseif ( is_numeric( $settings_to_save[ $key ] ?? null ) ) {
					$settings_to_save[ $key ] = (int) $value;
				} elseif ( is_array( $settings_to_save[ $key ] ?? null ) ) {
					$settings_to_save[ $key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : array();
				} else {
					$settings_to_save[ $key ] = sanitize_text_field( $value );
				}
			}
		}

		// Now we have the final, authoritative configuration. Let's derive the backend-specific config.
		$final_config = self::build_backend_config( $settings_to_save );

		// Merge the derived backend config back into the main settings.
		$settings_to_save = array_merge( $settings_to_save, $final_config );

		if ( ! empty( $settings_to_save['objectCacheEnabled'] ) ) {
			// Generate a new salt on every successful re-configuration.
			$settings_to_save['objectCacheKey'] = 'ch-' . wp_generate_password( 10, false );

			// Test the connection with the final configuration.
			$test_backend = Cache_Hive_Object_Cache_Factory::create( $settings_to_save );
			if ( ! $test_backend || ! $test_backend->is_connected() ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Could not connect to the object cache backend. Please check your connection details, password, and TLS settings.',
					),
					400
				);
			}
			if ( method_exists( $test_backend, 'close' ) ) {
				$test_backend->close();
			}
		} else {
			$settings_to_save['objectCacheKey'] = '';
		}

		update_option( 'cache_hive_settings', $settings_to_save, 'yes' );
		Cache_Hive_Disk::create_config_file( $settings_to_save );
		Cache_Hive_Object_Cache::manage_dropin( $settings_to_save );

		return self::get_object_cache_settings();
	}

	/**
	 * Builds the final, unified configuration array for the backends.
	 *
	 * @param array $settings The combined settings from DB, UI, and wp-config.php.
	 * @return array The derived configuration for the backend.
	 */
	private static function build_backend_config( $settings ) {
		$config       = array();
		$capabilities = self::get_server_capabilities();
		$method       = $settings['objectCacheMethod'] ?? 'redis';

		// Determine which client to use.
		if ( 'redis' === $method ) {
			$config['client'] = $settings['client'] ?? ( $capabilities['clients']['phpredis'] ? 'phpredis' : ( $capabilities['clients']['predis'] ? 'predis' : 'credis' ) );
		} else {
			$config['client'] = 'memcached';
		}

		// Unify connection details.
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

		// Unify Auth.
		if ( 'memcached' === $config['client'] ) {
			$config['user'] = $settings['memcached_user'] ?? '';
			$config['pass'] = $settings['memcached_pass'] ?? '';
		} else { // Redis.
			$config['user'] = $settings['objectCacheUsername'] ?? '';
			$config['pass'] = $settings['objectCachePassword'] ?? '';
		}

		// Unify other parameters.
		$config['database']    = (int) ( $settings['database'] ?? 0 );
		$config['timeout']     = (float) ( $settings['timeout'] ?? 2.0 );
		$config['persistent']  = ! empty( $settings['objectCachePersistentConnection'] );
		$config['prefetch']    = ! empty( $settings['prefetch'] );
		$config['flush_async'] = ! empty( $settings['flush_async'] );

		// Set advanced options based on capabilities.
		$config['serializer'] = $capabilities['serializers']['igbinary'] ? 'igbinary' : 'php';
		if ( 'phpredis' === $config['client'] ) {
			if ( $capabilities['compression']['zstd'] ) {
				$config['compression'] = 'zstd';
			} elseif ( $capabilities['compression']['lz4'] ) {
				$config['compression'] = 'lz4';
			} elseif ( $capabilities['compression']['lzf'] ) {
				$config['compression'] = 'lzf';
			} else {
				$config['compression'] = 'none';
			}
		}

		// Pass through TLS options.
		if ( isset( $settings['tls_options'] ) ) {
			$config['tls_options'] = $settings['tls_options'];
		}

		return $config;
	}
}
