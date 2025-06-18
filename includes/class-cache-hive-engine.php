<?php
/**
 * Class for handling the core caching engine operations.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Hive_Engine {
    
    /**
     * Whether the cache engine is started.
     * @var bool
     */
    public static $started = false;

    /**
     * Plugin settings from the disk or database.
     * @var array
     */
    public static $settings;

    /**
     * Start the cache engine.
     *
     * @since 1.0.0
     * @return bool True if the cache engine was started.
     */
    public static function start() {
        if ( self::should_start() ) {
            self::$started = true;
            new self();
        }
        return self::$started;
    }

    /**
     * Constructor. Hooks into WordPress to control caching.
     *
     * @since 1.0.0
     */
    private function __construct() {
        self::$settings = Cache_Hive_Settings::get_settings();
        
        // Hook in early to deliver a cached file if it exists and is valid.
        add_action( 'template_redirect', array( __CLASS__, 'deliver_cache' ), 0 );

        // If no cache was delivered, start output buffering to capture the page.
        add_action( 'template_redirect', array( __CLASS__, 'start_buffering' ), 1 );
    }

    /**
     * Determines if the cache engine should start.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function should_start() {
        if ( self::$started ) {
            error_log('[Cache Hive] Engine already started');
            return false;
        }

        // Don't run on admin pages, during cron, or for non-GET requests.
        if ( is_admin() || defined( 'DOING_CRON' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || defined( 'XMLRPC_REQUEST' ) ) {
            error_log('[Cache Hive] Bypassing due to admin/cron/ajax/xmlrpc request');
            return false;
        }

        if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
            error_log('[Cache Hive] Bypassing due to non-GET request');
            return false;
        }

        // Main switch from settings.
        if ( ! Cache_Hive_Settings::get( 'enableCache' ) ) {
            error_log('[Cache Hive] Caching is disabled in settings');
            return false;
        }
        
        // Handle REST API caching based on settings.
        if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! Cache_Hive_Settings::get( 'cacheRestApi' ) ) {
            error_log('[Cache Hive] Bypassing REST request - REST caching disabled');
            return false;
        }

        error_log('[Cache Hive] Engine starting - all checks passed');
        return true;
    }

    /**
     * Deliver the cached page for the current request if valid.
     *
     * @since 1.0.0
     */
    public static function deliver_cache() {
        if ( self::bypass_cache() ) {
            header( 'X-Cache-Hive: Bypassed' );
            return;
        }

        $cache_file = Cache_Hive_Disk::get_cache_file_path();

        if ( Cache_Hive_Disk::is_cache_valid( $cache_file ) ) {
            header( 'X-Cache-Hive: Hit' );
        } elseif ( isset(self::$settings['serveStale']) && self::$settings['serveStale'] && file_exists( $cache_file ) ) {
            header( 'X-Cache-Hive: Stale' );
        } else {
            header( 'X-Cache-Hive: Miss' );
            return; // No valid or stale cache to serve.
        }

        // Add browser caching headers if enabled.
        if ( class_exists('Cache_Hive_Browser_Cache') ) {
            Cache_Hive_Browser_Cache::send_headers(self::$settings);
        }
        
        // Deliver the file.
        readfile( $cache_file );
        exit;
    }

    /**
     * Start the output buffering.
     *
     * @since 1.0.0
     */
    public static function start_buffering() {
        // Final check before starting buffer.
        if ( self::bypass_cache() ) {
            return;
        }
        ob_start( array( __CLASS__, 'end_buffering' ) );
    }

    /**
     * End the output buffering and maybe cache the page.
     *
     * @since 1.0.0
     * @param string $buffer The output buffer contents.
     * @return string The buffer.
     */
    private static function end_buffering( $buffer ) {
        // A final, late-stage check for cacheability.
        if ( ! self::is_cacheable( $buffer ) || self::bypass_cache() ) {
            return $buffer;
        }
        
        // Page Optimization logic would go here. For now, it's skipped.

        Cache_Hive_Disk::cache_page( $buffer );
        
        return $buffer;
    }

    /**
     * Check if the buffer content is a valid, cacheable HTML page.
     *
     * @since 1.0.0
     * @param string $buffer The output buffer.
     * @return bool
     */
    public static function is_cacheable( $buffer ) {
        if ( strlen( $buffer ) < 255 ) {
            return false;
        }
        // Is it a valid HTML page?
        if ( ! preg_match( '/<html|<!DOCTYPE/i', $buffer ) ) {
            return false;
        }
        // Avoid caching XML-like files (e.g., sitemaps).
        if ( preg_match( '/<?xml/i', $buffer ) && ! preg_match( '/<!DOCTYPE/i', $buffer ) ) {
            return false;
        }
        return true;
    }
    
    /**
     * Determines if the current request should be explicitly excluded from caching.
     * This is the master list of exclusion rules.
     *
     * @since 1.0.0
     * @return bool True if the request should be bypassed.
     */
    private static function bypass_cache() {
        // WordPress native conditions that should always bypass cache.
        if ( is_404() || is_search() || is_preview() || is_trackback() || is_feed() || post_password_required() ) {
            error_log('[Cache Hive] Bypassing due to WordPress condition: ' . 
                (is_404() ? '404' : 
                (is_search() ? 'search' : 
                (is_preview() ? 'preview' : 
                (is_trackback() ? 'trackback' : 
                (is_feed() ? 'feed' : 'password required'))))));
            return true;
        }
        
        // Don't cache for logged-in users unless explicitly enabled.
        if ( ! self::$settings['cacheLoggedUsers'] && is_user_logged_in() ) {
            error_log('[Cache Hive] Bypassing due to logged-in user');
            return true;
        }

        // Don't cache for users who have commented, unless enabled.
        if ( ! self::$settings['cacheCommenters'] && isset( $_COOKIE['comment_author_' . COOKIEHASH] ) ) {
            error_log('[Cache Hive] Bypassing due to commenter');
            return true;
        }

        // Check for excluded user roles.
        if ( is_user_logged_in() && ! empty( self::$settings['excludeRoles'] ) ) {
            $user = wp_get_current_user();
            if ( ! empty( array_intersect( (array) $user->roles, self::$settings['excludeRoles'] ) ) ) {
                error_log('[Cache Hive] Bypassing due to excluded user role');
                return true;
            }
        }

        // Check for excluded URIs - each line is a separate pattern
        if ( ! empty( self::$settings['excludeUris'] ) ) {
            $request_uri = $_SERVER['REQUEST_URI'];
            $patterns = array_filter(array_map('trim', explode("\n", self::$settings['excludeUris'])));
            foreach ( $patterns as $pattern ) {
                if ( ! empty( $pattern ) ) {
                    try {
                        if ( preg_match( '#' . $pattern . '#i', $request_uri ) ) {
                            error_log('[Cache Hive] Bypassing due to excluded URI pattern: ' . $pattern);
                            return true;
                        }
                    } catch (\Exception $e) {
                        error_log('[Cache Hive] Invalid URI pattern: ' . $pattern);
                    }
                }
            }
        }

        // Check for excluded Query Strings - each line is a separate pattern
        if ( ! empty( $_GET ) && ! empty( self::$settings['excludeQueryStrings'] ) ) {
            $patterns = array_filter(array_map('trim', explode("\n", self::$settings['excludeQueryStrings'])));
            foreach ( array_keys( $_GET ) as $query_key ) {
                foreach ( $patterns as $pattern ) {
                    if ( ! empty( $pattern ) ) {
                        try {
                            if ( preg_match( '#' . $pattern . '#i', $query_key ) ) {
                                error_log('[Cache Hive] Bypassing due to excluded query string: ' . $query_key . ' matching pattern: ' . $pattern);
                                return true;
                            }
                        } catch (\Exception $e) {
                            error_log('[Cache Hive] Invalid query string pattern: ' . $pattern);
                        }
                    }
                }
            }
        }

        // Check for excluded Cookies - each line is a separate pattern
        if ( ! empty( $_COOKIE ) && ! empty( self::$settings['excludeCookies'] ) ) {
            $patterns = array_filter(array_map('trim', explode("\n", self::$settings['excludeCookies'])));
            foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
                foreach ( $patterns as $pattern ) {
                    if ( ! empty( $pattern ) ) {
                        try {
                            if ( preg_match( '#' . $pattern . '#i', $cookie_name ) ) {
                                error_log('[Cache Hive] Bypassing due to excluded cookie: ' . $cookie_name . ' matching pattern: ' . $pattern);
                                return true;
                            }
                        } catch (\Exception $e) {
                            error_log('[Cache Hive] Invalid cookie pattern: ' . $pattern);
                        }
                    }
                }
            }
        }
        
        /**
         * Final filter to allow developers to bypass the cache.
         * @since 1.0.0
         * @param bool false Default bypass status.
         */
        $bypass = apply_filters( 'cache_hive_bypass_cache', false );
        if ($bypass) {
            error_log('[Cache Hive] Bypassing due to cache_hive_bypass_cache filter');
        }
        return $bypass;
    }
    
    /**
     * Checks if the current visitor is a mobile device based on User Agent.
     *
     * @since 1.0.0
     * @return bool
     */    public static function is_mobile() {
        if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
            return false;
        }

        if ( ! self::$settings['cacheMobile'] ) {
            return false;
        }

        $mobile_user_agents = self::$settings['mobileUserAgents'];
        $user_agents = array_filter( array_map( 'trim', explode( "\n", $mobile_user_agents ) ) );
        
        if ( empty($user_agents) ) {
            return false;
        }

        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        foreach ( $user_agents as $pattern ) {
            if ( ! empty( $pattern ) ) {
                try {
                    if ( preg_match( '#' . $pattern . '#i', $user_agent ) ) {
                        error_log('[Cache Hive] Detected mobile device matching pattern: ' . $pattern);
                        return true;
                    }
                } catch (\Exception $e) {
                    error_log('[Cache Hive] Invalid mobile user agent pattern: ' . $pattern);
                }
            }
        }
        
        return false;
    }
}