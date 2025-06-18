<?php
/**
 * Class for handling all disk-related operations.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Hive_Disk {
    
    /**
     * Setup environment: create advanced-cache.php and set WP_CACHE constant.
     *
     * @since 1.0.0
     */
    public static function setup_environment() {
        self::create_advanced_cache_file();
        self::set_wp_cache_constant( true );
    }

    /**
     * Cleanup environment: remove advanced-cache.php and unset WP_CACHE constant.
     *
     * @since 1.0.0
     */
    public static function cleanup_environment() {
        if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
            @unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
        }
        self::set_wp_cache_constant( false );
        self::delete_config_file();
    }
    
    /**
     * Creates the advanced-cache.php file in wp-content.
     *
     * @since 1.0.0
     * @return bool Success or failure.
     */
    public static function create_advanced_cache_file() {
        if ( ! is_writable( WP_CONTENT_DIR ) ) {
            // You might want to log this or create an admin notice for the user.
            return false;
        }
        // Path to the template drop-in file inside your plugin.
        $advanced_cache_source_file = CACHE_HIVE_DIR . 'advanced-cache.php';
        // Destination path for the drop-in.
        $advanced_cache_destination_file = WP_CONTENT_DIR . '/advanced-cache.php';
        if ( ! is_readable( $advanced_cache_source_file ) ) {
            // The source file is missing from your plugin directory.
            return false;
        }
        // Since the new advanced-cache.php is self-contained, we can just copy it.
        // No more string replacement is needed.
        return copy( $advanced_cache_source_file, $advanced_cache_destination_file );
    }
    
    /**
     * Sets or unsets the WP_CACHE constant in wp-config.php.
     *
     * @since 1.0.0
     * @param bool $enable True to set the constant, false to remove.
     */
    private static function set_wp_cache_constant( $enable = true ) {
        $config_path = self::find_wp_config_path();
        if ( ! $config_path || ! is_writable( $config_path ) ) {
            return;
        }
        
        $config_content = file_get_contents( $config_path );
        $define_string = "define( 'WP_CACHE', true ); // Added by Cache Hive";
        
        // Remove any existing definitions first to avoid duplicates
        $config_content = preg_replace( "/^[\t\s]*define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*.*\s*\);.*?\R/mi", '', $config_content );

        if ( $enable ) {
            $config_content = preg_replace(
                '/(<\?php)/',
                "<?php\n" . $define_string,
                $config_content,
                1
            );
        }
        
        file_put_contents( $config_path, $config_content, LOCK_EX );
    }
    
    /**
     * Create the config file with current settings for advanced-cache.php to read.
     *
     * @since 1.0.0
     * @param array $settings The settings array.
     */
    public static function create_config_file( $settings ) {
        if ( ! is_dir( CACHE_HIVE_CONFIG_DIR ) ) {
            @mkdir( CACHE_HIVE_CONFIG_DIR, 0755, true );
        }

        $config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
        $contents = '<?php return ' . var_export( $settings, true ) . ';';
        file_put_contents( $config_file, $contents, LOCK_EX );
    }
    
    /**
     * Deletes the config file.
     * @since 1.0.0
     */
    public static function delete_config_file() {
         $config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
         if ( file_exists($config_file) ) {
            @unlink($config_file);
         }
         if ( is_dir(CACHE_HIVE_CONFIG_DIR) ) {
            @rmdir(CACHE_HIVE_CONFIG_DIR);
         }
    }

    /**
     * Get the full path to the cache file for the current request.
     *
     * @since 1.0.0
     * @return string The cache file path.
     */
    public static function get_cache_file_path() {
        $uri = strtok( $_SERVER['REQUEST_URI'], '?' );
        $uri = rtrim( $uri, '/' ); // Normalize trailing slash
        if ( empty( $uri ) ) {
            $uri = '/__index__'; // Special name for homepage
        }

        $host = strtolower( $_SERVER['HTTP_HOST'] );
        $path_parts = [$host];

        // For logged-in users, add hashed user folder after domain
        if ( function_exists('is_user_logged_in') && is_user_logged_in() && function_exists('wp_get_current_user') ) {
            $settings = method_exists('Cache_Hive_Settings', 'get_settings') ? Cache_Hive_Settings::get_settings() : [];
            if ( isset($settings['cacheLoggedUsers']) && $settings['cacheLoggedUsers'] ) {
                $user = wp_get_current_user();
                // Use a hash for the user folder for privacy
                $user_hash = 'user_' . md5($user->ID . (defined('AUTH_KEY') ? AUTH_KEY : 'cachehive'));
                $path_parts[] = $user_hash;
            }
        }

        $dir_path = CACHE_HIVE_CACHE_DIR . '/' . implode('/', $path_parts) . $uri;
        $file_name = (method_exists('Cache_Hive_Engine', 'is_mobile') && Cache_Hive_Engine::is_mobile()) ? 'index-mobile.html' : 'index.html';
        return $dir_path . '/' . $file_name;
    }

    /**
     * Creates a static HTML file and its metadata file.
     *
     * @since 1.0.0
     * @param string $buffer The page content to cache.
     */
    public static function cache_page( $buffer ) {
        $cache_file = self::get_cache_file_path();
        $meta_file = $cache_file . '.meta';
        $cache_dir = dirname( $cache_file );

        if ( ! is_dir( $cache_dir ) ) {
            if ( ! @mkdir( $cache_dir, 0755, true ) ) {
                error_log( "[Cache Hive] Failed to create cache directory: {$cache_dir}" );
                return;
            }
            error_log( "[Cache Hive] Created cache directory: {$cache_dir}" );
        }
        
        $cache_created = file_put_contents( $cache_file, $buffer . self::get_cache_signature(), LOCK_EX );

        if ( $cache_created ) {
            // Detect if this is a private cache by checking for /user_ in the path
            if (strpos($cache_file, '/user_') !== false || strpos($cache_file, '\\user_') !== false) {
                $settings = Cache_Hive_Settings::get_settings();
                $ttl = isset($settings['privateCacheTTL']) ? $settings['privateCacheTTL'] : 1800;
            } else {
                $ttl = self::get_current_page_ttl();
            }
            error_log('[Cache Hive DEBUG] TTL for cache file ' . $cache_file . ' is: ' . var_export($ttl, true));
            $meta_data = [
                'created' => time(),
                'ttl' => $ttl, // Use seconds directly, do not multiply by HOUR_IN_SECONDS
                'url' => ( isset( $_SERVER['HTTPS'] ) ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
            ];
            file_put_contents( $meta_file, json_encode( $meta_data ), LOCK_EX );
            error_log( "[Cache Hive] Created cache file: {$cache_file} with TTL: {$ttl} seconds" );
        } else {
            error_log( "[Cache Hive] Failed to create cache file: {$cache_file}" );
        }
    }

    /**
     * Checks if a cache file is valid (exists and is not expired).
     *
     * @since 1.0.0
     * @param string $cache_file The full path to the cache file.
     * @return bool
     */
    public static function is_cache_valid( $cache_file ) {
        $meta_file = $cache_file . '.meta';

        if ( ! @is_readable( $cache_file ) ) {
            error_log( "[Cache Hive] Cache file not readable: {$cache_file}" );
            return false;
        }
        
        if ( ! @is_readable( $meta_file ) ) {
            error_log( "[Cache Hive] Meta file not readable: {$meta_file}" );
            return false;
        }

        $meta_data_json = file_get_contents( $meta_file );
        $meta_data = json_decode( $meta_data_json, true );

        if ( ! $meta_data || ! isset( $meta_data['created'], $meta_data['ttl'] ) ) {
            error_log( "[Cache Hive] Invalid meta data in: {$meta_file}" );
            return false;
        }

        // TTL of 0 means cache indefinitely.
        if ( 0 === $meta_data['ttl'] ) {
            error_log( "[Cache Hive] Cache set to never expire: {$cache_file}" );
            return true;
        }

        $expires_at = $meta_data['created'] + $meta_data['ttl'];
        $is_valid = $expires_at > time();
        
        if ( ! $is_valid ) {
            error_log( sprintf( "[Cache Hive] Cache expired for %s. Created: %s, TTL: %s hours, Expired: %s", 
                $cache_file,
                date( 'Y-m-d H:i:s', $meta_data['created'] ),
                $meta_data['ttl'] / HOUR_IN_SECONDS,
                date( 'Y-m-d H:i:s', $expires_at )
            ) );
        }
        
        return $is_valid;
    }
    
    /**
     * Purges the entire page cache directory.
     *
     * @since 1.0.0
     */
    public static function purge_all() {
        if ( ! is_dir( CACHE_HIVE_CACHE_DIR ) ) {
            return;
        }
        self::delete_directory( CACHE_HIVE_CACHE_DIR );
    }

    /**
     * Purges a single URL.
     *
     * @since 1.0.0
     * @param string $url The URL to purge.
     */
    public static function purge_url( $url ) {
        $url_parts = parse_url( $url );
        $uri = rtrim( $url_parts['path'], '/' );
        if ( empty( $uri ) ) {
            $uri = '/__index__';
        }
        
        $host = strtolower( $url_parts['host'] );
        $dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

        if ( is_dir( $dir_path ) ) {
            self::delete_directory( $dir_path );
        }
    }

    /**
     * Purges the entire private cache for all users.
     *
     * @since 1.0.0
     */
    public static function purge_all_private() {
        $cache_dir = CACHE_HIVE_CACHE_DIR;
        if ( ! is_dir( $cache_dir ) ) return;
        $domains = scandir($cache_dir);
        foreach ($domains as $domain) {
            if ($domain === '.' || $domain === '..') continue;
            $domain_path = $cache_dir . '/' . $domain;
            if (!is_dir($domain_path)) continue;
            $subdirs = scandir($domain_path);
            foreach ($subdirs as $subdir) {
                if (strpos($subdir, 'user_') === 0) {
                    $user_path = $domain_path . '/' . $subdir;
                    if (is_dir($user_path)) {
                        self::delete_directory($user_path);
                    }
                }
            }
        }
    }

    /**
     * Purges a single private URL for all users.
     *
     * @since 1.0.0
     * @param string $url The URL to purge.
     */
    public static function purge_private_url( $url ) {
        $url_parts = parse_url( $url );
        $uri = rtrim( $url_parts['path'], '/' );
        if ( empty( $uri ) ) {
            $uri = '/__index__';
        }
        $host = strtolower( $url_parts['host'] );
        $dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;
        if ( is_dir( $dir_path ) ) {
            $subdirs = scandir($dir_path);
            foreach ($subdirs as $subdir) {
                if (strpos($subdir, 'user_') === 0) {
                    $user_path = $dir_path . '/' . $subdir;
                    if (is_dir($user_path)) {
                        self::delete_directory($user_path);
                    }
                }
            }
        }
    }

    /**
     * Recursively deletes a directory.
     *
     * @since 1.0.0
     * @param string $dir Path to the directory.
     */
    private static function delete_directory( $dir ) {
        if ( ! file_exists( $dir ) ) {
            return;
        }
        $it = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                @rmdir( $file->getRealPath() );
            } else {
                @unlink( $file->getRealPath() );
            }
        }
        @rmdir( $dir );
    }
    
    /**
     * Determines the correct TTL for the current page being cached.
     *
     * @since 1.0.0
     * @return int TTL in hours.
     */
    private static function get_current_page_ttl() {
        $settings = Cache_Hive_Settings::get_settings();

        if ( is_front_page() || is_home() ) return $settings['frontPageTTL'];
        if ( is_feed() ) return $settings['feedTTL'];
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return $settings['restTTL'];
        
        // Private cache for logged-in users.
        if ( is_user_logged_in() ) return $settings['privateCacheTTL'];
        
        // Default public TTL.
        return $settings['publicCacheTTL'];
    }

    /**
     * Gets the Cache Hive signature to append to cached files.
     * @since 1.0.0
     * @return string
     */
    private static function get_cache_signature() {
        return '<!-- Cache served by Cache Hive on ' . date( 'Y-m-d H:i:s' ) . ' -->';
    }

    /**
     * Finds the path to wp-config.php.
     * @since 1.0.0
     * @return string|bool Path to wp-config.php or false if not found.
     */
    private static function find_wp_config_path() {
        if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
            return ABSPATH . 'wp-config.php';
        }
        if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
            return dirname( ABSPATH ) . '/wp-config.php';
        }
        return false;
    }

    /**
     * Register hooks for cache purging on logout.
     */
    public static function register_hooks() {
        add_action('wp_logout', [__CLASS__, 'purge_current_user_private_cache']);
    }

    /**
     * Purge the private cache for the current user on logout.
     */
    public static function purge_current_user_private_cache() {
        if ( function_exists('wp_get_current_user') ) {
            $user = wp_get_current_user();
            if ( $user && $user->ID ) {
                self::purge_user_private_cache( $user->ID );
            }
        }
    }

    /**
     * Purge all private cache folders for a given user ID.
     * @param int $user_id
     */
    public static function purge_user_private_cache( $user_id ) {
        $cache_dir = CACHE_HIVE_CACHE_DIR;
        if ( ! is_dir( $cache_dir ) ) return;
        $user_hash = 'user_' . md5($user_id . (defined('AUTH_KEY') ? AUTH_KEY : 'cachehive'));
        $domains = scandir($cache_dir);
        foreach ($domains as $domain) {
            if ($domain === '.' || $domain === '..') continue;
            $domain_path = $cache_dir . '/' . $domain;
            if (!is_dir($domain_path)) continue;
            $user_path = $domain_path . '/' . $user_hash;
            if (is_dir($user_path)) {
                self::delete_directory($user_path);
            }
        }
    }
}