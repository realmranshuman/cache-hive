<?php
/**
 * Manages all REST API endpoints for Cache Hive.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Cache_Hive_Browser_Cache' ) ) {
    require_once __DIR__ . '/class-cache-hive-browser-cache.php';
}

final class Cache_Hive_REST_API {

    /**
     * REST API namespace.
     * @var string
     */
    private static $namespace = 'cache-hive/v1';

    /**
     * Initialize the REST API hooks.
     *
     * @since 1.0.0
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    /**
     * Register all REST API routes.
     *
     * @since 1.0.0
     */
    public static function register_routes() {
        // Remove the /settings endpoint (no longer needed)
        // Only register per-section endpoints
        register_rest_route( self::$namespace, '/cache', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_cache_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_cache_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
        register_rest_route( self::$namespace, '/ttl', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_ttl_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_ttl_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
        register_rest_route( self::$namespace, '/autopurge', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_autopurge_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_autopurge_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
        register_rest_route( self::$namespace, '/exclusions', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_exclusions_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_exclusions_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
        register_rest_route( self::$namespace, '/object-cache', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_object_cache_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_object_cache_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
        register_rest_route( self::$namespace, '/browser-cache', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_browser_cache_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_browser_cache_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
        register_rest_route( self::$namespace, '/browser-cache/verify-nginx', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'verify_nginx_browser_cache' ),
            'permission_callback' => array( __CLASS__, 'permissions_check' ),
        ) );

        // Route for performing actions
        register_rest_route( self::$namespace, '/actions/(?P<action>\S+)', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array( __CLASS__, 'perform_action' ),
            'permission_callback' => array( __CLASS__, 'permissions_check' ),
        ) );

        // Route for getting WordPress roles
        register_rest_route( self::$namespace, '/roles', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_roles' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
        ) );
    }

    /**
     * Check if the user has permissions to access the endpoint.
     *
     * @since 1.0.0
     * @return bool
     */
    public static function permissions_check() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get cache settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_cache_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $cache_settings = [
            'enableCache' => isset( $settings['enableCache'] ) ? $settings['enableCache'] : '',
            'cacheLoggedUsers' => isset( $settings['cacheLoggedUsers'] ) ? $settings['cacheLoggedUsers'] : '',
            'cacheCommenters' => isset( $settings['cacheCommenters'] ) ? $settings['cacheCommenters'] : '',
            'cacheRestApi' => isset( $settings['cacheRestApi'] ) ? $settings['cacheRestApi'] : '',
            'cacheMobile' => isset( $settings['cacheMobile'] ) ? $settings['cacheMobile'] : '',
            'mobileUserAgents' => isset( $settings['mobileUserAgents'] ) ? $settings['mobileUserAgents'] : '',
        ];
        return new WP_REST_Response( $cache_settings, 200 );
    }

    /**
     * Update cache settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_cache_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'enableCache':
                case 'cacheLoggedUsers':
                case 'cacheCommenters':
                case 'cacheRestApi':
                case 'cacheMobile':
                    $updated_settings[$key] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                    break;
                case 'mobileUserAgents':
                    $updated_settings[$key] = sanitize_textarea_field( $value );
                    break;
                default:
                    continue 2;
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );

        return new WP_REST_Response( $new_settings, 200 );
    }

    /**
     * Get TTL settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_ttl_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $ttl_settings = [
            'publicCacheTTL' => isset( $settings['publicCacheTTL'] ) ? $settings['publicCacheTTL'] : '',
            'privateCacheTTL' => isset( $settings['privateCacheTTL'] ) ? $settings['privateCacheTTL'] : '',
            'frontPageTTL' => isset( $settings['frontPageTTL'] ) ? $settings['frontPageTTL'] : '',
            'feedTTL' => isset( $settings['feedTTL'] ) ? $settings['feedTTL'] : '',
            'restTTL' => isset( $settings['restTTL'] ) ? $settings['restTTL'] : '',
        ];
        return new WP_REST_Response( $ttl_settings, 200 );
    }

    /**
     * Update TTL settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_ttl_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'publicCacheTTL':
                case 'privateCacheTTL':
                case 'frontPageTTL':
                case 'feedTTL':
                case 'restTTL':
                    $updated_settings[$key] = intval( $value );
                    break;
                default:
                    continue 2;
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );

        return new WP_REST_Response( $new_settings, 200 );
    }

    /**
     * Get auto-purge settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_autopurge_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $autopurge_settings = [
            'autoPurgeEntireSite' => isset( $settings['autoPurgeEntireSite'] ) ? $settings['autoPurgeEntireSite'] : '',
            'autoPurgeFrontPage' => isset( $settings['autoPurgeFrontPage'] ) ? $settings['autoPurgeFrontPage'] : '',
            'autoPurgeHomePage' => isset( $settings['autoPurgeHomePage'] ) ? $settings['autoPurgeHomePage'] : '',
            'autoPurgePages' => isset( $settings['autoPurgePages'] ) ? $settings['autoPurgePages'] : '',
            'autoPurgeAuthorArchive' => isset( $settings['autoPurgeAuthorArchive'] ) ? $settings['autoPurgeAuthorArchive'] : '',
            'autoPurgePostTypeArchive' => isset( $settings['autoPurgePostTypeArchive'] ) ? $settings['autoPurgePostTypeArchive'] : '',
            'autoPurgeYearlyArchive' => isset( $settings['autoPurgeYearlyArchive'] ) ? $settings['autoPurgeYearlyArchive'] : '',
            'autoPurgeMonthlyArchive' => isset( $settings['autoPurgeMonthlyArchive'] ) ? $settings['autoPurgeMonthlyArchive'] : '',
            'autoPurgeDailyArchive' => isset( $settings['autoPurgeDailyArchive'] ) ? $settings['autoPurgeDailyArchive'] : '',
            'autoPurgeTermArchive' => isset( $settings['autoPurgeTermArchive'] ) ? $settings['autoPurgeTermArchive'] : '',
            'purgeOnUpgrade' => isset( $settings['purgeOnUpgrade'] ) ? $settings['purgeOnUpgrade'] : '',
            'serveStale' => isset( $settings['serveStale'] ) ? $settings['serveStale'] : '',
            'customPurgeHooks' => isset( $settings['customPurgeHooks'] ) ? $settings['customPurgeHooks'] : '',
        ];
        return new WP_REST_Response( $autopurge_settings, 200 );
    }

    /**
     * Update auto-purge settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_autopurge_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'autoPurgeEntireSite':
                case 'autoPurgeFrontPage':
                case 'autoPurgeHomePage':
                case 'autoPurgePages':
                case 'autoPurgeAuthorArchive':
                case 'autoPurgePostTypeArchive':
                case 'autoPurgeYearlyArchive':
                case 'autoPurgeMonthlyArchive':
                case 'autoPurgeDailyArchive':
                case 'autoPurgeTermArchive':
                case 'purgeOnUpgrade':
                case 'serveStale':
                    $updated_settings[$key] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                    break;
                case 'customPurgeHooks':
                    $updated_settings[$key] = sanitize_textarea_field( $value );
                    break;
                default:
                    continue 2;
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );

        return new WP_REST_Response( $new_settings, 200 );
    }

    /**
     * Get exclusions settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_exclusions_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $exclusions_settings = [
            'excludeUris' => isset( $settings['excludeUris'] ) ? $settings['excludeUris'] : '',
            'excludeQueryStrings' => isset( $settings['excludeQueryStrings'] ) ? $settings['excludeQueryStrings'] : '',
            'excludeCookies' => isset( $settings['excludeCookies'] ) ? $settings['excludeCookies'] : '',
            'excludeRoles' => isset( $settings['excludeRoles'] ) && is_array($settings['excludeRoles']) ? array_values($settings['excludeRoles']) : [],
        ];
        return new WP_REST_Response( $exclusions_settings, 200 );
    }

    /**
     * Update exclusions settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_exclusions_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'excludeUris':
                case 'excludeQueryStrings':
                case 'excludeCookies':
                    $updated_settings[$key] = sanitize_textarea_field( $value );
                    break;
                case 'excludeRoles':
                    if (is_array($value)) {
                        $updated_settings[$key] = array_map('sanitize_text_field', $value);
                    } else {
                        $updated_settings[$key] = [];
                    }
                    break;
                default:
                    continue 2;
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );

        return new WP_REST_Response( $new_settings, 200 );
    }

    /**
     * Get object cache settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_object_cache_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $object_cache_settings = [
            'objectCacheEnabled' => isset( $settings['objectCacheEnabled'] ) ? $settings['objectCacheEnabled'] : false,
            'objectCacheMethod' => isset( $settings['objectCacheMethod'] ) ? $settings['objectCacheMethod'] : '',
            'objectCacheHost' => isset( $settings['objectCacheHost'] ) ? $settings['objectCacheHost'] : '',
            'objectCachePort' => isset( $settings['objectCachePort'] ) ? $settings['objectCachePort'] : '',
            'objectCacheLifetime' => isset( $settings['objectCacheLifetime'] ) ? $settings['objectCacheLifetime'] : '',
            'objectCacheUsername' => isset( $settings['objectCacheUsername'] ) ? $settings['objectCacheUsername'] : '',
            'objectCachePassword' => isset( $settings['objectCachePassword'] ) ? $settings['objectCachePassword'] : '',
            'objectCacheGlobalGroups' => isset($settings['objectCacheGlobalGroups']) ? (is_array($settings['objectCacheGlobalGroups']) ? $settings['objectCacheGlobalGroups'] : preg_split('/[\s,]+/', trim($settings['objectCacheGlobalGroups']))) : [],
            'objectCacheNoCacheGroups' => isset($settings['objectCacheNoCacheGroups']) ? (is_array($settings['objectCacheNoCacheGroups']) ? $settings['objectCacheNoCacheGroups'] : preg_split('/[\s,]+/', trim($settings['objectCacheNoCacheGroups']))) : [],
            'objectCachePersistentConnection' => isset( $settings['objectCachePersistentConnection'] ) ? $settings['objectCachePersistentConnection'] : false,
        ];
        return new WP_REST_Response( $object_cache_settings, 200 );
    }

    /**
     * Update object cache settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_object_cache_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'objectCacheEnabled':
                    $updated_settings[$key] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                    break;
                case 'objectCacheMethod':
                case 'objectCacheHost':
                case 'objectCacheUsername':
                case 'objectCachePassword':
                    $updated_settings[$key] = sanitize_text_field( $value );
                    break;
                case 'objectCachePort':
                case 'objectCacheLifetime':
                case 'objectCachePersistentConnection':
                    $updated_settings[$key] = intval( $value );
                    break;
                case 'objectCacheGlobalGroups':
                case 'objectCacheNoCacheGroups':
                    if (is_array($value)) {
                        $updated_settings[$key] = array_map('sanitize_text_field', $value);
                    } elseif (is_string($value)) {
                        $updated_settings[$key] = array_filter(array_map('sanitize_text_field', preg_split('/[\s,]+/', $value)));
                    } else {
                        $updated_settings[$key] = [];
                    }
                    break;
                default:
                    continue 2;
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );

        return new WP_REST_Response( $new_settings, 200 );
    }

    /**
     * Get browser cache settings and status (expanded).
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_browser_cache_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $server = Cache_Hive_Browser_Cache::get_server_software();
        $status = [
            'settings' => [
                'browserCacheEnabled' => isset($settings['browserCacheEnabled']) ? $settings['browserCacheEnabled'] : '',
                'browserCacheTTL' => isset($settings['browserCacheTTL']) ? $settings['browserCacheTTL'] : '',
            ],
            'server' => $server,
            'htaccessWritable' => null,
            'nginxVerified' => null,
            'rules' => '',
            'rulesPresent' => false,
        ];
        if ($server === 'apache' || $server === 'litespeed') {
            if (!function_exists('get_home_path')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $htaccess_file = trailingslashit(get_home_path()) . '.htaccess';
            $status['htaccessWritable'] = is_writable($htaccess_file);
            $rules = Cache_Hive_Browser_Cache::generate_htaccess_rules($settings);
            $status['rules'] = $rules;
            $status['rulesPresent'] = false;
            if (file_exists($htaccess_file)) {
                $contents = @file_get_contents($htaccess_file);
                if ($contents && strpos($contents, '# BEGIN Cache Hive Browser Cache') !== false && strpos($contents, '# END Cache Hive Browser Cache') !== false) {
                    $status['rulesPresent'] = true;
                }
            }
            // If not present and not writable, provide default rules (1 year)
            if (!$status['rulesPresent'] && !$status['htaccessWritable']) {
                $default_settings = $settings;
                $default_settings['browserCacheTTL'] = 31536000;
                $status['rules'] = Cache_Hive_Browser_Cache::generate_htaccess_rules($default_settings);
            }
        } elseif ($server === 'nginx') {
            $rules = Cache_Hive_Browser_Cache::generate_nginx_rules($settings);
            $status['rules'] = $rules;
            $status['nginxVerified'] = false;
            // For Nginx, you could add a similar check for rules presence if you have a way to verify
            $status['rulesPresent'] = !empty($rules); // Always true if rules generated
            if (!$status['rulesPresent']) {
                $default_settings = $settings;
                $default_settings['browserCacheTTL'] = 31536000;
                $status['rules'] = Cache_Hive_Browser_Cache::generate_nginx_rules($default_settings);
            }
        }
        return new WP_REST_Response($status, 200);
    }

    /**
     * Update browser cache settings and update .htaccess if needed.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_browser_cache_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'browserCacheEnabled':
                    $updated_settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'browserCacheTTL':
                    $updated_settings[$key] = intval($value);
                    break;
                default:
                    continue 2;
            }
        }
        $new_settings = Cache_Hive_Settings::sanitize_settings($updated_settings);
        update_option('cache_hive_settings', $new_settings);
        Cache_Hive_Disk::create_config_file($new_settings);
        $server = Cache_Hive_Browser_Cache::get_server_software();
        if ($server === 'apache' || $server === 'litespeed') {
            if (!function_exists('get_home_path')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $htaccess_file = trailingslashit(get_home_path()) . '.htaccess';
            $rules = Cache_Hive_Browser_Cache::generate_htaccess_rules($new_settings);
            $result = Cache_Hive_Browser_Cache::update_htaccess($new_settings);
            if (is_wp_error($result)) {
                // Always return the rules the user tried to save, so the frontend can show them
                // Also return the current status/settings so the frontend can revert UI
                $currentStatus = self::get_browser_cache_settings()->get_data();
                return new WP_REST_Response([
                    'error' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                    'rules' => $rules,
                    'currentStatus' => $currentStatus,
                ], 500);
            }
        }
        // On success, return the new status (not just settings)
        $status = self::get_browser_cache_settings()->get_data();
        return new WP_REST_Response($status, 200);
    }

    /**
     * Verify Nginx browser cache rules (stub).
     *
     * @since 1.0.0
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function verify_nginx_browser_cache(WP_REST_Request $request) {
        // TODO: Implement actual verification logic
        return new WP_REST_Response(['verified' => false, 'message' => 'Verification not implemented.'], 200);
    }

    /**
     * Get all WordPress roles for use in the exclusions form.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_roles() {
        if ( ! function_exists( 'get_editable_roles' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $roles = [];
        foreach ( get_editable_roles() as $role_key => $role ) {
            $roles[] = [
                'id' => $role_key,
                'name' => translate_user_role( $role['name'] ),
            ];
        }
        return new WP_REST_Response( $roles, 200 );
    }
}