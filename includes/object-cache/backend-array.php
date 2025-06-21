<?php
/**
 * Array fallback backend for Cache Hive object cache.
 */

require_once __DIR__ . '/interface-backend.php';

class Cache_Hive_Array_Backend implements Cache_Hive_Backend_Interface {
    private $cache = array();
    public function __construct($config) {}
    public function get($key, &$found) {
        $found = isset($this->cache[$key]);
        return $found ? $this->cache[$key] : false;
    }
    public function get_multiple($keys) {
        $results = array();
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $found);
        }
        return $results;
    }
    public function set($key, $value, $ttl) {
        $this->cache[$key] = $value;
        return true;
    }
    public function add($key, $value, $ttl) {
        if (isset($this->cache[$key])) return false;
        return $this->set($key, $value, $ttl);
    }
    public function replace($key, $value, $ttl) {
        if (!isset($this->cache[$key])) return false;
        return $this->set($key, $value, $ttl);
    }
    public function delete($key) {
        unset($this->cache[$key]);
        return true;
    }
    public function flush($async) {
        $this->cache = array();
        return true;
    }
    public function increment($key, $offset) {
        if (!isset($this->cache[$key])) $this->cache[$key] = 0;
        $this->cache[$key] += $offset;
        return $this->cache[$key];
    }
    public function decrement($key, $offset) {
        if (!isset($this->cache[$key])) $this->cache[$key] = 0;
        $this->cache[$key] -= $offset;
        return $this->cache[$key];
    }
    public function close() { return true; }
    public function is_connected() { return false; }
    public function get_info() {
        return array('status' => 'Not Connected', 'client' => 'Array Cache (Fallback)');
    }
}

