<?php
/**
 * PhpRedis backend for Cache Hive object cache.
 */

require_once __DIR__ . '/interface-backend.php';
require_once ABSPATH . 'wp-content/plugins/cache-hive/vendor/autoload.php';

class Cache_Hive_Redis_PhpRedis_Backend implements Cache_Hive_Backend_Interface {
    private $redis;
    private $config;
    private $connected = false;
    public function __construct($config) {
        $this->config = $config;
        $this->redis = new \Redis();
        try {
            $connection_args = [ $this->config['host'] ];
            if ((int)$this->config['port'] !== 0) {
                $connection_args[] = (int)$this->config['port'];
                $connection_args[] = (float)$this->config['timeout'];
                if ($this->config['persistent']) {
                    $connection_args[] = 'persistent-id-' . $this->config['host'] . $this->config['port'];
                } else {
                    $connection_args[] = null;
                }
                $connection_args[] = 0;
                $connection_args[] = 0.0;
                if (!empty($this->config['tls_enabled'])) {
                    $connection_args['context'] = ['ssl' => $this->config['tls_options'] ?? []];
                }
                @call_user_func_array([$this->redis, $this->config['persistent'] ? 'pconnect' : 'connect'], $connection_args);
            } else {
                @$this->redis->connect($this->config['host']);
            }
            if (!empty($this->config['pass'])) {
                @$this->redis->auth($this->config['pass']);
            }
            if (!empty($this->config['database'])) {
                @$this->redis->select((int)$this->config['database']);
            }
            if (strcasecmp('igbinary', $this->config['serializer']) === 0 && defined('Redis::SERIALIZER_IGBINARY')) {
                @$this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_IGBINARY);
            }
            $compression_map = ['lzf' => 'Redis::COMPRESSION_LZF', 'zstd' => 'Redis::COMPRESSION_ZSTD', 'lz4' => 'Redis::COMPRESSION_LZ4'];
            if (isset($compression_map[$this->config['compression']]) && defined($compression_map[$this->config['compression']])) {
                @$this->redis->setOption(\Redis::OPT_COMPRESSION, constant($compression_map[$this->config['compression']]));
            }
            $this->connected = ('+PONG' === @$this->redis->ping());
        } catch (\RedisException $e) {
            $this->connected = false;
        }
    }
    public function get($key, &$found) {
        $value = @$this->redis->get($key);
        $found = $value !== false;
        return $value;
    }
    public function get_multiple($keys) {
        return @$this->redis->mget($keys) ?: [];
    }
    public function set($key, $value, $ttl) {
        return @$this->redis->setex($key, $ttl, $value);
    }
    public function add($key, $value, $ttl) {
        return @$this->redis->set($key, $value, ['nx', 'ex' => $ttl]);
    }
    public function replace($key, $value, $ttl) {
        return @$this->redis->set($key, $value, ['xx', 'ex' => $ttl]);
    }
    public function delete($key) {
        return (bool)@$this->redis->del($key);
    }
    public function flush($async) {
        return $async ? @$this->redis->flushDB(true) : @$this->redis->flushDB();
    }
    public function increment($key, $offset) {
        return @$this->redis->incrBy($key, $offset);
    }
    public function decrement($key, $offset) {
        return @$this->redis->decrBy($key, $offset);
    }
    public function close() {
        if (empty($this->config['persistent'])) @$this->redis->close();
        return true;
    }
    public function is_connected() {
        return $this->connected;
    }
    public function get_info() {
        $info = @$this->redis->info();
        return [
            'status' => $this->connected ? 'Connected' : 'Not Connected',
            'client' => 'PhpRedis',
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'database' => $this->config['database'],
            'server_version' => $info['redis_version'] ?? 'N/A',
            'memory_usage' => $info['used_memory_human'] ?? 'N/A',
            'uptime' => $info['uptime_in_seconds'] ?? 'N/A',
            'serializer' => $this->config['serializer'],
            'compression' => $this->config['compression'],
        ];
    }
}
