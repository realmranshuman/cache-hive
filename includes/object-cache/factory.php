<?php
if (class_exists('Cache_Hive_Object_Cache_Factory')) return;
/**
 * Factory for selecting the best object cache backend.
 */

class Cache_Hive_Object_Cache_Factory {
    public static function create($config) {
        $method = strtolower($config['method']);
        $backend = null;
        $log_prefix = '[Cache Hive Object Cache Factory] ';
        // Explicit backend selection
        if ($method === 'phpredis') {
            if (class_exists('Redis')) {
                error_log($log_prefix.'Using PhpRedis backend.');
                $backend = new Cache_Hive_Redis_PhpRedis_Backend($config);
            } else {
                error_log($log_prefix.'PhpRedis selected but extension not found.');
            }
        } elseif ($method === 'predis') {
            if (class_exists('Predis\\Client')) {
                error_log($log_prefix.'Using Predis backend.');
                $backend = new Cache_Hive_Redis_Predis_Backend($config);
            } else {
                error_log($log_prefix.'Predis selected but not found.');
            }
        } elseif ($method === 'credis') {
            if (class_exists('Credis_Client')) {
                error_log($log_prefix.'Using Credis backend.');
                $backend = new Cache_Hive_Redis_Credis_Backend($config);
            } else {
                error_log($log_prefix.'Credis selected but not found.');
            }
        } elseif ($method === 'memcached') {
            if (class_exists('Memcached')) {
                error_log($log_prefix.'Using Memcached backend.');
                $backend = new Cache_Hive_Memcached_Backend($config);
            } else {
                error_log($log_prefix.'Memcached selected but extension not found.');
            }
        } elseif ($method === 'redis') {
            // Prefer PhpRedis, then Predis, then Credis
            if (class_exists('Redis')) {
                error_log($log_prefix.'Using PhpRedis backend (redis generic).');
                $backend = new Cache_Hive_Redis_PhpRedis_Backend($config);
            } elseif (class_exists('Predis\\Client')) {
                error_log($log_prefix.'Using Predis backend (redis generic).');
                $backend = new Cache_Hive_Redis_Predis_Backend($config);
            } elseif (class_exists('Credis_Client')) {
                error_log($log_prefix.'Using Credis backend (redis generic).');
                $backend = new Cache_Hive_Redis_Credis_Backend($config);
            }
        }
        // Fallback
        if (!$backend) {
            error_log($log_prefix.'Falling back to Array backend.');
            $backend = new Cache_Hive_Array_Backend($config);
        }
        // Log connection status if possible
        if (method_exists($backend, 'is_connected')) {
            $connected = $backend->is_connected();
            error_log($log_prefix.'Backend '.get_class($backend).' connection status: '.($connected ? 'CONNECTED' : 'NOT CONNECTED'));
        }
        return $backend;
    }
}
