<?php
/**
 * Factory for creating the configured object cache backend.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
		$client_to_use = $config['client'] ?? null;
		$backend       = null;

		switch ( $client_to_use ) {
			case 'phpredis':
				if ( class_exists( 'Redis' ) ) {
					$backend = new Cache_Hive_Redis_PhpRedis_Backend( $config );
				}
				break;
			case 'predis':
				if ( class_exists( 'Cache_Hive\\Vendor\\Predis\\Client' ) ) {
					$backend = new Cache_Hive_Redis_Predis_Backend( $config );
				}
				break;
			case 'credis':
				if ( class_exists( 'Cache_Hive\\Vendor\\Credis_Client' ) ) {
					$backend = new Cache_Hive_Redis_Credis_Backend( $config );
				}
				break;
			case 'memcached':
				if ( class_exists( 'Memcached' ) ) {
					$backend = new Cache_Hive_Memcached_Backend( $config );
				}
				break;
		}

		if ( ! $backend || ! $backend->is_connected() ) {
			return new Cache_Hive_Array_Backend( $config );
		}

		return $backend;
	}
}
