<?php
/**
 * Predis backend for Cache Hive object cache.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Predis backend implementation for Cache Hive object cache.
 */
class Cache_Hive_Redis_Predis_Backend implements Cache_Hive_Backend_Interface {
	private $client;
	private $config;
	private $connected = false;

	public function __construct( $config ) {
		$this->config = $config;
		if ( ! class_exists( 'Predis\\Client' ) ) {
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
				$parameters['username'] = $this->config['user']; }
			if ( ! empty( $this->config['pass'] ) ) {
				$parameters['password'] = $this->config['pass']; }
			if ( ! empty( $this->config['persistent'] ) ) {
				$parameters['persistent'] = true; }
			if ( 'unix' === $parameters['scheme'] ) {
				$parameters['path'] = $parameters['host'];
				unset( $parameters['host'], $parameters['port'] ); }
			if ( 'tls' === $parameters['scheme'] && ! empty( $this->config['tls_options'] ) ) {
				$parameters['ssl'] = array( 'verify_peer' => $this->config['tls_options']['verify_peer'] ?? true );
				if ( ! empty( $this->config['tls_options']['ca_cert'] ) ) {
					$parameters['ssl']['cafile'] = $this->config['tls_options']['ca_cert'];
				}
			}

			$this->client = new \Predis\Client( $parameters );
			$this->client->connect();
			$this->connected = $this->client->isConnected();
		} catch ( \Exception $e ) {
			error_log( 'Cache Hive Predis Connection Error: ' . $e->getMessage() );
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
			return false; }
		$value = $this->client->get( $key );
		if ( null === $value ) {
			return false; }
		$found = true;
		return $this->unserialize_value( $value );
	}

	public function get_multiple( $keys ) {
		if ( empty( $keys ) || ! $this->is_connected() ) {
			return array(); }
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
			return false; }
		$ttl = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		return 'OK' === $this->client->setex( $key, $ttl, serialize( $value ) )->getPayload();
	}

	public function add( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$ttl = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		return (bool) $this->client->set( $key, serialize( $value ), 'EX', $ttl, 'NX' );
	}

	public function replace( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$ttl = $ttl > 0 ? $ttl : ( $this->config['lifetime'] ?? 3600 );
		return (bool) $this->client->set( $key, serialize( $value ), 'EX', $ttl, 'XX' );
	}

	public function delete( $key ) {
		return $this->is_connected() ? 0 < $this->client->del( array( $key ) ) : false;
	}

	public function flush( $async ) {
		if ( ! $this->is_connected() ) {
			return false; }
		return 'OK' === $this->client->flushdb( $async ? 'ASYNC' : null )->getPayload();
	}

	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->client->incrby( $key, $offset ) : false;
	}

	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->client->decrby( $key, $offset ) : false;
	}

	public function close() {
		if ( $this->client && $this->client->isConnected() ) {
			$this->client->disconnect();
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
				'database'       => $this->config['database'],
				'server_version' => $info['Server']['redis_version'] ?? 'N/A',
				'memory_usage'   => $info['Memory']['used_memory_human'] ?? 'N/A',
				'uptime'         => $info['Server']['uptime_in_seconds'] ?? 'N/A',
			);
		} catch ( \Exception $e ) {
			return array(
				'status' => 'Connection Error',
				'client' => 'Predis',
				'error'  => $e->getMessage(),
			);
		}
	}
}
