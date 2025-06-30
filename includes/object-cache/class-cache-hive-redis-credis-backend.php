<?php
/**
 * Credis backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-backend.php';

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
	 * Sets up the Redis connection using Credis.
	 *
	 * @param array $config The backend configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Credis_Client' ) ) {
			$this->connected = false;
			return;
		}

		try {
			$host = $this->config['host'];
			$port = $this->config['port'];

			if ( 'unix' === $this->config['scheme'] ) {
				$port = null; // Credis uses null port for unix sockets.
			} elseif ( 'tls' === $this->config['scheme'] ) {
				$host = 'tls://' . $host;
			}

			// Note: Credis doesn't support a rich context array for TLS like PhpRedis/Predis.
			// The `tls://` scheme is the primary method.
			// The database is passed directly to the constructor, and the `setPersistent` call is removed.
			// Credis's constructor signature: `__construct($host = '127.0.0.1', $port = 6379, $timeout = null, $persistent = '', $db = 0, $password = null)`.
			$this->client = new Credis_Client( $host, $port, $this->config['timeout'], '', $this->config['database'], null );

			$this->client->connect();

			// Handle authentication.
			$password = $this->config['pass'] ?? null;
			$username = $this->config['user'] ?? null;
			if ( ! empty( $password ) ) {
				// The auth command can throw an exception on failure, which we catch below.
				$auth_params = empty( $username ) ? array( $password ) : array( $username, $password );
				$this->client->__call( 'auth', $auth_params );
			}

			$this->connected = $this->client->isConnected();
		} catch ( Exception $e ) {
			error_log( 'Cache Hive Credis Connection Error: ' . $e->getMessage() );
			$this->connected = false;
		}
	}

	/**
	 * Unserializes a value from Redis.
	 *
	 * @param mixed $value The value to unserialize.
	 * @return mixed The unserialized value.
	 */
	private function unserialize_value( $value ) {
		if ( is_null( $value ) || is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		$unserialized = @unserialize( $value );
		return ( false !== $unserialized || 'b:0;' === $value ) ? $unserialized : $value;
	}

	/**
	 * Retrieves an item from the cache.
	 *
	 * @param string    $key   The key of the item to retrieve.
	 * @param bool|null &$found Whether the key was found in the cache. Passed by reference.
	 * @return mixed The value of the item, or false on failure.
	 */
	public function get( $key, &$found ) {
		$found = false;
		if ( ! $this->is_connected() ) {
			return false;
		}
		$value = $this->client->get( $key );
		if ( false === $value || null === $value ) {
			return false;
		}
		$found = true;
		return $this->unserialize_value( $value );
	}

	/**
	 * Retrieves multiple items from the cache.
	 *
	 * @param string[] $keys Array of keys to retrieve.
	 * @return array Array of found key-value pairs.
	 */
	public function get_multiple( $keys ) {
		if ( empty( $keys ) || ! $this->is_connected() ) {
			return array();
		}
		$values = $this->client->mget( $keys );
		$result = array();
		foreach ( $keys as $i => $key ) {
			if ( isset( $values[ $i ] ) && false !== $values[ $i ] ) {
				$result[ $key ] = $this->unserialize_value( $values[ $i ] );
			}
		}
		return $result;
	}

	/**
	 * Stores an item in the cache.
	 *
	 * @param string $key   The key under which to store the value.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl   Time to live in seconds.
	 * @return bool True on success, false on failure.
	 */
	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$serialized_value = serialize( $value );
		return $this->client->setex( $key, $ttl, $serialized_value );
	}

	/**
	 * Adds an item to the cache if it does not already exist.
	 *
	 * @param string $key   The key under which to store the value.
	 * @param mixed  $value The value to store.
	 * @param int    $ttl   Time to live in seconds.
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
	 * Replaces an item in the cache, but only if the key already exists.
	 *
	 * @param string $key   The key of the item to replace.
	 * @param mixed  $value The new value.
	 * @param int    $ttl   Time to live in seconds.
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
	 * Deletes an item from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		return $this->is_connected() ? $this->client->del( $key ) > 0 : false;
	}

	/**
	 * Flushes the cache database.
	 *
	 * @param bool $async Whether to flush asynchronously.
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		return $this->is_connected() ? $this->client->flushdb( $async ) : false;
	}

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key    The key of the item to increment.
	 * @param int    $offset The amount by which to increment.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->client->incrby( $key, $offset ) : false;
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key    The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->client->decrby( $key, $offset ) : false;
	}

	/**
	 * Closes the connection to the cache.
	 *
	 * @return bool Always returns true.
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
	 * Gets information about the cache backend.
	 *
	 * @return array An array of cache stats and information.
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
				'scheme'         => $this->config['scheme'],
				'persistent'     => false, // Always report false for Credis.
				'prefetch'       => ! empty( $this->config['prefetch'] ),
				'flush_async'    => ! empty( $this->config['flush_async'] ),
				'database'       => $this->config['database'],
				'server_version' => $info['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( Exception $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'Credis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
