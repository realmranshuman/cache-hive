<?php
/**
 * Predis backend for Cache Hive object cache.
 * Requires Predis to be installed via Composer (vendor/autoload.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once ABSPATH . 'wp-content/plugins/cache-hive/vendor/autoload.php';
require_once __DIR__ . '/interface-backend.php';

class Cache_Hive_Redis_Predis_Backend implements Cache_Hive_Backend_Interface {
    private $client;
    private $config;
    private $connected = false;

    public function __construct( $config ) {
        $this->config = $config;
        try {
            $parameters = [
                'scheme'   => $config['tls_enabled'] ? 'tls' : 'tcp',
                'host'     => $config['host'],
                'port'     => $config['port'],
                'database' => $config['database'],
                'timeout'  => $config['timeout'],
            ];
            if ( ! empty( $config['user'] ) ) {
                $parameters['username'] = $config['user'];
            }
            if ( ! empty( $config['pass'] ) ) {
                $parameters['password'] = $config['pass'];
            }
            $options = [];
            if ( $config['persistent'] ) {
                $options['persistent'] = true;
            }
            $this->client = new Predis\Client( $parameters, $options );
            $this->client->connect();
            $this->connected = $this->client->isConnected();
        } catch ( Exception $e ) {
            $this->connected = false;
        }
    }

    public function get( $key, &$found ) {
        $value = $this->client->get( $key );
        $found = $value !== null;
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
        return $this->client->set( $key, $value, 'NX', 'EX', $ttl );
    }
    public function replace( $key, $value, $ttl ) {
        return $this->client->set( $key, $value, 'XX', 'EX', $ttl );
    }
    public function delete( $key ) {
        return $this->client->del( [ $key ] ) > 0;
    }
    public function flush( $async ) {
        return $async ? $this->client->flushdb( [ 'ASYNC' => true ] ) : $this->client->flushdb();
    }
    public function increment( $key, $offset ) {
        return $this->client->incrby( $key, $offset );
    }
    public function decrement( $key, $offset ) {
        return $this->client->decrby( $key, $offset );
    }
    public function close() {
        $this->client->disconnect();
        return true;
    }
    public function is_connected() {
        return $this->connected;
    }
    public function get_info() {
        $info = $this->client->info();
        return [
            'status' => $this->connected ? 'Connected' : 'Not Connected',
            'client' => 'Predis',
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'database' => $this->config['database'],
            'server_version' => $info['Server']['redis_version'] ?? 'N/A',
            'memory_usage' => $info['Memory']['used_memory_human'] ?? 'N/A',
            'uptime' => $info['Server']['uptime_in_seconds'] ?? 'N/A',
        ];
    }
}
