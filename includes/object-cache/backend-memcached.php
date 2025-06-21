<?php
/**
 * Memcached backend for Cache Hive object cache.
 */

require_once __DIR__ . '/interface-backend.php';
require_once ABSPATH . 'wp-content/plugins/cache-hive/vendor/autoload.php';

class Cache_Hive_Memcached_Backend implements Cache_Hive_Backend_Interface {
    private $mc;
    private $config;
    private $connected = false;
    public function __construct($config) {
        $this->config = $config;
        $this->mc = !empty($this->config['persistent']) ? new \Memcached('ch_pool') : new \Memcached();
        if (!count($this->mc->getServerList())) {
            $this->mc->addServer($this->config['host'], $this->config['port']);
        }
        if (!empty($this->config['user']) && !empty($this->config['pass'])) {
            $this->mc->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->mc->setSaslAuthData($this->config['user'], $this->config['pass']);
        }
        $stats = @$this->mc->getStats();
        $this->connected = is_array($stats) && isset($stats[$this->config['host'] . ':' . $this->config['port']]) && $stats[$this->config['host'] . ':' . $this->config['port']]['pid'] > 0;
    }
    public function get($key, &$found) {
        $value = @$this->mc->get($key);
        $found = ($this->mc->getResultCode() !== \Memcached::RES_NOTFOUND);
        return $value;
    }
    public function get_multiple($keys) {
        return @$this->mc->getMulti($keys) ?: [];
    }
    public function set($key, $value, $ttl) {
        return @$this->mc->set($key, $value, $ttl);
    }
    public function add($key, $value, $ttl) {
        return @$this->mc->add($key, $value, $ttl);
    }
    public function replace($key, $value, $ttl) {
        return @$this->mc->replace($key, $value, $ttl);
    }
    public function delete($key) {
        return @$this->mc->delete($key);
    }
    public function flush($async) {
        return @$this->mc->flush();
    }
    public function increment($key, $offset) {
        return @$this->mc->increment($key, $offset, 0, $this->config['lifetime']);
    }
    public function decrement($key, $offset) {
        return @$this->mc->decrement($key, $offset, 0, $this->config['lifetime']);
    }
    public function close() {
        if (empty($this->config['persistent'])) @$this->mc->quit();
        return true;
    }
    public function is_connected() {
        return $this->connected;
    }
    public function get_info() {
        $stats = @$this->mc->getStats()[$this->config['host'] . ':' . $this->config['port']] ?? [];
        return [
            'status' => $this->connected ? 'Connected' : 'Not Connected',
            'client' => 'Memcached',
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'server_version' => $stats['version'] ?? 'N/A',
            'memory_usage' => isset($stats['bytes']) ? (function_exists('size_format') ? size_format($stats['bytes']) : $stats['bytes']) : 'N/A',
            'uptime' => $stats['uptime'] ?? 'N/A',
        ];
    }
}
