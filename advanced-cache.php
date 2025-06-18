<?php
/**
 * Cache Hive - Advanced Cache Drop-in
 *
 * This file is executed very early by WordPress if the WP_CACHE constant is enabled.
 * Its job is to check for a valid cached version of the requested page and serve
 * it directly, bypassing the full WordPress load for maximum performance.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// If WP_CACHE is not enabled, do nothing.
if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
    return;
}

class Cache_Hive_Advanced_Cache {

    /**
     * @var array Plugin settings loaded from the config file.
     */
    private $settings;

    /**
     * @var bool Whether the visitor is considered mobile.
     */
    private $is_mobile = false;

    /**
     * Kicks off the cache delivery process.
     */
    public function __construct() {
        // 1. Load settings from the fast-loading config file.
        $this->settings = $this->get_settings();

        // 2. If caching is disabled in settings, bail early.
        if ( empty( $this->settings ) || ! $this->settings['enableCache'] ) {
            return;
        }

        // 3. Run essential checks to see if we should bypass caching.
        if ( $this->should_bypass() ) {
            return;
        }
        
        // 4. Determine if the request is for a mobile-specific cache.
        $this->is_mobile = $this->check_if_mobile();

        // 5. Attempt to find and deliver a valid cache file.
        $this->deliver_cache();
    }

    /**
     * Loads settings from the config file. This is much faster than get_option().
     *
     * @return array The settings array or an empty array on failure.
     */
    private function get_settings() {
        $config_file = WP_CONTENT_DIR . '/cache-hive-config/config.php';
        if ( @is_readable( $config_file ) ) {
            return include $config_file;
        }
        return [];
    }
    
    /**
     * Performs very early, essential checks to determine if the cache should be bypassed.
     *
     * @return bool True to bypass, false to continue.
     */
    private function should_bypass() {
        // Only cache GET requests.
        if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
            return true;
        }
        
        // Don't cache for logged-in users unless specifically enabled.
        if ( ! isset($this->settings['cacheLoggedUsers']) || !$this->settings['cacheLoggedUsers'] ) {
             if ( ! empty($_COOKIE) ) {
                foreach ( $_COOKIE as $name => $value ) {
                    if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
                        return true;
                    }
                }
            }
        }
        
        // Check for WordPress post password cookie.
        if ( defined('COOKIEHASH') && ! empty( $_COOKIE['wp-postpass_' . COOKIEHASH] ) ) {
            return true;
        }

        // Don't cache for users who have commented, unless enabled.
        if ( defined('COOKIEHASH') && ! $this->settings['cacheCommenters'] && ! empty( $_COOKIE['comment_author_' . COOKIEHASH] ) ) {
            return true;
        }

        return false;
    }
    
    /**
     * Checks if the current visitor is a mobile device based on User Agent settings.
     *
     * @return bool
     */
    private function check_if_mobile() {
        if ( ! isset($this->settings['cacheMobile']) || !$this->settings['cacheMobile'] || ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return false;
        }

        $mobile_user_agents = $this->settings['mobileUserAgents'];
        $user_agents = array_filter( array_map( 'trim', explode( "\n", $mobile_user_agents ) ) );
        
        if ( empty($user_agents) ) {
            return false;
        }
        $regex = '/' . implode( '|', $user_agents ) . '/i';
        return (bool) preg_match( $regex, $_SERVER['HTTP_USER_AGENT'] );
    }

    /**
     * The core delivery logic. Finds the file, validates it, and serves it.
     */
    private function deliver_cache() {
        $cache_file = $this->get_cache_file_path();

        // Check validity
        if ( ! $this->is_cache_valid( $cache_file ) ) {
            // Cache expired: delete cache and meta files
            @unlink( $cache_file );
            @unlink( $cache_file . '.meta' );
            // Serve stale cache only if enabled and file is still readable (double-check after deletion)
            if ( isset($this->settings['serveStale']) && $this->settings['serveStale'] && @is_readable( $cache_file ) ) {
                header( 'X-Cache-Hive: Stale (Advanced)' );
            } else {
                return; // No valid or stale cache to serve.
            }
        } else {
            header( 'X-Cache-Hive: Hit (Advanced)' );
        }

        // Add browser caching headers if enabled in settings.
        // Use config keys as defined in config.php: browserCacheEnabled, browserCacheTTL
        if ( isset($this->settings['browserCacheEnabled']) && $this->settings['browserCacheEnabled'] ) {
            $ttl_days = isset($this->settings['browserCacheTTL']) ? absint( $this->settings['browserCacheTTL'] ) : 0;
            if ( $ttl_days > 0 ) {
                $ttl_seconds = $ttl_days * DAY_IN_SECONDS;
                header( 'Cache-Control: public, max-age=' . $ttl_seconds );
                header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl_seconds ) . ' GMT' );
            }
        }
        
        // Serve the file and stop execution.
        if (@is_readable($cache_file)) {
            @readfile( $cache_file );
            exit;
        }
    }
    
    /**
     * Constructs the full path to the potential cache file.
     *
     * @return string The cache file path.
     */
    private function get_cache_file_path() {
        $uri = strtok( $_SERVER['REQUEST_URI'], '?' );
        $uri = rtrim( $uri, '/' );
        if ( empty( $uri ) ) {
            $uri = '/__index__'; // Special name for homepage
        }

        $host = strtolower( $_SERVER['HTTP_HOST'] );
        $path_parts = [$host];

        // Private cache for logged-in users if enabled
        if (
            isset($this->settings['cacheLoggedUsers']) && $this->settings['cacheLoggedUsers'] &&
            !empty($_COOKIE)
        ) {
            foreach ($_COOKIE as $name => $value) {
                if (strpos($name, 'wordpress_logged_in') === 0) {
                    // Hash user folder for privacy (same as class-cache-hive-disk.php)
                    if (defined('AUTH_KEY')) {
                        // Extract user ID from cookie value (format: user_login|user_id|...)
                        $parts = explode('|', $value);
                        $user_id = isset($parts[1]) ? $parts[1] : '';
                        if ($user_id !== '') {
                            $user_hash = 'user_' . md5($user_id . AUTH_KEY);
                            $path_parts[] = $user_hash;
                        }
                    }
                    break;
                }
            }
        }

        $dir_path = WP_CONTENT_DIR . '/cache/cache-hive/' . implode('/', $path_parts) . $uri;
        $file_name = $this->is_mobile ? 'index-mobile.html' : 'index.html';
        return $dir_path . '/' . $file_name;
    }

    /**
     * Checks if a cache file is valid (exists and is not expired).
     *
     * @param string $cache_file The full path to the cache file.
     * @return bool
     */
    private function is_cache_valid( $cache_file ) {
        $meta_file = $cache_file . '.meta';

        if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
            return false;
        }

        $meta_data_json = @file_get_contents( $meta_file );
        if ( ! $meta_data_json ) {
            return false;
        }

        $meta_data = json_decode( $meta_data_json, true );

        if ( ! $meta_data || ! isset( $meta_data['created'], $meta_data['ttl'] ) ) {
            return false;
        }

        // TTL of 0 means cache indefinitely.
        if ( 0 === (int) $meta_data['ttl'] ) {
            return true;
        }
        
        return ( $meta_data['created'] + $meta_data['ttl'] ) > time();
    }
}

// Let's go!
new Cache_Hive_Advanced_Cache();