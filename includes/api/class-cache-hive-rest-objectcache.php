<?php
/**
 * Object Cache settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Settings;
use Cache_Hive\Includes\Object_Cache\Cache_Hive_Object_Cache_Factory;
use Cache_Hive\Includes\Cache_Hive_Object_Cache;
use WP_REST_Request;
use WP_REST_Response;

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
	 * @return array An array of server capabilities.
	 */
	private static function get_server_capabilities() {
		return array(
			'clients'     => array(
				'phpredis'  => class_exists( 'Redis' ),
				'predis'    => class_exists( 'Cache_Hive\\Vendor\\Predis\\Client' ),
				'credis'    => class_exists( 'Cache_Hive\\Vendor\\Credis_Client' ),
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
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings = Cache_Hive_Settings::get_settings( true );

		$response_data = array(
			'object_cache_enabled'               => ! empty( $settings['object_cache_enabled'] ),
			'object_cache_method'                => $settings['object_cache_method'] ?? 'redis',
			'object_cache_client'                => $settings['object_cache_client'] ?? 'phpredis',
			'object_cache_host'                  => $settings['object_cache_host'] ?? '127.0.0.1',
			'object_cache_port'                  => $settings['object_cache_port'] ?? 6379,
			'object_cache_username'              => $settings['object_cache_username'] ?? '',
			'object_cache_password'              => $settings['object_cache_password'] ? '********' : '',
			'object_cache_database'              => $settings['object_cache_database'] ?? 0,
			'object_cache_timeout'               => $settings['object_cache_timeout'] ?? 2.0,
			'object_cache_lifetime'              => $settings['object_cache_lifetime'] ?? 3600,
			'object_cache_key'                   => $settings['object_cache_key'] ?? '',
			'object_cache_global_groups'         => $settings['object_cache_global_groups'] ?? array(),
			'object_cache_no_cache_groups'       => $settings['object_cache_no_cache_groups'] ?? array(),
			'object_cache_tls_options'           => $settings['object_cache_tls_options'] ?? array(),
			'object_cache_persistent_connection' => ! empty( $settings['object_cache_persistent_connection'] ),
			'wp_config_overrides'                => array_fill_keys( Cache_Hive_Settings::get_overridden_keys(), true ),
			'live_status'                        => function_exists( 'wp_cache_get_info' ) ? wp_cache_get_info() : array(
				'status' => 'Disabled',
				'client' => 'Drop-in not active.',
			),
			'server_capabilities'                => self::get_server_capabilities(),
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the object cache settings.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response The response object with the updated settings and status.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params           = $request->get_json_params();
		$settings_to_save = Cache_Hive_Settings::sanitize_settings( $params );
		$live_status      = null;

		if ( ! empty( $settings_to_save['object_cache_enabled'] ) ) {
			$settings_to_save['object_cache_key'] = 'ch-' . wp_generate_password( 10, false );
			$runtime_config                       = Cache_Hive_Settings::get_object_cache_runtime_config( $settings_to_save );
			$test_backend                         = Cache_Hive_Object_Cache_Factory::create( $runtime_config );

			if ( ! $test_backend || ! $test_backend->is_connected() ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Could not connect to the object cache backend. Please check your connection details.', 'cache-hive' ),
					),
					400
				);
			}

			$live_status = $test_backend->get_info();
			$test_backend->close();
		}

		update_option( 'cache_hive_settings', $settings_to_save, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $settings_to_save );
		Cache_Hive_Object_Cache::manage_dropin( $settings_to_save );

		// Invalidate the static settings snapshot to ensure the next get_settings() call is fresh.
		Cache_Hive_Settings::invalidate_settings_snapshot();

		$response_data = self::get_settings()->get_data();
		if ( null !== $live_status ) {
			$response_data['live_status'] = $live_status;
		}

		return new WP_REST_Response( $response_data, 200 );
	}
}
