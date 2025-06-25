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
		// With the factory handling autoloading, we can rely on a simple class_exists.
		// The manual include helper is no longer needed.
		$clients_available = array(
			'phpredis'  => class_exists( 'Redis' ),
			'predis'    => class_exists( 'Predis\\Client' ),
			'credis'    => class_exists( 'Credis_Client' ),
			'memcached' => class_exists( 'Memcached' ),
		);

		$best_client = null;
		if ( $clients_available['phpredis'] ) {
			$best_client = 'phpredis';
		} elseif ( $clients_available['predis'] ) {
			$best_client = 'predis';
		} elseif ( $clients_available['credis'] ) {
			$best_client = 'credis';
		}

		return array(
			'clients'            => $clients_available,
			'best_client'        => $best_client,
			'serializers'        => array(
				'igbinary_phpredis'  => defined( 'Redis::SERIALIZER_IGBINARY' ),
				'igbinary_memcached' => defined( 'Memcached::HAVE_IGBINARY' ),
				'php'                => true,
			),
			'compression'        => array(
				'zstd_phpredis' => defined( 'Redis::COMPRESSION_ZSTD' ),
				'lz4_phpredis'  => defined( 'Redis::COMPRESSION_LZ4' ),
				'lzf_phpredis'  => defined( 'Redis::COMPRESSION_LZF' ),
			),
			'wp_config_override' => array( 'client' => defined( 'CACHE_HIVE_OBJECT_CACHE_CLIENT' ) ? CACHE_HIVE_OBJECT_CACHE_CLIENT : null ),
		);
	}

	/**
	 * Retrieves the current object cache settings and status.
	 *
	 * @since 1.0.0
	 * @return WP_REST_Response The response object.
	 */
	public static function get_object_cache_settings() {
		$settings = Cache_Hive_Settings::get_settings();

		$object_cache_settings = array(
			'objectCacheEnabled'              => $settings['objectCacheEnabled'] ?? false,
			'objectCacheMethod'               => $settings['objectCacheMethod'] ?? 'redis',
			'objectCacheHost'                 => $settings['objectCacheHost'] ?? '127.0.0.1',
			'objectCachePort'                 => $settings['objectCachePort'] ?? 6379,
			'objectCacheLifetime'             => $settings['objectCacheLifetime'] ?? 3600,
			'objectCacheUsername'             => $settings['objectCacheUsername'] ?? '',
			'objectCachePassword'             => $settings['objectCachePassword'] ?? '',
			'objectCacheGlobalGroups'         => $settings['objectCacheGlobalGroups'] ?? array(),
			'objectCacheNoCacheGroups'        => $settings['objectCacheNoCacheGroups'] ?? array(),
			'objectCachePersistentConnection' => $settings['objectCachePersistentConnection'] ?? false,
		);

		if ( function_exists( 'wp_cache_get_info' ) ) {
			$object_cache_settings['liveStatus'] = wp_cache_get_info();
		} else {
			$object_cache_settings['liveStatus'] = array(
				'status' => 'Disabled',
				'client' => 'Drop-in not active.',
			);
		}

		$object_cache_settings['serverCapabilities'] = self::get_server_capabilities();
		return new WP_REST_Response( $object_cache_settings, 200 );
	}

	/**
	 * Updates the object cache settings.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings and status.
	 */
	public static function update_object_cache_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$current_settings = Cache_Hive_Settings::get_settings();
		$old_method       = $current_settings['objectCacheMethod'] ?? 'redis';

		$no_cache_groups = is_array( $params['objectCacheNoCacheGroups'] ) ? array_map( 'sanitize_text_field', $params['objectCacheNoCacheGroups'] ) : array();
		$no_cache_groups = array_diff( $no_cache_groups, array( 'counts' ) );

		$user_intent = array(
			'objectCacheEnabled'              => ! empty( $params['objectCacheEnabled'] ),
			'objectCacheMethod'               => sanitize_text_field( $params['objectCacheMethod'] ?? 'redis' ),
			'objectCacheHost'                 => sanitize_text_field( $params['objectCacheHost'] ?? '127.0.0.1' ),
			'objectCachePort'                 => intval( $params['objectCachePort'] ?? 6379 ),
			'objectCacheLifetime'             => intval( $params['objectCacheLifetime'] ?? 3600 ),
			'objectCacheUsername'             => sanitize_text_field( $params['objectCacheUsername'] ?? '' ),
			'objectCachePassword'             => $params['objectCachePassword'] ?? '',
			'objectCachePersistentConnection' => ! empty( $params['objectCachePersistentConnection'] ),
			'objectCacheGlobalGroups'         => is_array( $params['objectCacheGlobalGroups'] ) ? array_map( 'sanitize_text_field', $params['objectCacheGlobalGroups'] ) : array(),
			'objectCacheNoCacheGroups'        => array_values( $no_cache_groups ),
		);

		$capabilities = self::get_server_capabilities();
		$final_config = array();

		$client_to_use = ( 'memcached' === $user_intent['objectCacheMethod'] ) ? 'memcached' : $capabilities['best_client'];
		if ( ! empty( $capabilities['wp_config_override']['client'] ) ) {
			$client_to_use = $capabilities['wp_config_override']['client'];
		}
		$final_config['client'] = $client_to_use;

		if ( 'memcached' === $client_to_use ) {
			$final_config['host']        = $user_intent['objectCacheHost'];
			$final_config['port']        = $user_intent['objectCachePort'];
			$final_config['serializer']  = $capabilities['serializers']['igbinary_memcached'] ? 'igbinary' : 'php';
			$final_config['compression'] = 'manual';
		} else {
			$host = $user_intent['objectCacheHost'];
			$port = $user_intent['objectCachePort'];
			if ( 0 === $port || str_starts_with( $host, '/' ) ) {
				$final_config['host']        = $host;
				$final_config['port']        = 0;
				$final_config['scheme']      = 'unix';
				$final_config['tls_enabled'] = false;
			} elseif ( str_starts_with( $host, 'tls://' ) ) {
				$final_config['host']        = substr( $host, 6 );
				$final_config['port']        = $port;
				$final_config['scheme']      = 'tls';
				$final_config['tls_enabled'] = true;
			} else {
				$final_config['host']        = $host;
				$final_config['port']        = $port;
				$final_config['scheme']      = 'tcp';
				$final_config['tls_enabled'] = false;
			}

			$final_config['database'] = defined( 'CACHE_HIVE_OBJECT_CACHE_DATABASE' ) ? (int) CACHE_HIVE_OBJECT_CACHE_DATABASE : 0;

			$final_config['serializer'] = $capabilities['serializers']['igbinary_phpredis'] ? 'igbinary' : 'php';

			if ( 'phpredis' === $client_to_use ) {
				if ( $capabilities['compression']['zstd_phpredis'] ) {
					$final_config['compression'] = 'zstd';
				} elseif ( $capabilities['compression']['lz4_phpredis'] ) {
					$final_config['compression'] = 'lz4';
				} elseif ( $capabilities['compression']['lzf_phpredis'] ) {
						$final_config['compression'] = 'lzf';
				} else {
						$final_config['compression'] = 'none';
				}
			} else {
				$final_config['compression'] = 'none';
			}
		}

		$final_config['prefetch']    = true;
		$final_config['flush_async'] = true;

		$can_be_persistent          = in_array( $client_to_use, array( 'phpredis', 'memcached' ), true );
		$final_config['persistent'] = $user_intent['objectCachePersistentConnection'] && $can_be_persistent;

		$settings_to_save = array_merge( $current_settings, $user_intent, $final_config );

		$live_status_info = array(
			'status' => 'Disabled',
			'client' => 'Drop-in not active.',
		);
		$test_backend     = null;

		if ( ! empty( $settings_to_save['objectCacheEnabled'] ) ) {
			$test_config            = $settings_to_save;
			$test_config['timeout'] = 2;

			$test_backend = Cache_Hive_Object_Cache_Factory::create( $test_config );

			if ( ! $test_backend || ! $test_backend->is_connected() ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Could not connect to the object cache backend. Please check your Host and Port settings.',
					),
					400
				);
			}
		}

		if ( ( $user_intent['objectCacheMethod'] !== $old_method ) && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
			if ( isset( $test_backend ) && $test_backend->is_connected() ) {
				$test_backend->flush( false );
			}
		}

		update_option( 'cache_hive_settings', $settings_to_save, 'yes' );
		Cache_Hive_Disk::create_config_file( $settings_to_save );

		if ( class_exists( 'Cache_Hive_Object_Cache' ) ) {
			Cache_Hive_Object_Cache::manage_dropin( $settings_to_save );
		}

		if ( isset( $test_backend ) && $test_backend->is_connected() ) {
			$live_status_info = $test_backend->get_info();
			if ( empty( $settings_to_save['persistent'] ) && method_exists( $test_backend, 'close' ) ) {
				$test_backend->close();
			}
		}

		$response_data = array_merge(
			$user_intent,
			array(
				'liveStatus'         => $live_status_info,
				'serverCapabilities' => self::get_server_capabilities(),
			)
		);

		return new WP_REST_Response( $response_data, 200 );
	}
}
