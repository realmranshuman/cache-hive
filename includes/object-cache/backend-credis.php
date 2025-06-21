<?php
/**
 * Credis backend for Cache Hive object cache.
 * Requires Credis to be installed via Composer (vendor/autoload.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once ABSPATH . 'wp-content/plugins/cache-hive/vendor/autoload.php';
require_once __DIR__ . '/interface-backend.php';

class Cache_Hive_Redis_Credis_Backend implements Cache_Hive_Backend_Interface {
    private $client;
    private $config;
    private $connected = false;

    public function __construct( $config ) {
        $this->config = $config;
        try {
            $this->client = new Credis_Client(
                $config['host'],
                $config['port'],
                $config['timeout'],
                $config['persistent'],
                $config['database'],
                $config['pass']
            );
            if ( ! empty( $config['user'] ) ) {
                $this->client->auth( $config['user'] . ':' . $config['pass'] );
            } elseif ( ! empty( $config['pass'] ) ) {
                $this->client->auth( $config['pass'] );
            }
            $this->connected = $this->client->connect();
        } catch ( Exception $e ) {
            $this->connected = false;
        }
    }

    public function get( $key, &$found ) {
        $value = $this->client->get( $key );
        $found = $value !== false && $value !== null;
        return $value;
    }
    public function get_multiple( $keys ) {
        $values = $this->client->mget( $keys );
        $result = [];
        foreach ( $keys as $i => $key ) {
            $result[ $key ] = $values[ $i ];
        }
        return $result;
    }
    public function set( $key, $value, $ttl ) {
        return $this->client->setex( $key, $ttl, $value );
    }
    public function add( $key, $value, $ttl ) {
        return $this->client->set( $key, $value, array( 'nx', 'ex' => $ttl ) );
    }
    public function replace( $key, $value, $ttl ) {
        return $this->client->set( $key, $value, array( 'xx', 'ex' => $ttl ) );
    }
    public function delete( $key ) {
        return $this->client->del( $key ) > 0;
    }
    public function flush( $async ) {
        return $async ? $this->client->flushdb( true ) : $this->client->flushdb();
    }
    public function increment( $key, $offset ) {
        return $this->client->incrby( $key, $offset );
    }
    public function decrement( $key, $offset ) {
        return $this->client->decrby( $key, $offset );
    }
    public function close() {
        $this->client->close();
        return true;
    }
    public function is_connected() {
        return $this->connected;
    }
    public function get_info() {
        $info = $this->client->info();
        return [
            'status' => $this->connected ? 'Connected' : 'Not Connected',
            'client' => 'Credis',
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'database' => $this->config['database'],
            'server_version' => $info['redis_version'] ?? 'N/A',
            'memory_usage' => $info['used_memory_human'] ?? 'N/A',
            'uptime' => $info['uptime_in_seconds'] ?? 'N/A',
        ];
    }
}
