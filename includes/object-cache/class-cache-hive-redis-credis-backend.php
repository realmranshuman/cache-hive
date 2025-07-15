<?php
/**
 * Credis backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credis backend for the Cache Hive object cache.
 */
class Cache_Hive_Redis_Credis_Backend implements Cache_Hive_Backend_Interface {
	/**
	 * The Credis client instance.
	 *
	 * @var \Credis_Client|null
	 */
	private $client;
	/**
	 * The backend configuration.
	 *
	 * @var array
	 */
	private $config;
	/**
	 * Connection status.
	 *
	 * @var bool
	 */
	private $connected = false;

	/**
	 * Sets up the Credis connection.
	 *
	 * @param array $config The backend configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Cache_Hive\\Vendor\\Credis_Client' ) ) {
			// Fallback to Array backend if Credis_Client is not available.
			// This is handled by the factory, so we just return here.
			return;
		}

		try {
			$host = 'unix' === $this->config['scheme'] ? $this->config['host'] : ( 'tls' === $this->config['scheme'] ? 'tls://' . $this->config['host'] : $this->config['host'] );
			$port = 'unix' === $this->config['scheme'] ? null : $this->config['port'];

			$this->client = new \Credis_Client( $host, $port, $this->config['timeout'], '', $this->config['database'], null );
			$this->client->connect();

			if ( ! empty( $this->config['pass'] ) ) {
				$auth_params = ! empty( $this->config['user'] ) ? array( $this->config['user'], $this->config['pass'] ) : array( $this->config['pass'] );
				$this->client->__call( 'auth', $auth_params );
			}

			$this->connected = $this->client->isConnected();
		} catch ( \Exception $e ) {
			error_log( 'Cache Hive Credis Connection Error: ' . $e->getMessage() );
			$this->connected = false;
		}
	}

	/**
	 * Unserializes a value if it's a serialized string.
	 *
	 * @param mixed $value The value to unserialize.
	 * @return mixed The unserialized value or the original value if not serialized.
	 */
	private function unserialize_value( $value ) {
		if ( is_string( $value ) ) {
			$unserialized = @unserialize( $value );
			return ( false !== $unserialized || 'b:0;' === $value ) ? $unserialized : $value;
		}
		return $value;
	}

	/**
	 * Retrieves a value from the cache.
	 *
	 * @param string $key The key to retrieve.
	 * @param bool   $found Whether the key was found in the cache.
	 * @return mixed The cached value, or false if not found.
	 */
	public function get( $key, &$found ) {
		$found = false;
		if ( ! $this->is_connected() ) {
			return false;
		}
		$value = $this->client->get( $key );
		if ( null === $value ) {
			return false;
		}
		$found = true;
		return $this->unserialize_value( $value );
	}

	/**
	 * Retrieves multiple values from the cache.
	 *
	 * @param array $keys An array of keys to retrieve.
	 * @return array An associative array of cached values, keyed by the original keys.
	 */
	public function get_multiple( $keys ) {
		if ( empty( $keys ) || ! $this->is_connected() ) {
			return array();
		}
		$values = $this->client->mget( $keys );
		$result = array();
		foreach ( $keys as $i => $key ) {
			if ( isset( $values[ $i ] ) && null !== $values[ $i ] ) {
				$result[ $key ] = $this->unserialize_value( $values[ $i ] );
			}
		}
		return $result;
	}

	/**
	 * Stores a value in the cache.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->client->setex( $key, $ttl, serialize( $value ) );
	}

	/**
	 * Adds a value to the cache only if the key does not already exist.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->client->set(
			$key,
			serialize( $value ),
			array(
				'nx',
				'ex' => $ttl,
			)
		);
	}

	/**
	 * Replaces a value in the cache only if the key already exists.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->client->set(
			$key,
			serialize( $value ),
			array(
				'xx',
				'ex' => $ttl,
			)
		);
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		return $this->is_connected() ? $this->client->del( $key ) > 0 : false;
	}

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to flush asynchronously (if supported).
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		return $this->is_connected() ? $this->client->flushdb( $async ) : false;
	}

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key The key of the item to increment.
	 * @param int    $offset The amount by which to increment the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->client->incrby( $key, $offset ) : false;
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->client->decrby( $key, $offset ) : false;
	}

	/**
	 * Closes the connection to the cache backend.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close() {
		if ( $this->client && $this->client->isConnected() ) {
			$this->client->close();
		}
		$this->connected = false;
		return true;
	}

	/**
	 * Checks if the cache backend is connected.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_connected() {
		return $this->connected;
	}

	/**
	 * Retrieves information about the Credis connection and server.
	 *
	 * @return array An associative array containing connection status and server information.
	 */
	public function get_info() {
		if ( ! $this->is_connected() ) {
			return array(
				'status' => 'Not Connected',
				'client' => 'Credis',
			);
		}
		try {
			$info = $this->client->info();
			return array(
				'status'         => 'Connected',
				'client'         => 'Credis',
				'host'           => $this->config['host'],
				'port'           => $this->config['port'],
				'database'       => $this->config['database'],
				'persistent'     => ! empty( $this->config['persistent'] ),
				'prefetch'       => ! empty( $this->config['prefetch'] ),
				'serializer'     => $this->config['serializer'] ?? 'php',
				'server_version' => $info['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( \Exception $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'Credis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
