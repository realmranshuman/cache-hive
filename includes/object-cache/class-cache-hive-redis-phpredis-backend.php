<?php
/**
 * PhpRedis backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PhpRedis backend for Cache Hive.
 */
class Cache_Hive_Redis_PhpRedis_Backend implements Cache_Hive_Backend_Interface {
	/**
	 * The PhpRedis client instance.
	 *
	 * @var \Redis|null
	 */
	private $redis;

	/**
	 * The backend configuration.
	 *
	 * @var array
	 */
	private $config;

	/**
	 * The secure data transcoder.
	 *
	 * @var Cache_Hive_Transcoder
	 */
	private $transcoder;

	/**
	 * Connection status.
	 *
	 * @var bool
	 */
	private $connected = false;

	/**
	 * Sets up the PhpRedis connection.
	 *
	 * @param array                 $config The backend configuration.
	 * @param Cache_Hive_Transcoder $transcoder The secure transcoder instance.
	 */
	public function __construct( $config, $transcoder ) {
		$this->config     = $config;
		$this->transcoder = $transcoder;
		if ( ! class_exists( 'Redis' ) ) {
			return;
		}

		$this->redis = new \Redis();

		try {
			$this->establish_connection();
			$this->authenticate();
			$this->select_database();
			$this->configure_client();

			$ping            = $this->redis->ping();
			$this->connected = ( '+PONG' === $ping || true === $ping || ( is_array( $ping ) && isset( $ping[0] ) ) ); // Cluster support returns array.
		} catch ( \RedisException $e ) {
			error_log( 'Cache Hive PhpRedis Connection Error: ' . $e->getMessage() );
			$this->connected = false;
		}
	}

	/**
	 * Establishes the connection to the Redis server.
	 */
	private function establish_connection() {
		$host    = $this->config['host'];
		$port    = (int) $this->config['port'];
		$timeout = (float) $this->config['timeout'];
		$context = array();

		if ( 'unix' === $this->config['scheme'] ) {
			$this->redis->connect( $host );
			return;
		}

		if ( 'tls' === $this->config['scheme'] ) {
			$host           = 'tls://' . $host;
			$context['ssl'] = array( 'verify_peer' => $this->config['tls_options']['verify_peer'] ?? true );
			if ( ! empty( $this->config['tls_options']['ca_cert'] ) ) {
				$context['ssl']['cafile'] = $this->config['tls_options']['ca_cert'];
			}
		}

		$persistent_id = 'ch-pconn-' . ( $this->config['database'] ?? 0 );
		if ( ! empty( $this->config['persistent'] ) ) {
			$this->redis->pconnect( $host, $port, $timeout, $persistent_id, 0, 0.0, $context );
		} else {
			$this->redis->connect( $host, $port, $timeout, null, 0, 0.0, $context );
		}
	}

	/**
	 * Authenticates the Redis connection if credentials are provided.
	 *
	 * @throws \RedisException If authentication fails.
	 */
	private function authenticate() {
		if ( empty( $this->config['pass'] ) ) {
			return;
		}
		$auth = ! empty( $this->config['user'] ) ? array( $this->config['user'], $this->config['pass'] ) : $this->config['pass'];
		if ( ! $this->redis->auth( $auth ) ) {
			throw new \RedisException( 'Redis authentication failed.' );
		}
	}

	/**
	 * Selects the Redis database if specified.
	 *
	 * @throws \RedisException If database selection fails.
	 */
	private function select_database() {
		if ( ! empty( $this->config['database'] ) && (int) $this->config['database'] > 0 ) {
			if ( ! $this->redis->select( (int) $this->config['database'] ) ) {
				throw new \RedisException( 'Redis database selection failed.' );
			}
		}
	}

	/**
	 * Configures the Redis client options, such as serializer.
	 */
	private function configure_client() {
		// Set serializer to NONE so we can handle it securely via the transcoder.
		$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE );

		// Configure compression, which is independent of serialization.
		if ( ! empty( $this->config['compression'] ) ) {
			$compression_map = array(
				'zstd' => defined( 'Redis::COMPRESSION_ZSTD' ) ? \Redis::COMPRESSION_ZSTD : null,
				'lz4'  => defined( 'Redis::COMPRESSION_LZ4' ) ? \Redis::COMPRESSION_LZ4 : null,
				'lzf'  => defined( 'Redis::COMPRESSION_LZF' ) ? \Redis::COMPRESSION_LZF : null,
			);
			if ( isset( $compression_map[ $this->config['compression'] ] ) ) {
				$this->redis->setOption( \Redis::OPT_COMPRESSION, $compression_map[ $this->config['compression'] ] );
			}
		}
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
		$value = $this->redis->get( $key );
		if ( false === $value ) {
			return false;
		}
		$decoded = $this->transcoder->decode( $value );
		$found   = ( false !== $decoded );
		return $decoded;
	}

	/**
	 * Retrieves multiple values from the cache.
	 *
	 * @param array $keys An array of keys to retrieve.
	 * @return array An associative array of cached values, keyed by the original keys.
	 */
	public function get_multiple( $keys ) {
		if ( empty( $keys ) || ! is_array( $keys ) || ! $this->is_connected() ) {
			return array(); }
		$values = $this->redis->mget( $keys );
		$result = array();
		if ( is_array( $values ) ) {
			foreach ( $keys as $index => $key ) {
				if ( isset( $values[ $index ] ) && false !== $values[ $index ] ) {
					$decoded = $this->transcoder->decode( $values[ $index ] );
					if ( false !== $decoded ) {
						$result[ $key ] = $decoded;
					}
				}
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
		$encoded_value = $this->transcoder->encode( $value );
		return $ttl > 0 ? $this->redis->setex( $key, $ttl, $encoded_value ) : $this->redis->set( $key, $encoded_value );
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
		$options = array( 'nx' );
		if ( $ttl > 0 ) {
			$options['ex'] = $ttl;
		}
		return $this->redis->set( $key, $this->transcoder->encode( $value ), $options );
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
		$options = array( 'xx' );
		if ( $ttl > 0 ) {
			$options['ex'] = $ttl;
		}
		return $this->redis->set( $key, $this->transcoder->encode( $value ), $options );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		return $this->is_connected() ? (bool) $this->redis->del( $key ) : false;
	}

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to flush asynchronously (if supported).
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		return $this->is_connected() ? $this->redis->flushDb( $async ) : false;
	}

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key The key of the item to increment.
	 * @param int    $offset The amount by which to increment the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->redis->incrBy( $key, $offset ) : false;
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement the item's value.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->redis->decrBy( $key, $offset ) : false;
	}

	/**
	 * Closes the connection to the cache backend.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close() {
		if ( $this->is_connected() && empty( $this->config['persistent'] ) ) {
			$this->redis->close();
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
	 * Retrieves information about the PhpRedis connection and server.
	 *
	 * @return array An associative array containing connection status and server information.
	 */
	public function get_info() {
		if ( ! $this->is_connected() ) {
			return array(
				'status' => 'Not Connected',
				'client' => 'PhpRedis',
			);
		}
		try {
			$info = $this->redis->info();
			return array(
				'status'         => 'Connected',
				'client'         => 'PhpRedis',
				'host'           => $this->config['host'],
				'port'           => $this->config['port'],
				'database'       => $this->config['database'] ?? 0,
				'persistent'     => ! empty( $this->config['persistent'] ),
				'prefetch'       => ! empty( $this->config['prefetch'] ),
				'serializer'     => $this->config['serializer'] ?? 'php',
				'compression'    => $this->config['compression'] ?? 'none',
				'server_version' => $info['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( \RedisException $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'PhpRedis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
