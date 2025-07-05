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
	 * @param array $config The backend configuration.
	 */
	public function __construct( $config ) {
		$this->config = $config;
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

	private function get_namespaced_key( $key ) {
		return $this->get_ns_version() . ':' . $key;
	}

	public function get( $key, &$found ) {
		if ( ! $this->is_connected() ) {
			$found = false;
			return false;
		}
		$value = $this->mc->get( $this->get_namespaced_key( $key ) );
		$found = \Memcached::RES_SUCCESS === $this->mc->getResultCode();
		return $found ? $value : false;
	}

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
				$final_results[ substr( $ns_key, $ns_version_len ) ] = $value;
			}
		}
		return $final_results;
	}

	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->mc->set( $this->get_namespaced_key( $key ), $value, $ttl );
	}

	public function add( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->mc->add( $this->get_namespaced_key( $key ), $value, $ttl );
	}

	public function replace( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->mc->replace( $this->get_namespaced_key( $key ), $value, $ttl );
	}

	public function delete( $key ) {
		return $this->is_connected() ? $this->mc->delete( $this->get_namespaced_key( $key ) ) : false;
	}

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

	public function close() {
		if ( empty( $this->config['persistent'] ) && $this->is_connected() ) {
			$this->mc->quit();
		}
		$this->connected = false;
		return true;
	}

	public function is_connected() {
		return $this->connected;
	}

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
