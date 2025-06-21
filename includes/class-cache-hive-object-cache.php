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

        // Compose a minimal drop-in loader that requires backend classes from the plugin
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '// Cache Hive Object Cache Drop-in v2 - prevents core cache.php from loading';
        $lines[] = 'if ( defined(\'WP_OBJECT_CACHE_LOADED\') ) return;';
        $lines[] = 'define(\'WP_OBJECT_CACHE_LOADED\', true);';
        $lines[] = 'if ( ! defined(\'WP_CACHE\') ) define(\'WP_CACHE\', true);';
        $lines[] = 'if ( ! defined(\'ABSPATH\') ) exit;';
        $lines[] = 'define(\'CACHE_HIVE_PLUGIN_PATH\', WP_CONTENT_DIR . "/plugins/cache-hive/");';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/interface-backend.php";';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/backend-phpredis.php";';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/backend-predis.php";';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/backend-credis.php";';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/backend-memcached.php";';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/backend-array.php";';
        $lines[] = 'require_once CACHE_HIVE_PLUGIN_PATH . "includes/object-cache/factory.php";';
        $lines[] = ''; // instantiate backend and define wp_cache_* functions as before
        $lines[] = 'global $wp_object_cache;';
        $lines[] = '$config = ' . var_export($config, true) . ';';
        $lines[] = '$wp_object_cache = Cache_Hive_Object_Cache_Factory::create($config);';
        $lines[] = ''; // ...wp_cache_* function definitions (with function_exists checks)...
        $lines[] = 'if (!function_exists(\'wp_cache_get\')) {';
        $lines[] = 'function wp_cache_get($key, $group = \'\', $force = false, &$found = null) {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->get("{$group}:{$key}", $found);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_set\')) {';
        $lines[] = 'function wp_cache_set($key, $value, $group = \'\', $expire = 0) {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->set("{$group}:{$key}", $value, $expire);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_add\')) {';
        $lines[] = 'function wp_cache_add($key, $value, $group = \'\', $expire = 0) {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->add("{$group}:{$key}", $value, $expire);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_replace\')) {';
        $lines[] = 'function wp_cache_replace($key, $value, $group = \'\', $expire = 0) {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->replace("{$group}:{$key}", $value, $expire);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_delete\')) {';
        $lines[] = 'function wp_cache_delete($key, $group = \'\') {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->delete("{$group}:{$key}");';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_flush\')) {';
        $lines[] = 'function wp_cache_flush() {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->flush(false);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_incr\')) {';
        $lines[] = 'function wp_cache_incr($key, $offset = 1, $group = \'\') {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->increment("{$group}:{$key}", $offset);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_decr\')) {';
        $lines[] = 'function wp_cache_decr($key, $offset = 1, $group = \'\') {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->decrement("{$group}:{$key}", $offset);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_close\')) {';
        $lines[] = 'function wp_cache_close() {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    return $wp_object_cache->close();';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = 'if (!function_exists(\'wp_cache_get_multi\')) {';
        $lines[] = 'function wp_cache_get_multi($keys, $group = \'\') {';
        $lines[] = '    global $wp_object_cache;';
        $lines[] = '    $prefixed = array_map(function($key) use ($group) { return "{$group}:{$key}"; }, $keys);';
        $lines[] = '    return $wp_object_cache->get_multiple($prefixed);';
        $lines[] = '}';
        $lines[] = '}';
        $lines[] = '';
        return implode("\n", $lines);
    }
}