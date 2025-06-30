<?php
/**
 * Predis backend for Cache Hive object cache.
 *
 * @package Cache
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-backend.php';

/**
 * Predis backend implementation for Cache Hive object cache.
 */
class Cache_Hive_Redis_Predis_Backend implements Cache_Hive_Backend_Interface {
	/**
	 * The Predis client instance.
	 *
	 * @var \Predis\ClientInterface|null
	 */
	private $client;
	/**
	 * The configuration array for the Redis connection.
	 *
	 * @var array
	 */
	private $config;
	/**
	 * Flag indicating the connection status.
	 *
	 * @var bool
	 */
	private $connected = false;

	/**
	 * Constructor for the Predis backend.
	 *
	 * @param array $config Configuration array for the backend.
	 */
	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Predis\\Client' ) ) {
			$this->connected = false;
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
				$parameters['username'] = $this->config['user'];
			}
			if ( ! empty( $this->config['pass'] ) ) {
				$parameters['password'] = $this->config['pass'];
			}
			if ( ! empty( $this->config['persistent'] ) ) {
				$parameters['persistent'] = true;
			}

			if ( 'unix' === $parameters['scheme'] ) {
				$parameters['path'] = $parameters['host'];
				unset( $parameters['host'], $parameters['port'] );
			}

			if ( 'tls' === $parameters['scheme'] && ! empty( $this->config['tls_options'] ) ) {
				$parameters['ssl'] = array(
					'cafile'      => $this->config['tls_options']['ca_cert'] ?? null,
					'verify_peer' => $this->config['tls_options']['verify_peer'] ?? true,
				);
			}

			$this->client = new Predis\Client( $parameters );
			$this->client->connect();
			$this->connected = $this->client->isConnected();
		} catch ( Exception $e ) {
			error_log( 'Cache Hive Predis Connection Error: ' . $e->getMessage() );
			$this->connected = false;
		}
	}

	/**
	 * Unserializes a value if possible.
	 *
	 * @param mixed $value The value to unserialize.
	 * @return mixed The unserialized value or the original value.
	 */
	private function unserialize_value( $value ) {
		if ( is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		// Predis returns strings. Serializer is handled by drop-in, so we must unserialize here.
		$unserialized = @unserialize( $value );
		return ( false !== $unserialized || 'b:0;' === $value ) ? $unserialized : $value;
	}

	/**
	 * Retrieves an item from the cache.
	 *
	 * @param string $key   The key for the item.
	 * @param bool   &$found Pass-by-reference. Is set to true if the key was found in the cache, false otherwise.
	 * @return mixed The cached data, or false if not found or on error.
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
	 * Retrieves multiple items from the cache.
	 *
	 * @param array $keys Array of keys to retrieve.
	 * @return array An associative array of found items. Returns an empty array on error.
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
	 * Stores an item in the cache.
	 *
	 * @param string $key   The key for the item.
	 * @param mixed  $value The data to store.
	 * @param int    $ttl   Time to live in seconds. 0 for no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$ttl              = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		$serialized_value = serialize( $value );
		return 'OK' === $this->client->setex( $key, $ttl, $serialized_value )->getPayload();
	}

	/**
	 * Adds an item to the cache, but only if the key does not already exist.
	 *
	 * @param string $key   The key for the item.
	 * @param mixed  $value The data to store.
	 * @param int    $ttl   Time to live in seconds. 0 for no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function add( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$ttl              = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		$serialized_value = serialize( $value );
		return (bool) $this->client->set( $key, $serialized_value, 'EX', $ttl, 'NX' );
	}

	/**
	 * Replaces an item in the cache, but only if the key already exists.
	 *
	 * @param string $key   The key for the item.
	 * @param mixed  $value The data to store.
	 * @param int    $ttl   Time to live in seconds. 0 for no expiration.
	 * @return bool True on success, false on failure.
	 */
	public function replace( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$ttl              = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		$serialized_value = serialize( $value );
		return (bool) $this->client->set( $key, $serialized_value, 'EX', $ttl, 'XX' );
	}

	/**
	 * Deletes an item from the cache.
	 *
	 * @param string $key The key for the item.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return 0 < $this->client->del( array( $key ) );
	}

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to perform the flush asynchronously.
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return 'OK' === $this->client->flushdb( $async ? 'ASYNC' : null )->getPayload();
	}

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key    The key for the item.
	 * @param int    $offset The amount by which to increment.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->client->incrby( $key, $offset ) : false;
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key    The key for the item.
	 * @param int    $offset The amount by which to decrement.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->client->decrby( $key, $offset ) : false;
	}

	/**
	 * Closes the cache connection.
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
	 * Retrieves information and statistics about the cache backend.
	 *
	 * @return array An array of info and stats.
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
				'scheme'         => $this->config['scheme'],
				'persistent'     => ! empty( $this->config['persistent'] ),
				'prefetch'       => ! empty( $this->config['prefetch'] ),
				'flush_async'    => ! empty( $this->config['flush_async'] ),
				'database'       => $this->config['database'],
				'server_version' => $info['Server']['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['Memory']['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['Server']['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( Exception $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'Predis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
