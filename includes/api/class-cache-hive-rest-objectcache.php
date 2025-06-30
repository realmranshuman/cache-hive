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
			'objectCacheClient'               => $settings['objectCacheClient'] ?? 'phpredis',
			'objectCacheHost'                 => $settings['objectCacheHost'] ?? '127.0.0.1',
			'objectCachePort'                 => $settings['objectCachePort'] ?? 6379,
			'objectCacheUsername'             => $settings['objectCacheUsername'] ?? '',
			'objectCachePassword'             => $settings['objectCachePassword'] ? '********' : '', // Obfuscate password.
			'objectCacheDatabase'             => $settings['objectCacheDatabase'] ?? 0,
			'objectCacheTimeout'              => $settings['objectCacheTimeout'] ?? 2.0,
			'objectCacheLifetime'             => $settings['objectCacheLifetime'] ?? 3600,
			'objectCacheKey'                  => $settings['objectCacheKey'] ?? '',
			'objectCacheGlobalGroups'         => $settings['objectCacheGlobalGroups'] ?? array(),
			'objectCacheNoCacheGroups'        => $settings['objectCacheNoCacheGroups'] ?? array(),
			'objectCacheTlsOptions'           => $settings['objectCacheTlsOptions'] ?? array(),
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
		// THIS IS THE FIX: Call the new public method and format the response for the frontend.
		$overridden_keys = Cache_Hive_Settings::get_overridden_keys();
		if ( empty( $overridden_keys ) ) {
			return array();
		}
		// The frontend expects an associative array like: { 'objectCacheHost': true, ... }.
		return array_fill_keys( $overridden_keys, true );
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

		// Sanitize all incoming settings against the defaults.
		// This ensures we have a complete and valid settings array.
		$settings_to_save = Cache_Hive_Settings::sanitize_settings( $params );

		// Enforce prefetch and async flush as they are core, non-configurable features.
		$settings_to_save['prefetch']    = true;
		$settings_to_save['flush_async'] = true;

		$live_status = null;

		if ( ! empty( $settings_to_save['objectCacheEnabled'] ) ) {
			// Generate a new salt on every successful re-configuration.
			$settings_to_save['objectCacheKey'] = 'ch-' . wp_generate_password( 10, false );

			// Get the derived runtime config for the backend test. This is NOT saved.
			$runtime_config = Cache_Hive_Settings::get_object_cache_runtime_config( $settings_to_save );

			// Test the connection with the derived runtime configuration.
			$test_backend = Cache_Hive_Object_Cache_Factory::create( $runtime_config );
			if ( ! $test_backend || ! $test_backend->is_connected() ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => 'Could not connect to the object cache backend. Please check your connection details, password, and TLS settings.',
					),
					400
				);
			}

			// Capture the fresh status from the successful test connection.
			$live_status = $test_backend->get_info();

			if ( method_exists( $test_backend, 'close' ) ) {
				$test_backend->close();
			}
		} else {
			$settings_to_save['objectCacheKey'] = '';
			// Define the status for a disabled cache.
			$live_status = array(
				'status' => 'Disabled',
				'client' => 'Drop-in not active.',
			);
		}

		// Save the pure, camelCased settings to the database.
		update_option( 'cache_hive_settings', $settings_to_save, 'yes' );
		// Generate the config and drop-in files from these same pure settings.
		Cache_Hive_Disk::create_config_file( $settings_to_save );
		Cache_Hive_Object_Cache::manage_dropin( $settings_to_save );

		// Get the full response data structure, which now reflects the saved options.
		$response_data = self::get_object_cache_settings()->get_data();

		// Overwrite the (potentially stale) liveStatus from the getter with our fresh,
		// accurate status captured during the connection test.
		if ( null !== $live_status ) {
			$response_data['liveStatus'] = $live_status;
		}

		// Return the complete and accurate state to the frontend.
		return new WP_REST_Response( $response_data, 200 );
	}
}
