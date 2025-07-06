<?php
/**
 * Predis backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Predis backend implementation for Cache Hive object cache.
 */
class Cache_Hive_Redis_Predis_Backend implements Cache_Hive_Backend_Interface {
	/**
	 * The Predis client instance.
	 *
	 * @var \Predis\Client|null
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
	 * Sets up the Predis connection.
	 *
	 * @param array $config The backend configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Cache_Hive\\Vendor\\Predis\\Client' ) ) {
			return;
		}

		try {
			$parameters = array(
				'scheme'   => $this->config['scheme'],
				'host'     => $this->config['host'],
				'port'     => $this->config['port'],
				'database' => $this->config['database'],
				'timeout'  => $this->config['timeout'],
			);
			if ( ! empty( $this->config['user'] ) ) {
				$parameters['username'] = $this->config['user']; }
			if ( ! empty( $this->config['pass'] ) ) {
				$parameters['password'] = $this->config['pass']; }
			if ( ! empty( $this->config['persistent'] ) ) {
				$parameters['persistent'] = true; }
			if ( 'unix' === $parameters['scheme'] ) {
				$parameters['path'] = $parameters['host'];
				unset( $parameters['host'], $parameters['port'] ); }
			if ( 'tls' === $parameters['scheme'] && ! empty( $this->config['tls_options'] ) ) {
				$parameters['ssl'] = array( 'verify_peer' => $this->config['tls_options']['verify_peer'] ?? true );
				if ( ! empty( $this->config['tls_options']['ca_cert'] ) ) {
					$parameters['ssl']['cafile'] = $this->config['tls_options']['ca_cert'];
				}
			}

			$this->client = new \Cache_Hive\Vendor\Predis\Client( $parameters );
			$this->client->connect();
			$this->connected = $this->client->isConnected();
		} catch ( \Exception $e ) {
			error_log( 'Cache Hive Predis Connection Error: ' . $e->getMessage() );
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
			return false; }
		$value = $this->client->get( $key );
		if ( null === $value ) {
			return false; }
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
			return array(); }
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
	 */
	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$ttl = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		return 'OK' === $this->client->setex( $key, $ttl, serialize( $value ) )->getPayload();
	}

	/**
	 * Adds a value to the cache only if the key does not already exist.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 */
	public function add( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$ttl = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		return (bool) $this->client->set( $key, serialize( $value ), 'EX', $ttl, 'NX' );
	}

	/**
	 * Replaces a value in the cache only if the key already exists.
	 *
	 * @param string $key The key to store the value under.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl The time-to-live for the cache item in seconds.
	 */
	public function replace( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$ttl = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		return (bool) $this->client->set( $key, serialize( $value ), 'EX', $ttl, 'XX' );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		return $this->is_connected() ? 0 < $this->client->del( array( $key ) ) : false;
	}

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to flush asynchronously (if supported).
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		if ( ! $this->is_connected() ) {
			return false; }
		return 'OK' === $this->client->flushdb( $async ? 'ASYNC' : null )->getPayload();
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
			$this->client->disconnect();
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
	 * Retrieves information about the Predis connection and server.
	 *
	 * @return array An associative array containing connection status and server information.
	 */
	public function get_info() {
		if ( ! $this->is_connected() ) {
			return array(
				'status' => 'Not Connected',
				'client' => 'Predis',
			);
		}
		try {
			$info = $this->client->info();
			return array(
				'status'         => 'Connected',
				'client'         => 'Predis',
				'host'           => $this->config['host'],
				'port'           => $this->config['port'],
				'database'       => $this->config['database'],
				'persistent'     => ! empty( $this->config['persistent'] ),
				'prefetch'       => ! empty( $this->config['prefetch'] ),
				'serializer'     => $this->config['serializer'] ?? 'php',
				'server_version' => $info['Server']['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['Memory']['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['Server']['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( \Exception $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'Predis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
