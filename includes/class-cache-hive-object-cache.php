<?php
/**
 * Handles object cache settings and integration for Cache Hive.
 *
 * This class is responsible for creating and removing the object-cache.php drop-in.
 * It generates a self-contained drop-in with multiple backend strategies.
 *
 * @since 1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Hive_Object_Cache {

    /**
     * The full path to the object-cache.php drop-in.
     * @var string
     */
    private static $dropin_path;

    /**
     * A unique marker to identify the Cache Hive drop-in.
     * @var string
     */
    private const DROPIN_MARKER = 'Cache Hive Object Cache Drop-in v2';

    /**
     * Initializes the object cache handler.
     */
    public static function init() {
        self::$dropin_path = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/object-cache.php' : false;
    }

    /**
     * Manages the object cache drop-in based on settings.
     *
     * @param array|null $settings The plugin settings.
     */
    public static function manage_dropin( $settings = null ) {
        if ( ! self::$dropin_path ) {
            return;
        }

        if ( is_null( $settings ) ) {
            $settings = Cache_Hive_Settings::get_settings( true );
        }

        if ( ! empty( $settings['objectCacheEnabled'] ) ) {
            self::enable( $settings );
        } else {
            self::disable();
        }
    }

    /**
     * Enables the object cache by creating the drop-in file.
     *
     * @param array $settings The plugin settings.
     * @return bool True on success, false on failure.
     */
    public static function enable( $settings ) {
        if ( ! is_writable( WP_CONTENT_DIR ) ) {
            return false;
        }

        if ( file_exists( self::$dropin_path ) && ! self::is_dropin_active() ) {
            @rename( self::$dropin_path, self::$dropin_path . '.ch-backup' );
        }

        $dropin_content = self::get_dropin_content( $settings );
        
        if ( empty( $dropin_content ) ) {
            return false;
        }

        @file_put_contents( self::$dropin_path, $dropin_content );
        
        if ( file_exists(self::$dropin_path) && function_exists( 'wp_is_writable' ) && ! wp_is_writable( self::$dropin_path ) ) {
            @chmod( self::$dropin_path, 0644 );
        }

        return self::is_dropin_active();
    }

    /**
     * Disables the object cache by removing the drop-in file.
     */
    public static function disable() {
        if ( self::is_dropin_active() ) {
            @unlink( self::$dropin_path );
            if ( file_exists( self::$dropin_path . '.ch-backup' ) ) {
                @rename( self::$dropin_path . '.ch-backup', self::$dropin_path );
            }
        }
    }

    /**
     * Checks if the Cache Hive object cache drop-in is active.
     *
     * @return bool
     */
    public static function is_dropin_active() {
        if ( ! file_exists( self::$dropin_path ) ) {
            return false;
        }
        $content = @file_get_contents( self::$dropin_path, false, null, 0, 100 );
        return $content && strpos( $content, self::DROPIN_MARKER ) !== false;
    }
    
    /**
     * Generates the entire content for the object-cache.php drop-in.
     *
     * @param array $settings The plugin settings from the UI.
     * @return string The generated PHP code for the drop-in.
     */
    private static function get_dropin_content( $settings ) {
        $config = [
            'method'        => esc_attr($settings['objectCacheMethod']),
            'host'          => esc_attr($settings['objectCacheHost']),
            'port'          => intval($settings['objectCachePort']),
            'timeout'       => 2,
            'database'      => 0,
            'lifetime'      => intval($settings['objectCacheLifetime']),
            'user'          => esc_attr($settings['objectCacheUsername']),
            'pass'          => str_replace("'", "\\'", $settings['objectCachePassword']),
            'persistent'    => !empty($settings['objectCachePersistentConnection']) ? 'true' : 'false',
            'tls_enabled'   => 'false',
            'global_groups' => var_export((array) $settings['objectCacheGlobalGroups'], true),
            'no_cache_groups' => var_export((array) $settings['objectCacheNoCacheGroups'], true),
            'prefetch'      => 'true',
            'serializer'    => 'php',
            'compression'   => 'none',
            'flush_async'   => 'false',
        ];
        $generation_date = date( 'Y-m-d H:i:s T' );

        // Compose all backend classes and the factory for the drop-in, stripping <?php tags and require_once lines
        $strip_php = function($code) {
            $code = preg_replace('/<\?php\s*/i', '', $code);
            // Remove require_once lines for backend files
            $code = preg_replace('/require_once\s*__DIR__\s*\.\s*\'\/interface-backend.php\'\s*;\s*/i', '', $code);
            $code = preg_replace('/require_once\s*__DIR__\s*\.\s*\'\/backend-[a-z0-9\-]+.php\'\s*;\s*/i', '', $code);
            // Remove require_once for interface-backend.php in any path
            $code = preg_replace('/require_once.*interface-backend.php.*/i', '', $code);
            return $code;
        };
        $factory_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/factory.php'));
        $interface_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/interface-backend.php'));
        $phpredis_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/backend-phpredis.php'));
        $predis_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/backend-predis.php'));
        $credis_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/backend-credis.php'));
        $memcached_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/backend-memcached.php'));
        $array_code = $strip_php(file_get_contents(__DIR__ . '/object-cache/backend-array.php'));
        $wp_version = isset($GLOBALS['wp_version']) ? $GLOBALS['wp_version'] : 'unknown';
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '/**';
        $lines[] = ' * Cache Hive Object Cache Drop-in v2';
        $lines[] = ' *';
        $lines[] = ' * This file is a self-contained, high-performance object cache drop-in.';
        $lines[] = ' * It is auto-generated by the Cache Hive plugin. Do not edit directly.';
        $lines[] = ' *';
        $lines[] = ' * Generated on: {$generation_date}';
        $lines[] = ' * WordPress Version: ' . $wp_version;
        $lines[] = ' *';
        $lines[] = ' * Supported wp-config.php constants (these override UI settings):';
        $lines[] = ' *';
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_ENABLED', true );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_METHOD', 'redis' ); // 'redis' or 'memcached'";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_HOST', '127.0.0.1' ); // or '/var/run/redis/redis.sock'";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_PORT', 6379 ); // Set to 0 for UNIX sockets";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_USER', '' ); // For Memcached SASL or Redis ACL";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_PASS', 'your-password' );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_DATABASE', 0 ); // For Redis";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_TIMEOUT', 2.0 );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_PERSISTENT', true );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_TLS_ENABLED', false );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_TLS_OPTIONS', [] );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_PREFETCH', true );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_SERIALIZER', 'igbinary' ); // 'php' or 'igbinary'";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_COMPRESSION', 'lzf' ); // 'none', 'lzf', 'lz4', 'zstd'";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_FLUSH_ASYNC', true );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_GLOBAL_GROUPS', ['users', 'userlogins', 'usermeta'] );";
        $lines[] = " * define( 'CACHE_HIVE_OBJECT_CACHE_NON_PERSISTENT_GROUPS', ['counts', 'plugins'] );";
        $lines[] = ' *';
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'if ( ! defined(\'ABSPATH\' ) ) {';
        $lines[] = '    exit;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'if ( defined(\'CACHE_HIVE_OBJECT_CACHE_ENABLED\' ) && ! CACHE_HIVE_OBJECT_CACHE_ENABLED ) { return; }';
        $lines[] = 'if (isset($GLOBALS[\'wp_object_cache\']) && is_object($GLOBALS[\'wp_object_cache\']) && method_exists($GLOBALS[\'wp_object_cache\'], \'get_info\')) { return; }';
        $lines[] = '';
        // No Composer autoload logic at all
        $lines[] = '// Backend interface and all backend classes';
        $lines[] = $interface_code;
        $lines[] = $phpredis_code;
        $lines[] = $predis_code;
        $lines[] = $credis_code;
        $lines[] = $memcached_code;
        $lines[] = $array_code;
        $lines[] = $factory_code;
        $lines[] = '';
        $lines[] = 'if (!function_exists(\'cache_hive_debug_log\')) {';
        $lines[] = '    function cache_hive_debug_log($msg) {';
        $lines[] = '        if (defined(\'WP_DEBUG\') && WP_DEBUG) {';
        $lines[] = '            error_log(\'[Cache Hive] \' . (is_scalar($msg) ? $msg : var_export($msg, true)));';
        $lines[] = '        }';
        $lines[] = '    }';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '// ... rest of the drop-in ...';
        return implode("\n", $lines);
    }
}