<?php
/**
 * Factory for creating the configured object cache backend.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'Cache_Hive_Object_Cache_Factory' ) ) {
	return;
}

/**
 * Factory for creating the configured object cache backend.
 *
 * This class is responsible for instantiating the correct backend class.
 * It assumes that all backend classes have already been loaded by the main plugin file.
 */
class Cache_Hive_Object_Cache_Factory {
	/**
	 * Creates and returns the appropriate cache backend based on configuration.
	 *
	 * @param array $config Configuration array for the backend.
	 * @return Cache_Hive_Backend_Interface The selected backend instance.
	 */
	public static function create( $config ) {
		$log_prefix = '[Cache Hive Object Cache Factory] ';
		$backend    = null;

		// The $config array is the single source of truth for the runtime.
		// It has already been processed to determine the correct client based on the method.
		// We trust its 'client' key directly.
		$client_to_use = $config['client'] ?? null;

		// NOTE: All 'require_once' calls have been removed. The main 'cache-hive.php'
		// file is now responsible for loading all necessary class files.
		// This factory now only checks for dependencies and instantiates the classes.

		switch ( $client_to_use ) {
			case 'phpredis':
				if ( class_exists( 'Redis' ) ) {
					error_log( $log_prefix . 'Using PhpRedis backend.' );
					$backend = new Cache_Hive_Redis_PhpRedis_Backend( $config );
				}
				break;
			case 'predis':
				if ( class_exists( 'Predis\\Client' ) ) {
					error_log( $log_prefix . 'Using Predis backend.' );
					$backend = new Cache_Hive_Redis_Predis_Backend( $config );
				}
				break;
			case 'credis':
				if ( class_exists( 'Credis_Client' ) ) {
					error_log( $log_prefix . 'Using Credis backend.' );
					$backend = new Cache_Hive_Redis_Credis_Backend( $config );
				}
				break;
			case 'memcached':
				if ( class_exists( 'Memcached' ) ) {
					error_log( $log_prefix . 'Using Memcached backend.' );
					$backend = new Cache_Hive_Memcached_Backend( $config );
				}
				break;
		}

		// Fallback if the configured client is not available or connection fails.
		if ( ! $backend || ! $backend->is_connected() ) {
			$client_name = $client_to_use ?? 'configured';
			error_log( $log_prefix . 'Client (' . $client_name . ') failed or not available. Falling back to Array backend.' );
			$backend = new Cache_Hive_Array_Backend( $config );
		} else {
			$status = $backend->is_connected() ? 'CONNECTED' : 'NOT CONNECTED';
			error_log( $log_prefix . 'Backend ' . get_class( $backend ) . ' connection status: ' . $status );
		}

		return $backend;
	}
}
