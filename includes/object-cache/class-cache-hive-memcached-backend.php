<?php
/**
 * Memcached backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Memcached backend for the Cache Hive object cache.
 */
class Cache_Hive_Memcached_Backend implements Cache_Hive_Backend_Interface {

	/**
	 * The Memcached instance.
	 *
	 * @var \Memcached|null
	 */
	private $mc;

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
	 * The key for the namespace version.
	 *
	 * @var string
	 */
	private $ns_version_key = 'ch_ns_v';

	/**
	 * The current namespace version.
	 *
	 * @var int|null
	 */
	private $ns_version;

	/**
	 * The server key used in stats arrays.
	 *
	 * @var string|null
	 */
	private $server_key;

	/**
	 * Sets up the Memcached connection.
	 *
	 * @param array                 $config The backend configuration.
	 * @param Cache_Hive_Transcoder $transcoder The secure transcoder instance.
	 */
	public function __construct( $config, $transcoder ) {
		$this->config     = $config;
		$this->transcoder = $transcoder;
		if ( ! class_exists( 'Memcached' ) ) {
			return;
		}

		$persistent_id = 'cache-hive-' . md5( ( $config['host'] ?? '' ) . ':' . ( $config['port'] ?? '' ) );
		$this->mc      = ! empty( $this->config['persistent'] ) ? new \Memcached( $persistent_id ) : new \Memcached();

		if ( ! count( $this->mc->getServerList() ) ) {
			if ( 'unix' === $this->config['scheme'] ) {
				$this->mc->addServer( $this->config['host'], 0 );
			} else {
				$this->mc->addServer( $this->config['host'], (int) $this->config['port'] );
			}
		}

		if ( ! empty( $this->config['user'] ) && ! empty( $this->config['pass'] ) ) {
			$this->mc->setSaslAuthData( $this->config['user'], $this->config['pass'] );
		}

		$stats            = $this->mc->getStats();
		$this->server_key = ( 'unix' === $this->config['scheme'] ) ? $this->config['host'] . ':0' : $this->config['host'] . ':' . $this->config['port'];

		if ( is_array( $stats ) && isset( $stats[ $this->server_key ] ) && $stats[ $this->server_key ]['pid'] > 0 ) {
			$this->connected = true;
			$this->get_ns_version();
		}
	}

	/**
	 * Retrieves the current namespace version from Memcached.
	 *
	 * @return int The current namespace version.
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
		$this->ns_version = (int) $version;
		return $this->ns_version;
	}

	/**
	 * Generates a namespaced key for Memcached.
	 *
	 * @param string $key The original key.
	 * @return string The namespaced key.
	 */
	private function get_namespaced_key( $key ) {
		return $this->get_ns_version() . ':' . $key;
	}

	/**
	 * Retrieves a value from the cache.
	 *
	 * @param string $key The key to retrieve.
	 * @param bool   $found Whether the key was found in the cache.
	 * @return mixed The cached value, or false if not found.
	 */
	public function get( $key, &$found ) {
		if ( ! $this->is_connected() ) {
			$found = false;
			return false;
		}
		$value   = $this->mc->get( $this->get_namespaced_key( $key ) );
		$found   = \Memcached::RES_SUCCESS === $this->mc->getResultCode();
		$decoded = $found ? $this->transcoder->decode( $value ) : false;
		$found   = ( false !== $decoded ); // Update found status after integrity check.
		return $decoded;
	}

	/**
	 * Retrieves multiple values from the cache.
	 *
	 * @param array $keys An array of keys to retrieve.
	 * @return array An associative array of cached values, keyed by the original keys.
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
				$decoded = $this->transcoder->decode( $value );
				if ( false !== $decoded ) {
					$final_results[ substr( $ns_key, $ns_version_len ) ] = $decoded;
				}
			}
		}
		return $final_results;
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
			return false;
		}
		$encoded_value = $this->transcoder->encode( $value );
		return $this->mc->set( $this->get_namespaced_key( $key ), $encoded_value, $ttl );
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
			return false;
		}
		$encoded_value = $this->transcoder->encode( $value );
		return $this->mc->add( $this->get_namespaced_key( $key ), $encoded_value, $ttl );
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
			return false;
		}
		$encoded_value = $this->transcoder->encode( $value );
		return $this->mc->replace( $this->get_namespaced_key( $key ), $encoded_value, $ttl );
	}

	/**
	 * Deletes a value from the cache.
	 *
	 * @param string $key The key to delete.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $key ) {
		return $this->is_connected() ? $this->mc->delete( $this->get_namespaced_key( $key ) ) : false;
	}

	/**
	 * Flushes the entire cache.
	 *
	 * @param bool $async Whether to flush asynchronously (if supported).
	 */
	public function flush( $async ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		// Memcached flush is a namespace bump, not a server command.
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
	 * @param string $key The key of the item to increment.
	 * @param int    $offset The amount by which to increment the item's value.
	 */
	public function increment( $key, $offset ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		// Memcached incr/decr doesn't work on signed/serialized values.
		// We must do a read-modify-write, which is not atomic.
		$value = $this->get( $key, $found );
		if ( ! $found ) {
			$new_value = $offset;
			$this->add( $key, $new_value, 0 );
			return $new_value;
		}
		$new_value = (int) $value + $offset;
		$this->set( $key, $new_value, 0 );
		return $new_value;
	}

	/**
	 * Decrements a numeric item's value.
	 *
	 * @param string $key The key of the item to decrement.
	 * @param int    $offset The amount by which to decrement the item's value.
	 */
	public function decrement( $key, $offset ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		// Memcached incr/decr doesn't work on signed/serialized values.
		// We must do a read-modify-write, which is not atomic.
		$value = $this->get( $key, $found );
		if ( ! $found ) {
			$new_value = 0;
			$this->add( $key, $new_value, 0 );
			return $new_value;
		}
		$new_value = (int) $value - $offset;
		$this->set( $key, $new_value, 0 );
		return $new_value;
	}

	/**
	 * Closes the Memcached connection.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close() {
		if ( empty( $this->config['persistent'] ) && $this->is_connected() ) {
			$this->mc->quit();
		}
		$this->connected = false;
		return true;
	}

	/**
	 * Checks if the Memcached client is connected.
	 *
	 * @return bool True if connected, false otherwise.
	 */
	public function is_connected() {
		return $this->connected;
	}

	/**
	 * Retrieves information about the Memcached connection and server.
	 *
	 * @return array An associative array containing connection status and server information.
	 */
	public function get_info() {
		if ( ! $this->is_connected() ) {
			return array(
				'status' => 'Not Connected',
				'client' => 'Memcached',
			);
		}
		$stats = $this->mc->getStats()[ $this->server_key ] ?? array();
		return array(
			'status'            => 'Connected',
			'client'            => 'Memcached',
			'host'              => $this->config['host'],
			'port'              => $this->config['port'],
			'persistent'        => ! empty( $this->config['persistent'] ),
			'prefetch'          => ! empty( $this->config['prefetch'] ),
			'serializer'        => $this->config['serializer'] ?? 'php',
			'server_version'    => $stats['version'] ?? 'N/A',
			'memory_usage'      => isset( $stats['bytes'] ) ? size_format( $stats['bytes'] ) : 'N/A',
			'uptime'            => $stats['uptime'] ?? 'N/A',
			'namespace_version' => $this->ns_version,
		);
	}
}
