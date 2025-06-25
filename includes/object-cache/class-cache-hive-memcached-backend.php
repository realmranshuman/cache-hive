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
		$this->mc      = ! empty( $this->config['persistent'] ) ? new \Memcached( $persistent_id ) : new \Memcached();

		if ( ! count( $this->mc->getServerList() ) ) {
			$this->mc->setOption( \Memcached::OPT_LIBKETAMA_COMPATIBLE, true );
			if ( version_compare( phpversion( 'memcached' ), '3', '>=' ) ) {
				$this->mc->setOption( \Memcached::OPT_BINARY_PROTOCOL, true );
			}
			if ( defined( 'Memcached::HAVE_IGBINARY' ) && \Memcached::HAVE_IGBINARY ) {
				$this->mc->setOption( \Memcached::OPT_SERIALIZER, \Memcached::SERIALIZER_IGBINARY );
			}
			if ( isset( $config['port'] ) && 0 === (int) $config['port'] ) {
				$this->mc->addServer( $config['host'], 0 );
			} else {
				$this->mc->addServer( $config['host'], (int) $config['port'] );
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
		$is_unix_socket   = ( isset( $config['port'] ) && 0 === (int) $config['port'] ) || str_starts_with( $config['host'], '/' );
		$this->server_key = $is_unix_socket ? $config['host'] . ':11211' : $config['host'] . ':' . $config['port'];

		if ( is_array( $stats ) && isset( $stats[ $this->server_key ] ) && $stats[ $this->server_key ]['pid'] > 0 ) {
			$this->connected = true;
			$this->get_ns_version();
		}
	}

	/**
	 * Gets the current namespace version from Memcached.
	 *
	 * This is a global namespace version used for flushing the entire cache.
	 *
	 * @return int The namespace version.
	 */
	private function get_ns_version() {
		if ( null !== $this->ns_version ) {
			return $this->ns_version;
		}
		$version = $this->mc->get( $this->ns_version_key );
		if ( false === $version ) {
			$version = time();
			$this->mc->set( $this->ns_version_key, $version, 0 );
		}
		$this->ns_version = $version;
		return $this->ns_version;
	}

	/**
	 * Prepends the namespace version to a key.
	 *
	 * @param string $key The key.
	 * @return string The namespaced key.
	 */
	private function get_namespaced_key( $key ) {
		return "{$this->get_ns_version()}:{$key}";
	}

	/**
	 * Encodes a value for storage.
	 *
	 * Applies compression and serialization.
	 *
	 * @param mixed $data The data to encode.
	 * @return string The encoded value.
	 */
	private function encode_value( $data ) {
		$value = serialize( $data );
		if ( 'zstd' === $this->compression_method ) {
			return self::FLAG_ZSTD_COMPRESSED . zstd_compress( $value );
		}
		if ( 'lzf' === $this->compression_method ) {
			return self::FLAG_LZF_COMPRESSED . lzf_compress( $value );
		}
		return self::FLAG_PHP_SERIALIZED . $value;
	}

	/**
	 * Decodes a value from storage.
	 *
	 * Decompresses and unserializes the data.
	 *
	 * @param string|false $value The value from Memcached.
	 * @return mixed The decoded data, or false on failure.
	 */
	private function decode_value( $value ) {
		if ( false === $value || ! is_string( $value ) || 2 > strlen( $value ) ) {
			return false;
		}
		$flag         = $value[0];
		$data         = substr( $value, 1 );
		$decompressed = null;
		if ( self::FLAG_ZSTD_COMPRESSED === $flag && function_exists( 'zstd_uncompress' ) ) {
			$decompressed = zstd_uncompress( $data );
		} elseif ( self::FLAG_LZF_COMPRESSED === $flag && function_exists( 'lzf_uncompress' ) ) {
			$decompressed = lzf_uncompress( $data );
		} else {
			$decompressed = $data;
		}
		return unserialize( $decompressed );
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
		$found = ( \Memcached::RES_SUCCESS === $this->mc->getResultCode() );

		if ( $found ) {
			++$this->stats['hits'];
			return $this->decode_value( $value );
		}
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
		if ( ! $this->is_connected() || empty( $keys ) ) {
			return array();
		}
		$namespaced_keys = array_map( array( $this, 'get_namespaced_key' ), $keys );
		$results         = $this->mc->getMulti( $namespaced_keys );
		$final_results   = array();
		if ( is_array( $results ) ) {
			$ns_version_len = strlen( $this->get_ns_version() . ':' );
			foreach ( $results as $ns_key => $value ) {
				// The main WP_Object_Cache class now handles re-mapping, so we just return what we found.
				// This backend is simpler and doesn't need to know about groups.
				$final_results[ $ns_key ] = $this->decode_value( $value );
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
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->mc->delete( $this->get_namespaced_key( $key ) );
	}

	/**
	 * Flushes the entire cache.
	 *
	 * This is done by incrementing the namespace version.
	 *
	 * @param bool $async Not used by this backend.
	 * @return bool True on success, false on failure.
	 */
	public function flush( $async ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		$this->ns_version = $this->mc->increment( $this->ns_version_key );
		if ( false === $this->ns_version ) {
			// If increment fails (e.g., key expired), reset it.
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
		return $this->mc->increment( $this->get_namespaced_key( $key ), $offset );
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
		return $this->mc->decrement( $this->get_namespaced_key( $key ), $offset );
	}

	/**
	 * Closes the connection to the cache.
	 *
	 * Only closes non-persistent connections.
	 *
	 * @return bool Always returns true.
	 */
	public function close() {
		if ( empty( $this->config['persistent'] ) && $this->is_connected() ) {
			$this->mc->quit();
		}
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
		if ( ! $this->is_connected() || null === $this->server_key ) {
			return array(
				'status' => 'Not Connected',
				'client' => 'Memcached',
			);
		}
		$stats      = $this->mc->getStats()[ $this->server_key ] ?? array();
		$serializer = 'php';
		if ( defined( 'Memcached::HAVE_IGBINARY' ) && \Memcached::SERIALIZER_IGBINARY === $this->mc->getOption( \Memcached::OPT_SERIALIZER ) ) {
			$serializer = 'igbinary';
		}
		return array(
			'status'            => 'Connected',
			'client'            => 'Memcached',
			'host'              => $this->config['host'],
			'port'              => $this->config['port'],
			'server_version'    => $stats['version'] ?? 'N/A',
			'memory_usage'      => isset( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : 'N/A',
			'uptime'            => $stats['uptime'] ?? 'N/A',
			'persistent'        => ! empty( $this->config['persistent'] ),
			'prefetch'          => ! empty( $this->config['prefetch'] ),
			'serializer'        => $serializer,
			'compression'       => $this->compression_method,
			'hits'              => $this->stats['hits'],
			'misses'            => $this->stats['misses'],
			'namespace_version' => $this->ns_version,
		);
	}
}
