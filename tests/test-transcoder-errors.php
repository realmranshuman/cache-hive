<?php
/**
 * Test for Transcoder Error Handling.
 */

namespace Cache_Hive\Tests {

    use Cache_Hive\Includes\Object_Cache\Cache_Hive_Transcoder;

    // Mocking WordPress environment
    if (!defined('ABSPATH')) {
        define('ABSPATH', true);
    }
}

namespace {
    if (!function_exists('igbinary_serialize')) {
        function igbinary_serialize($data) {
            if ($data === 'TRIGGER_SERIALIZE_ERROR') {
                throw new \Exception('igbinary_serialize error');
            }
            return 'igbinary_serialized:' . serialize($data);
        }
    }

    if (!function_exists('igbinary_unserialize')) {
        function igbinary_unserialize($data) {
            if ($data === 'TRIGGER_UNSERIALIZE_EXCEPTION') {
                throw new \Exception('igbinary_unserialize exception');
            }
            if ($data === 'TRIGGER_UNSERIALIZE_ERROR') {
                // In some PHP versions/extensions, it might throw Error instead of Exception
                throw new \Error('igbinary_unserialize error');
            }
            return unserialize(str_replace('igbinary_serialized:', '', $data));
        }
    }
}

namespace Cache_Hive\Tests {

    use Cache_Hive\Includes\Object_Cache\Cache_Hive_Transcoder;

    // Mocking WP salts to have a predictable key
    if (!defined('WP_CACHE_KEY')) {
        define('WP_CACHE_KEY', 'test-secret-key');
    }

    require_once __DIR__ . '/../includes/object-cache/class-cache-hive-transcoder.php';

    function run_test($name, $callback) {
        echo "Running test: $name... ";
        try {
            if ($callback()) {
                echo "PASS\n";
                return true;
            } else {
                echo "FAIL\n";
                return false;
            }
        } catch (\Throwable $e) {
            echo "FAIL (Caught " . get_class($e) . ": " . $e->getMessage() . ")\n";
            return false;
        }
    }

    $success = true;

    $success &= run_test('igbinary_unserialize exception handling', function() {
        $config = ['serializer' => 'igbinary'];
        $transcoder = new Cache_Hive_Transcoder($config);

        $serialized_data = 'TRIGGER_UNSERIALIZE_EXCEPTION';
        $secret_key = 'test-secret-key';
        $signature = hash_hmac('sha256', $serialized_data, $secret_key, true);
        $payload = $signature . $serialized_data;

        return $transcoder->decode($payload) === false;
    });

    $success &= run_test('php_unserialize error handling', function() {
        $config = ['serializer' => 'php'];
        $transcoder = new Cache_Hive_Transcoder($config);

        // Corrupt serialized data for PHP
        $serialized_data = 's:5:"test";'; // Correct would be s:4:"test";
        $secret_key = 'test-secret-key';
        $signature = hash_hmac('sha256', $serialized_data, $secret_key, true);
        $payload = $signature . $serialized_data;

        return $transcoder->decode($payload) === false;
    });

    $success &= run_test('igbinary_unserialize error handling', function() {
        $config = ['serializer' => 'igbinary'];
        $transcoder = new Cache_Hive_Transcoder($config);

        $serialized_data = 'TRIGGER_UNSERIALIZE_ERROR';
        $secret_key = 'test-secret-key';
        $signature = hash_hmac('sha256', $serialized_data, $secret_key, true);
        $payload = $signature . $serialized_data;

        return $transcoder->decode($payload) === false;
    });

    if (!$success) {
        exit(1);
    }
}
