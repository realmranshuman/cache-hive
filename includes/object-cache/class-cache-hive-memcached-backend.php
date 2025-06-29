<?php
/**
 * Memcached backend for Cache Hive object cache.
 * Implements namespacing, manual compression, and detailed stats.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-backend.php';

/**
 * Memcached backend for the Cache Hive object cache.
 *
 * @package Cache_Hive
 */
class Cache_Hive_Memcached_Backend implements Cache_Hive_Backend_Interface {

	private const FLAG_ZSTD_COMPRESSED = 'z';
	private const FLAG_LZF_COMPRESSED  = 'l';
	private const FLAG_PHP_SERIALIZED  = 'p';

	/**
	 * The Memcached instance.
	 *
	 * @var \Memcached
	 */
	private $mc;

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
	 * The key for the namespace version.
	 *
	 * @var string
	 */
	private $ns_version_key = 'cache-hive-ns-version';

	/**
	 * The current namespace version.
	 *
	 * @var int|null
	 */
	private $ns_version;

	/**
	 * Cache statistics.
	 *
	 * @var array
	 */
	private $stats = array(
		'hits'   => 0,
		'misses' => 0,
	);

	/**
	 * The compression method to use.
	 *
	 * @var string
	 */
	private $compression_method = 'none';

	/**
	 * The server key used in stats arrays.
	 *
	 * @var string|null
	 */
	private $server_key;

	/**
	 * Sets up the Memcached connection.
	 *
	 * @param array $config The backend configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Memcached' ) ) {
			$this->connected = false;
			return;
		}

		$persistent_id = 'cache-hive-' . md5( ( $config['host'] ?? '' ) . ':' . ( $config['port'] ?? '' ) );
		$this->mc      = ! empty( $this->config['objectCachePersistentConnection'] ) ? new \Memcached( $persistent_id ) : new \Memcached();

		if ( ! count( $this->mc->getServerList() ) ) {
			$this->mc->setOption( \Memcached::OPT_LIBKETAMA_COMPATIBLE, true );
			if ( version_compare( phpversion( 'memcached' ), '3', '>=' ) ) {
				$this->mc->setOption( \Memcached::OPT_BINARY_PROTOCOL, true );
			}

			// --- THE FIX IS HERE ---
			// Translate the generic serializer string into the specific Memcached constant.
			if ( 'igbinary' === ( $this->config['serializer'] ?? '' ) && defined( 'Memcached::HAVE_IGBINARY' ) && \Memcached::HAVE_IGBINARY ) {
				$this->mc->setOption( \Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_IGBINARY );
			} else {
				$this->mc->setOption( \Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_PHP );
			}
			// --- END OF FIX ---

			if ( 'unix' === $this->config['scheme'] ) {
				$this->mc->addServer( $this->config['host'], 0 );
			} else {
				$this->mc->addServer( $this->config['host'], (int) $this->config['port'] );
			}
		}

		if ( function_exists( 'zstd_compress' ) ) {
			$this->compression_method = 'zstd';
		} elseif ( function_exists( 'lzf_compress' ) ) {
			$this->compression_method = 'lzf';
		}

		if ( ! empty( $this->config['user'] ) && ! empty( $this->config['pass'] ) ) {
			$this->mc->setSaslAuthData( $this->config['user'], $this->config['pass'] );
		}

		$stats            = $this->mc->getStats();
		$this->server_key = ( 'unix' === $this->config['scheme'] ) ? $this->config['host'] . ':0' : $this->config['host'] . ':' . $this->config['port'];
		if ( ':0' === $this->server_key && isset( $this->config['host'] ) && '/var/run/memcached/memcached.sock' === $this->config['host'] ) {
			$this->server_key = '/var/run/memcached/memcached.sock:0';
		}

		if ( is_array( $stats ) && isset( $stats[ $this->server_key ] ) && $stats[ $this->server_key ]['pid'] > 0 ) {
			$this->connected = true;
			$this->get_ns_version();
		}
	}

	/**
	 * Gets the current namespace version from Memcached.
	 *
	 * @return int The namespace version.
	 */
	public function get_ns_version() {
		if ( null !== $this->ns_version ) {
			return $this->ns_version;
		}
		$version = $this->mc->get( $this->ns_version_key );
		if ( false === $version ) {
			$version = time();
			$this->mc->set( $this->ns_version_key, $version, 0 );
		}
		$this->ns_version = (int) $version;
		return $this->ns_version;
	}

