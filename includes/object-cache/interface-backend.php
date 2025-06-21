<?php
/**
 * Interface for Cache Hive object cache backends.
 */

interface Cache_Hive_Backend_Interface {
    public function __construct($config);
    public function get($key, &$found);
    public function get_multiple($keys);
    public function set($key, $value, $ttl);
    public function add($key, $value, $ttl);
    public function replace($key, $value, $ttl);
    public function delete($key);
    public function flush($async);
    public function increment($key, $offset);
    public function decrement($key, $offset);
    public function close();
    public function get_info();
    public function is_connected();
}
