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
	private $redis;
	private $config;
	private $connected = false;

	public function __construct( $config ) {
		$this->config = $config;
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
			$this->connected = ( '+PONG' === $ping || true === $ping );
		} catch ( \RedisException $e ) {
			error_log( 'Cache Hive PhpRedis Connection Error: ' . $e->getMessage() );
			$this->connected = false;
		}
	}

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

	private function authenticate() {
		if ( empty( $this->config['pass'] ) ) {
			return;
		}
		$auth = ! empty( $this->config['user'] ) ? array( $this->config['user'], $this->config['pass'] ) : $this->config['pass'];
		if ( ! $this->redis->auth( $auth ) ) {
			throw new \RedisException( 'Redis authentication failed.' );
		}
	}

	private function select_database() {
		if ( ! empty( $this->config['database'] ) && (int) $this->config['database'] > 0 ) {
			if ( ! $this->redis->select( (int) $this->config['database'] ) ) {
				throw new \RedisException( 'Redis database selection failed.' );
			}
		}
	}

	private function configure_client() {
		if ( 0 === strcasecmp( 'igbinary', $this->config['serializer'] ?? '' ) && defined( 'Redis::SERIALIZER_IGBINARY' ) ) {
			$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY );
		} else {
			$this->redis->setOption( \Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP );
		}
	}

	public function get( $key, &$found ) {
		$found = false;
		if ( ! $this->is_connected() ) {
			return false; }
		$value = $this->redis->get( $key );
		$found = false !== $value;
		return $value;
	}

	public function get_multiple( $keys ) {
		if ( empty( $keys ) || ! is_array( $keys ) || ! $this->is_connected() ) {
			return array(); }
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
	}

	public function set( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		return $ttl > 0 ? $this->redis->setex( $key, $ttl, $value ) : $this->redis->set( $key, $value );
	}

	public function add( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$options = array( 'nx' );
		if ( $ttl > 0 ) {
			$options['ex'] = $ttl;
		}
		return $this->redis->set( $key, $value, $options );
	}

	public function replace( $key, $value, $ttl ) {
		if ( ! $this->is_connected() ) {
			return false; }
		$options = array( 'xx' );
		if ( $ttl > 0 ) {
			$options['ex'] = $ttl;
		}
		return $this->redis->set( $key, $value, $options );
	}

	public function delete( $key ) {
		return $this->is_connected() ? (bool) $this->redis->del( $key ) : false;
	}

	public function flush( $async ) {
		return $this->is_connected() ? $this->redis->flushDb( $async ) : false;
	}

	public function increment( $key, $offset ) {
		return $this->is_connected() ? $this->redis->incrBy( $key, $offset ) : false;
	}

	public function decrement( $key, $offset ) {
		return $this->is_connected() ? $this->redis->decrBy( $key, $offset ) : false;
	}

	public function close() {
		if ( $this->is_connected() && empty( $this->config['persistent'] ) ) {
			$this->redis->close();
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