	/**
	 * Prepends the namespace version to a key.
	 *
	 * @param string $key The key.
	 * @return string The namespaced key.
	 */
	private function get_namespaced_key( $key ) {
		$prefix = $this->get_ns_version() . ':';
		if ( is_string( $key ) && str_starts_with( $key, $prefix ) ) {
			return $key;
		}
		return $prefix . $key;
	}

	/**
	 * Encodes a value for storage.
	 *
	 * @param mixed $data The data to encode.
	 * @return string The encoded value.
	 */
	private function encode_value( $data ) {
		// The serializer is now handled by Memcached::setOption, so we don't do it manually.
		// However, we still need to handle our custom compression flags.
		$value = $data;

		if ( 'zstd' === $this->compression_method ) {
			return self::FLAG_ZSTD_COMPRESSED . zstd_compress( serialize( $value ) );
		}
		if ( 'lzf' === $this->compression_method ) {
			return self::FLAG_LZF_COMPRESSED . lzf_compress( serialize( $value ) );
		}

		// If no custom compression, let the built-in serializer handle it.
		// We add a flag just to be consistent in our decode method.
		return self::FLAG_PHP_SERIALIZED . serialize( $value );
	}

	/**
	 * Decodes a value from storage.
	 *
	 * @param string|false $value The value from Memcached.
	 * @return mixed The decoded data, or false on failure.
	 */
	private function decode_value( $value ) {
		// The serializer is now handled by Memcached::setOption, so Memcached::get() returns the unserialized data directly.
		// We only need to handle our custom compression.

		if ( false === $value ) {
			return false;
		}

		if ( is_string( $value ) && strlen( $value ) > 1 ) {
			$flag = $value[0];
			$data = substr( $value, 1 );

			if ( self::FLAG_ZSTD_COMPRESSED === $flag && function_exists( 'zstd_uncompress' ) ) {
				return unserialize( zstd_uncompress( $data ) );
			}
			if ( self::FLAG_LZF_COMPRESSED === $flag && function_exists( 'lzf_uncompress' ) ) {
				return unserialize( lzf_uncompress( $data ) );
			}
			if ( self::FLAG_PHP_SERIALIZED === $flag ) {
				return unserialize( $data );
			}
		}

		// If no flag or not a string, return the value as is.
		return $value;
	}

	/**
	 * Retrieves an item from the cache.
	 *
	 * @param string    $key   The key of the item to retrieve.
	 * @param bool|null &$found Whether the key was found in the cache. Passed by reference.
	 * @return mixed The value of the item, or false on failure.
	 */
	public function get( $key, &$found ) {
		if ( ! $this->is_connected() ) {
			$found = false;
			++$this->stats['misses'];
			return false;
		}
		$value = $this->mc->get( $this->get_namespaced_key( $key ) );
		if ( \Memcached::RES_SUCCESS === $this->mc->getResultCode() ) {
			$found = true;
			++$this->stats['hits'];
			return $this->decode_value( $value );
		}
		$found = false;
		++$this->stats['misses'];
		return false;
	}

