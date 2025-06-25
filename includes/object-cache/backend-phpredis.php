<?php
/**
 * PhpRedis backend for Cache Hive object cache.
 *
 * @package CacheHive
 */

/**
 * Class Cache_Hive_Redis_PhpRedis_Backend
 *
 * Handles PhpRedis object cache implementation logic for Cache Hive.
 */
require_once __DIR__ . '/interface-backend.php';

if ( ! class_exists( 'Cache_Hive_Redis_PhpRedis_Backend' ) ) {
	/**
	 * PhpRedis backend for Cache Hive.
	 *
	 * This class provides a Redis-based caching backend using the PhpRedis extension.
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
		 * The key used to signal that a background flush of old keys is needed.
		 */
		const FLUSH_PENDING_KEY = 'ch:flush_pending';

		/**
		 * Cache_Hive_Redis_PhpRedis_Backend constructor.
		 *
		 * Initializes the Redis connection.
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

			// Using the robust connection logic from your file.
			try {
				if ( isset( $config['scheme'] ) && 'unix' === $config['scheme'] ) {
					if ( ! empty( $config['persistent'] ) ) {
						$this->redis->pconnect( $config['host'] );
					} else {
						$this->redis->connect( $config['host'] );
					}
				} elseif ( ! empty( $config['persistent'] ) ) {
					$this->redis->pconnect(
						$config['host'],
						(int) $config['port'],
						(float) ( $config['timeout'] ?? 1.0 ),
						'ch-pconn-' . ( $config['database'] ?? 0 )
					);
				} else {
					$context = array();
					if ( ! empty( $config['tls_enabled'] ) ) {
						$context = array(
							'ssl' => array(
								'verify_peer'      => false,
								'verify_peer_name' => false,
							),
						);
					}
					$this->redis->connect(
						$config['host'],
						(int) $config['port'],
						(float) ( $config['timeout'] ?? 1.0 ),
						null,
						0,
						0.0,
						$context
					);
				}

				$password = $config['objectCachePassword'] ?? ( $config['pass'] ?? null );
				$username = $config['objectCacheUsername'] ?? ( $config['user'] ?? null );
				if ( ! empty( $password ) ) {
					$auth = ! empty( $username ) ? array( $username, $password ) : $password;
					$this->redis->auth( $auth );
				}

				if ( ! empty( $config['database'] ) && (int) $config['database'] > 0 ) {
					$this->redis->select( (int) $config['database'] );
				}

				if ( 0 === strcasecmp( 'igbinary', $config['serializer'] ?? '' ) && defined( 'Redis::SERIALIZER_IGBINARY' ) ) {
					$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY );
				} else {
					$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP );
				}

				$compression_map = array(
					'zstd' => defined( 'Redis::COMPRESSION_ZSTD' ) ? \Redis::COMPRESSION_ZSTD : null,
					'lz4'  => defined( 'Redis::COMPRESSION_LZ4' ) ? \Redis::COMPRESSION_LZ4 : null,
					'lzf'  => defined( 'Redis::COMPRESSION_LZF' ) ? \Redis::COMPRESSION_LZF : null,
				);

				if ( isset( $config['compression'] ) && isset( $compression_map[ $config['compression'] ] ) ) {
					$this->redis->setOption( \Redis::OPT_COMPRESSION, $compression_map[ $config['compression'] ] );
				}

				$ping            = $this->redis->ping();
				$this->connected = ( '+PONG' === $ping || true === $ping );

			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive Redis Connection Error: ' . $e->getMessage() );
				$this->connected = false;
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
				error_log( 'Cache Hive Redis get Error: ' . $e->getMessage() );
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
			if ( empty( $keys ) ) {
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
				error_log( 'Cache Hive Redis get_multiple Error: ' . $e->getMessage() );
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
				if ( $ttl > 0 ) {
					return $this->redis->setex( $key, $ttl, $value );
				}
				return $this->redis->set( $key, $value );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive Redis set Error: ' . $e->getMessage() );
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
			$options = array( 'nx' );
			if ( $ttl > 0 ) {
				$options['ex'] = $ttl;
			}
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return $this->redis->set( $key, $value, $options );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive Redis add Error: ' . $e->getMessage() );
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
			$options = array( 'xx' );
			if ( $ttl > 0 ) {
				$options['ex'] = $ttl;
			}
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				return $this->redis->set( $key, $value, $options );
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive Redis replace Error: ' . $e->getMessage() );
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
				error_log( 'Cache Hive Redis delete Error: ' . $e->getMessage() );
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
				error_log( 'Cache Hive Redis increment Error: ' . $e->getMessage() );
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
				error_log( 'Cache Hive Redis decrement Error: ' . $e->getMessage() );
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
					// A failed close is not critical, but we should log it.
					error_log( 'Cache Hive Redis close Error: ' . $e->getMessage() );
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
		 * Implements the "smart" async flush.
		 *
		 * Instead of a full flush, it increments the global version prefix,
		 * instantly invalidating old keys, and sets a flag for background cleanup.
		 *
		 * @param bool $async When true, perform a "smart" async flush. When false, performs a standard blocking flush.
		 * @return bool True on success, false on failure.
		 */
		public function flush( $async ) {
			try {
				if ( ! $this->is_connected() ) {
					return false;
				}
				if ( $async ) {
					// Get the current prefix version before we change it.
					$old_prefix = $this->redis->get( 'ch:global_prefix' );
					// Immediately increment the prefix, making all old keys inaccessible.
					$this->redis->incr( 'ch:global_prefix' );
					// Set the pending flag with the old prefix info for the cron job. TTL of 5 minutes.
					$this->redis->setex( self::FLUSH_PENDING_KEY, 300, $old_prefix );
					return true;
				}

				// For a synchronous flush, do a standard full flush.
				return $this->redis->flushDB();
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive Redis flush Error: ' . $e->getMessage() );
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
				$compression = 'none';
				if ( defined( 'Redis::OPT_COMPRESSION' ) ) {
					$current_compression = $this->redis->getOption( \Redis::OPT_COMPRESSION );
					if ( defined( 'Redis::COMPRESSION_ZSTD' ) && \Redis::COMPRESSION_ZSTD === $current_compression ) {
						$compression = 'zstd';
					} elseif ( defined( 'Redis::COMPRESSION_LZ4' ) && \Redis::COMPRESSION_LZ4 === $current_compression ) {
						$compression = 'lz4';
					} elseif ( defined( 'Redis::COMPRESSION_LZF' ) && \Redis::COMPRESSION_LZF === $current_compression ) {
						$compression = 'lzf';
					}
				}

				$return_info = array(
					'status'         => 'Connected',
					'client'         => 'PhpRedis',
					'host'           => $this->config['host'],
					'port'           => $this->config['port'],
					'database'       => $this->config['database'] ?? 0,
					'server_version' => $info['redis_version'] ?? 'N/A',
					'memory_usage'   => $info['used_memory_human'] ?? 'N/A',
					'uptime'         => $info['uptime_in_seconds'] ?? 'N/A',
					'serializer'     => $serializer,
					'compression'    => $compression,
					'persistent'     => ! empty( $this->config['persistent'] ),
					'prefetch'       => ! empty( $this->config['prefetch'] ),
				);

				// Check for the async flush flag and add it to the info array.
				if ( $this->redis->exists( self::FLUSH_PENDING_KEY ) ) {
					$return_info['flush_pending'] = true;
				}

				return $return_info;
			} catch ( \RedisException $e ) {
				error_log( 'Cache Hive Redis get_info Error: ' . $e->getMessage() );
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
