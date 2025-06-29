<?php
/**
 * PhpRedis backend for Cache Hive object cache.
 *
 * @package CacheHive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-backend.php';

if ( ! class_exists( 'Cache_Hive_Redis_PhpRedis_Backend' ) ) {
	/**
	 * PhpRedis backend for Cache Hive.
	 */
	class Cache_Hive_Redis_PhpRedis_Backend implements Cache_Hive_Backend_Interface {
		/**
		 * The Redis client instance.
		 *
		 * @var \Redis|null
		 */
		private $redis;
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
		 * Cache_Hive_Redis_PhpRedis_Backend constructor.
		 *
		 * @param array $config The Redis connection configuration.
		 */
		public function __construct( $config ) {
			$this->config = $config;
			if ( ! class_exists( 'Redis' ) ) {
				$this->connected = false;
				return;
			}

			$this->redis = new \Redis();

			try {
				$this->establish_connection();
				$this->authenticate();
				$this->select_database();
				$this->configure_client();

				$ping            = $this->redis->ping();
				$this->connected = ( '+PONG' === $ping || true === $ping );

			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis Connection Error: ' . $e->getMessage() );
				$this->connected = false;
			}
		}

		/**
		 * Establishes the physical connection to the Redis server.
		 *
		 * @throws \RedisException If connection fails.
		 */
		private function establish_connection() {
			$host    = $this->config['host'];
			$port    = (int) $this->config['port'];
			$timeout = (float) $this->config['timeout'];

			if ( 'unix' === $this->config['scheme'] ) {
				$this->redis->connect( $host );
				return;
			}

			if ( 'tls' === $this->config['scheme'] ) {
				$host = 'tls://' . $host;
			}

			$context = array();
			if ( ! empty( $this->config['tls_options'] ) ) {
				$context['ssl'] = array(
					'cafile'      => $this->config['tls_options']['ca_cert'] ?? null,
					'verify_peer' => $this->config['tls_options']['verify_peer'] ?? true,
					'SNI_enabled' => true,
					'peer_name'   => $this->config['host'],
				);
			}

			if ( ! empty( $this->config['persistent'] ) ) {
				$this->redis->pconnect( $host, $port, $timeout, 'ch-pconn-' . $this->config['database'], 0, 0.0, $context );
			} else {
				$this->redis->connect( $host, $port, $timeout, null, 0, 0.0, $context );
			}
		}

		/**
		 * Handles authentication with the Redis server.
		 *
		 * @throws \RedisException If authentication fails.
		 */
		private function authenticate() {
			$password = $this->config['pass'] ?? null;
			$username = $this->config['user'] ?? null;

			if ( empty( $password ) ) {
				return;
			}

			$auth = ! empty( $username ) ? array( $username, $password ) : $password;

			if ( ! $this->redis->auth( $auth ) ) {
				throw new \RedisException( 'Redis authentication failed.' );
			}
		}

		/**
		 * Selects the Redis database.
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
		 * Configures serializer and compression options.
		 */
		private function configure_client() {
			if ( 0 === strcasecmp( 'igbinary', $this->config['serializer'] ?? '' ) && defined( 'Redis::SERIALIZER_IGBINARY' ) ) {
				$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY );
			} else {
				$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP );
			}

			$compression_map     = array(
				'zstd' => defined( 'Redis::COMPRESSION_ZSTD' ) ? \Redis::COMPRESSION_ZSTD : null,
				'lz4'  => defined( 'Redis::COMPRESSION_LZ4' ) ? \Redis::COMPRESSION_LZ4 : null,
				'lzf'  => defined( 'Redis::COMPRESSION_LZF' ) ? \Redis::COMPRESSION_LZF : null,
			);
			$compression_setting = $this->config['compression'] ?? 'none';
			if ( 'none' !== $compression_setting && isset( $compression_map[ $compression_setting ] ) ) {
				$this->redis->setOption( \Redis::OPT_COMPRESSION, $compression_map[ $compression_setting ] );
			}
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
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				$value = $this->redis->get( $key );
				$found = false !== $value;
				return $value;
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis GET Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
		}

		/**
		 * Retrieves multiple items from the cache.
		 *
		 * @param array $keys Array of keys to retrieve.
		 * @return array An associative array of found items. Returns an empty array on error.
		 */
		public function get_multiple( $keys ) {
			if ( empty( $keys ) || ! is_array( $keys ) ) {
				return array();
			}
			try {
				if ( ! $this->is_connected() ) {
					return array();
				}
				$values = $this->redis->mget( $keys );
				$result = array();
				if ( is_array( $values ) ) {
					foreach ( $keys as $index => $key ) {
						if ( isset( $values[ $index ] ) && false !== $values[ $index ] ) {
							$result[ $key ] = $values[ $index ];
						}
					}
				}
				return $result;
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis MGET Error: ' . $e->getMessage() );
				$this->connected = false;
				return array();
			}
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
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return $ttl > 0 ? $this->redis->setex( $key, $ttl, $value ) : $this->redis->set( $key, $value );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis SET Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
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
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				$options = array( 'nx' );
				if ( $ttl > 0 ) {
					$options['ex'] = $ttl;
				}
				return $this->redis->set( $key, $value, $options );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis ADD Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
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
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				$options = array( 'xx' );
				if ( $ttl > 0 ) {
					$options['ex'] = $ttl;
				}
				return $this->redis->set( $key, $value, $options );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis REPLACE Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
		}

		/**
		 * Deletes an item from the cache.
		 *
		 * @param string $key The key for the item.
		 * @return bool True on success, false on failure.
		 */
		public function delete( $key ) {
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return (bool) $this->redis->del( $key );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis DEL Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
		}

		/**
		 * Increments a numeric item's value.
		 *
		 * @param string $key    The key for the item.
		 * @param int    $offset The amount by which to increment.
		 * @return int|false The new value on success, false on failure.
		 */
		public function increment( $key, $offset ) {
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return $this->redis->incrBy( $key, $offset );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis INCR Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
		}

		/**
		 * Decrements a numeric item's value.
		 *
		 * @param string $key    The key for the item.
		 * @param int    $offset The amount by which to decrement.
		 * @return int|false The new value on success, false on failure.
		 */
		public function decrement( $key, $offset ) {
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return $this->redis->decrBy( $key, $offset );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis DECR Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
		}

		/**
		 * Closes the cache connection.
		 *
		 * @return bool True on success, false on failure.
		 */
		public function close() {
			if ( ! empty( $this->redis ) && empty( $this->config['persistent'] ) ) {
				try {
					$this->redis->close();
				} catch ( \RedisException $e ) {
					error_log( 'Cache Hive PhpRedis CLOSE Error: ' . $e->getMessage() );
				}
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
		 * Flushes the cache.
		 *
		 * @param bool $async When true, performs a non-blocking flush. When false, performs a standard blocking flush.
		 * @return bool True on success, false on failure.
		 */
		public function flush( $async ) {
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return $this->redis->flushDb( $async );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis FLUSH Error: ' . $e->getMessage() );
				$this->connected = false;
				return false;
			}
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
					'client' => 'PhpRedis',
				);
			}
			try {
				$info       = $this->redis->info();
				$serializer = 'php';
				if ( defined( 'Redis::SERIALIZER_IGBINARY' ) && \Redis::SERIALIZER_IGBINARY === $this->redis->getOption( \Redis::OPT_SERIALIZER ) ) {
					$serializer = 'igbinary';
				}
				$compression         = 'none';
				$compression_current = $this->redis->getOption( \Redis::OPT_COMPRESSION );
				if ( defined( 'Redis::COMPRESSION_ZSTD' ) && \Redis::COMPRESSION_ZSTD === $compression_current ) {
					$compression = 'zstd';
				} elseif ( defined( 'Redis::COMPRESSION_LZ4' ) && \Redis::COMPRESSION_LZ4 === $compression_current ) {
					$compression = 'lz4';
				} elseif ( defined( 'Redis::COMPRESSION_LZF' ) && \Redis::COMPRESSION_LZF === $compression_current ) {
					$compression = 'lzf';
				}

				return array(
					'status'         => 'Connected',
					'client'         => 'PhpRedis',
					'host'           => $this->config['host'],
					'port'           => $this->config['port'],
					'scheme'         => $this->config['scheme'],
					'database'       => $this->config['database'] ?? 0,
					'server_version' => $info['redis_version'] ?? 'N/A',
					'memory_usage'   => $info['used_memory_human'] ?? 'N/A',
					'uptime'         => $info['uptime_in_seconds'] ?? 'N/A',
					'serializer'     => $serializer,
					'compression'    => $compression,
					'persistent'     => ! empty( $this->config['persistent'] ),
				);
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive PhpRedis INFO Error: ' . $e->getMessage() );
				$this->connected = false;
				return array(
					'status' => 'Connection Error',
					'client' => 'PhpRedis',
					'error'  => $e->getMessage(),
				);
			}
		}
	}
}