	/**
	 * Retrieves multiple items from the cache.
	 *
	 * @param string[] $keys Array of keys to retrieve.
	 * @return array Array of found key-value pairs.
	 */
	public function get_multiple( $keys ) {
		if ( ! $this->is_connected() || empty( $keys ) || ! is_array( $keys ) ) {
			return array();
		}
		$namespaced_keys = array_map( array( $this, 'get_namespaced_key' ), $keys );
		$results         = $this->mc->getMulti( $namespaced_keys );
		$final_results   = array();

		if ( is_array( $results ) && ! empty( $results ) ) {
			$ns_version_str = $this->get_ns_version() . ':';
			$ns_version_len = strlen( $ns_version_str );

			foreach ( $results as $ns_key => $value ) {
				$original_key                   = substr( $ns_key, $ns_version_len );
				$final_results[ $original_key ] = $this->decode_value( $value );
				++$this->stats['hits'];
			}
		}
		$this->stats['misses'] += ( count( $keys ) - count( $final_results ) );
		return $final_results;
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
		return $this->mc->set( $this->get_namespaced_key( $key ), $this->encode_value( $value ), $ttl );
	}

	/**
	 * Adds an item to the cache, but only if the key does not exist.
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
		return $this->mc->add( $this->get_namespaced_key( $key ), $this->encode_value( $value ), $ttl );
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
		return $this->mc->replace( $this->get_namespaced_key( $key ), $this->encode_value( $value ), $ttl );
	}

	/**
	 * Deletes an item from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		return $this->is_connected() ? $this->mc->delete( $this->get_namespaced_key( $key ) ) : false;
	}

	/**
	 * Flushes the cache by incrementing the namespace version.
	 *
	 * @param bool $async Whether to flush asynchronously (not used).
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$this->ns_version = $this->mc->increment( $this->ns_version_key );
		if ( false === $this->ns_version ) {
			$this->ns_version = null;
			$this->get_ns_version();
		}
		return true;
	}

	/**
	 * Increments a numeric item's value.
	 *
	 * @param string $key    The key of the item to increment.
	 * @param int    $offset The amount by which to increment.
	 * @return int|false The new value on success, false on failure.
	 */
	public function increment( $key, $offset ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$namespaced_key = $this->get_namespaced_key( $key );
		$new_value      = $this->mc->increment( $namespaced_key, $offset );
		if ( false === $new_value ) {
			if ( $this->mc->add( $namespaced_key, $offset, 0 ) ) {
				return $offset;
			}
		}
		return $new_value;
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key    The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement.
	 * @return int|false The new value on success, false on failure.
	 */
	public function decrement( $key, $offset ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$namespaced_key = $this->get_namespaced_key( $key );
		$new_value      = $this->mc->decrement( $namespaced_key, $offset );
		if ( false === $new_value ) {
			if ( $this->mc->add( $namespaced_key, 0, 0 ) ) {
				return 0;
			}
		}
		return $new_value;
	}

	/**
	 * Closes the connection to the cache.
	 *
	 * @return bool Always returns true.
	 */
	public function close() {
		if ( empty( $this->config['objectCachePersistentConnection'] ) && $this->is_connected() ) {
			$this->mc->quit();
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
				'client' => 'Memcached',
			);
		}
		$stats      = $this->mc->getStats()[ $this->server_key ] ?? array();
		$serializer = 'php';
		if ( defined( 'Memcached::HAVE_IGBINARY' ) && \Memcached::HAVE_IGBINARY && \Memcached::SERIALIZER_IGBINARY === $this->mc->getOption( \Memcached::OPT_SERIALIZER ) ) {
			$serializer = 'igbinary';
		}
		return array(
			'status'            => 'Connected',
			'client'            => 'Memcached',
			'host'              => $this->config['host'],
			'port'              => $this->config['port'],
			'persistent'        => ! empty( $this->config['objectCachePersistentConnection'] ),
			'prefetch'          => ! empty( $this->config['prefetch'] ),
			'flush_async'       => ! empty( $this->config['flush_async'] ),
			'server_version'    => $stats['version'] ?? 'N/A',
			'memory_usage'      => isset( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : 'N/A',
			'uptime'            => $stats['uptime'] ?? 'N/A',
			'serializer'        => $serializer,
			'compression'       => $this->compression_method,
			'namespace_version' => $this->ns_version,
		);
	}
}
