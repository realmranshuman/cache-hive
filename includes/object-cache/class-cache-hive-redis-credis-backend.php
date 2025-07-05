<?php
/**
 * Credis backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Credis backend for the Cache Hive object cache.
 */
class Cache_Hive_Redis_Credis_Backend implements Cache_Hive_Backend_Interface {
	private $client;
	private $config;
	private $connected = false;

	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Credis_Client' ) ) {
			return;
		}

		try {
			$host = 'unix' === $this->config['scheme'] ? $this->config['host'] : ( 'tls' === $this->config['scheme'] ? 'tls://' . $this->config['host'] : $this->config['host'] );
			$port = 'unix' === $this->config['scheme'] ? null : $this->config['port'];

			$this->client = new \Credis_Client( $host, $port, $this->config['timeout'], '', $this->config['database'], null );
			$this->client->connect();

			if ( ! empty( $this->config['pass'] ) ) {
				$auth_params = ! empty( $this->config['user'] ) ? array( $this->config['user'], $this->config['pass'] ) : array( $this->config['pass'] );
				$this->client->__call( 'auth', $auth_params );
			}

			$this->connected = $this->client->isConnected();
		} catch ( \Exception $e ) {
			error_log( 'Cache Hive Credis Connection Error: ' . $e->getMessage() );
			$this->connected = false;
		}
	}

	private function unserialize_value( $value ) {
		if ( is_string( $value ) ) {
			$unserialized = @unserialize( $value );
			return ( false !== $unserialized || 'b:0;' === $value ) ? $unserialized : $value;
		}
		return $value;
	}

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

	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false;
		}
		return $this->client->setex( $key, $ttl, serialize( $value ) );
	}

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

	public function delete( $key ) {
		return $this->is_connected() ? $this->client->del( $key ) > 0 : false;
	}

	public function flush( $async ) {
		return $this->is_connected() ? $this->client->flushdb( $async ) : false;
	}

	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->client->incrby( $key, $offset ) : false;
	}

	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->client->decrby( $key, $offset ) : false;
	}

	public function close() {
		if ( $this->client && $this->client->isConnected() ) {
			$this->client->close();
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
				'database'       => $this->config['database'],
				'persistent'     => ! empty( $this->config['persistent'] ), 
				'prefetch'       => ! empty( $this->config['prefetch'] ),
				'serializer'     => $this->config['serializer'] ?? 'php', 
				'server_version' => $info['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( \Exception $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'Credis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
