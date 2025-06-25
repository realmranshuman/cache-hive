<?php
/**
 * Factory for creating the configured object cache backend.
 *
 * This file bootstraps the Composer autoloader and provides the Cache_Hive_Object_Cache_Factory class.
 *
 * @package Cache
 */

/**
 * Class Cache_Hive_Object_Cache_Factory
 *
 * Creates and returns the appropriate cache backend based on configuration.
 */
$autoloader = dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( is_readable( $autoloader ) ) {
	require_once $autoloader;
}

if ( class_exists( 'Cache_Hive_Object_Cache_Factory' ) ) {
	return;
}
/**
 * Factory for creating the configured object cache backend.
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

		// Highest priority: wp-config.php override.
		$forced_client = defined( 'CACHE_HIVE_OBJECT_CACHE_CLIENT' ) ? CACHE_HIVE_OBJECT_CACHE_CLIENT : null;

		$client_to_use = null;
		if ( null !== $forced_client ) {
			$client_to_use = $forced_client;
		} elseif ( isset( $config['client'] ) ) {
			$client_to_use = $config['client'];
		}

		switch ( $client_to_use ) {
			case 'phpredis':
				if ( class_exists( 'Redis' ) ) {
					error_log( $log_prefix . 'Using PhpRedis backend.' );
					require_once __DIR__ . '/backend-phpredis.php';
					$backend = new Cache_Hive_Redis_PhpRedis_Backend( $config );
				}
				break;
			case 'predis':
				if ( class_exists( 'Predis\\Client' ) ) {
					error_log( $log_prefix . 'Using Predis backend.' );
					require_once __DIR__ . '/backend-predis.php';
					$backend = new Cache_Hive_Redis_Predis_Backend( $config );
				}
				break;
			case 'credis':
				if ( class_exists( 'Credis_Client' ) ) {
					error_log( $log_prefix . 'Using Credis backend.' );
					require_once __DIR__ . '/backend-credis.php';
					$backend = new Cache_Hive_Redis_Credis_Backend( $config );
				}
				break;
			case 'memcached':
				if ( class_exists( 'Memcached' ) ) {
					error_log( $log_prefix . 'Using Memcached backend.' );
					require_once __DIR__ . '/backend-memcached.php';
					$backend = new Cache_Hive_Memcached_Backend( $config );
				}
				break;
		}

		// Fallback if the configured client is not available for some reason.
		if ( ! $backend || ! $backend->is_connected() ) {
			error_log( $log_prefix . 'Configured client (' . $client_to_use . ') failed or not found. Falling back to Array backend.' );
			require_once __DIR__ . '/backend-array.php';
			$backend = new Cache_Hive_Array_Backend( $config );
		} else {
			$status = $backend->is_connected() ? 'CONNECTED' : 'NOT CONNECTED';
			error_log( $log_prefix . 'Backend ' . get_class( $backend ) . ' connection status: ' . $status );
		}

		return $backend;
	}
}
