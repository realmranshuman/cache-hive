<?php
/**
 * Manages all REST API endpoints for Cache Hive.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
        // Route for getting and updating settings
        register_rest_route( self::$namespace, '/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
            array(
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => array( __CLASS__, 'update_settings' ),
                'permission_callback' => array( __CLASS__, 'permissions_check' ),
            ),
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

        // Per-section endpoints
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
     * Get all plugin settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        // Map backend config keys to frontend field names and convert textarea fields to newline-delimited strings
        $map = [
            // Cache Tab
            'enableCache' => 'enableCache',
            'cacheLoggedUsers' => 'cacheLoggedUsers',
            'cacheCommenters' => 'cacheCommenters',
            'cacheRestApi' => 'cacheRestApi',
            'cacheMobile' => 'cacheMobile',
            'mobileUserAgents' => 'mobileUserAgents',
            // TTL Tab
            'publicCacheTTL' => 'publicCacheTTL',
            'privateCacheTTL' => 'privateCacheTTL',
            'frontPageTTL' => 'frontPageTTL',
            'feedTTL' => 'feedTTL',
            'restTTL' => 'restTTL',
            // Auto Purge Tab
            'autoPurgeEntireSite' => 'autoPurgeEntireSite',
            'autoPurgeFrontPage' => 'autoPurgeFrontPage',
            'autoPurgeHomePage' => 'autoPurgeHomePage',
            'autoPurgePages' => 'autoPurgePages',
            'autoPurgeAuthorArchive' => 'autoPurgeAuthorArchive',
            'autoPurgePostTypeArchive' => 'autoPurgePostTypeArchive',
            'autoPurgeYearlyArchive' => 'autoPurgeYearlyArchive',
            'autoPurgeMonthlyArchive' => 'autoPurgeMonthlyArchive',
            'autoPurgeDailyArchive' => 'autoPurgeDailyArchive',
            'autoPurgeTermArchive' => 'autoPurgeTermArchive',
            'purgeOnUpgrade' => 'purgeOnUpgrade',
            'serveStale' => 'serveStale',
            'customPurgeHooks' => 'customPurgeHooks',
            // Exclusions Tab
            'excludeUris' => 'excludeUris',
            'excludeQueryStrings' => 'excludeQueryStrings',
            'excludeCookies' => 'excludeCookies',
            'excludeRoles' => 'excludeRoles',
            // Browser Cache Tab
            'browserCacheEnabled' => 'browserCacheEnabled',
            'browserCacheTTL' => 'browserCacheTTL',
            // Object Cache Tab
            'objectCacheEnabled' => 'objectCacheEnabled',
            'objectCacheMethod' => 'objectCacheMethod',
            'objectCacheHost' => 'objectCacheHost',
            'objectCachePort' => 'objectCachePort',
            'objectCacheLifetime' => 'objectCacheLifetime',
            'objectCacheUsername' => 'objectCacheUsername',
            'objectCachePassword' => 'objectCachePassword',
            'objectCacheGlobalGroups' => 'objectCacheGlobalGroups',
            'objectCacheNoCacheGroups' => 'objectCacheNoCacheGroups',
            'objectCachePersistentConnection' => 'objectCachePersistentConnection',
        ];
        $textarea_fields = [
            'mobileUserAgents', 'customPurgeHooks', 'excludeUris', 'excludeQueryStrings', 'excludeCookies',
            'objectCacheGlobalGroups', 'objectCacheNoCacheGroups'
        ];
        $frontend = [];
        foreach ($map as $configKey => $frontendKey) {
            if (isset($settings[$configKey])) {
                if (in_array($configKey, $textarea_fields)) {
                    $frontend[$frontendKey] = $settings[$configKey]; // Always return as newline-delimited string
                } else {
                    $frontend[$frontendKey] = $settings[$configKey];
                }
            }
        }
        return new WP_REST_Response($frontend, 200);
    }

    /**
     * Update plugin settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        if ( empty( $params ) ) {
            return new WP_REST_Response( ['error' => 'No settings provided.'], 400 );
        }
        // Map frontend field names to backend config keys (camelCase, as in config.php)
        $reverse_map = [
            // Cache Tab
            'enableCache' => 'enableCache',
            'cacheLoggedUsers' => 'cacheLoggedUsers',
            'cacheCommenters' => 'cacheCommenters',
            'cacheRestApi' => 'cacheRestApi',
            'cacheMobile' => 'cacheMobile',
            'mobileUserAgents' => 'mobileUserAgents',
            // TTL Tab
            'publicCacheTTL' => 'publicCacheTTL',
            'privateCacheTTL' => 'privateCacheTTL',
            'frontPageTTL' => 'frontPageTTL',
            'feedTTL' => 'feedTTL',
            'restTTL' => 'restTTL',
            // Auto Purge Tab
            'autoPurgeEntireSite' => 'autoPurgeEntireSite',
            'autoPurgeFrontPage' => 'autoPurgeFrontPage',
            'autoPurgeHomePage' => 'autoPurgeHomePage',
            'autoPurgePages' => 'autoPurgePages',
            'autoPurgeAuthorArchive' => 'autoPurgeAuthorArchive',
            'autoPurgePostTypeArchive' => 'autoPurgePostTypeArchive',
            'autoPurgeYearlyArchive' => 'autoPurgeYearlyArchive',
            'autoPurgeMonthlyArchive' => 'autoPurgeMonthlyArchive',
            'autoPurgeDailyArchive' => 'autoPurgeDailyArchive',
            'autoPurgeTermArchive' => 'autoPurgeTermArchive',
            'purgeOnUpgrade' => 'purgeOnUpgrade',
            'serveStale' => 'serveStale',
            'customPurgeHooks' => 'customPurgeHooks',
            // Exclusions Tab
            'excludeUris' => 'excludeUris',
            'excludeQueryStrings' => 'excludeQueryStrings',
            'excludeCookies' => 'excludeCookies',
            'excludeRoles' => 'excludeRoles',
            // Browser Cache Tab
            'browserCacheEnabled' => 'browserCacheEnabled',
            'browserCacheTTL' => 'browserCacheTTL',
            // Object Cache Tab
            'objectCacheEnabled' => 'objectCacheEnabled',
            'objectCacheMethod' => 'objectCacheMethod',
            'objectCacheHost' => 'objectCacheHost',
            'objectCachePort' => 'objectCachePort',
            'objectCacheLifetime' => 'objectCacheLifetime',
            'objectCacheUsername' => 'objectCacheUsername',
            'objectCachePassword' => 'objectCachePassword',
            'objectCacheGlobalGroups' => 'objectCacheGlobalGroups',
            'objectCacheNoCacheGroups' => 'objectCacheNoCacheGroups',
            'objectCachePersistentConnection' => 'objectCachePersistentConnection',
        ];
        $textarea_fields = [
            'mobileUserAgents', 'customPurgeHooks', 'excludeUris', 'excludeQueryStrings', 'excludeCookies',
            'objectCacheGlobalGroups', 'objectCacheNoCacheGroups'
        ];
        $backend = [];
        foreach ($params as $frontendKey => $value) {
            if (isset($reverse_map[$frontendKey])) {
                $configKey = $reverse_map[$frontendKey];
                if (in_array($frontendKey, $textarea_fields)) {
                    $backend[$configKey] = trim((string)$value); // Always save as string
                } elseif (is_bool($value)) {
                    $backend[$configKey] = (bool)$value;
                } elseif (is_numeric($value) && strpos($frontendKey, 'TTL') !== false) {
                    $backend[$configKey] = (int)$value;
                } else {
                    $backend[$configKey] = $value;
                }
            }
        }
        $new_settings = Cache_Hive_Settings::sanitize_settings( $backend );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );
        if ( isset($new_settings['cloudflare_api_token']) || isset($new_settings['cloudflare_api_key']) ) {
             Cache_Hive_Cloudflare::verify_credentials();
        }
        Cache_Hive_Purge::purge_all();
        return new WP_REST_Response( Cache_Hive_Settings::get_settings(true), 200 );
    }

    /**
     * Perform a specified action.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function perform_action( WP_REST_Request $request ) {
        $action = $request->get_param('action');
        $data = $request->get_json_params();

        switch ( $action ) {
            case 'purge_all':
                Cache_Hive_Purge::purge_all();
                return new WP_REST_Response( ['message' => 'All caches purged.'], 200 );
            
            case 'purge_cloudflare':
                $result = Cache_Hive_Cloudflare::purge_all();
                if ( $result['success'] ) {
                    return new WP_REST_Response( ['message' => 'Cloudflare cache purged successfully.'], 200 );
                } else {
                    return new WP_REST_Response( ['error' => $result['message']], 400 );
                }

            case 'toggle_cf_dev_mode':
                 $status = isset($data['status']) ? (bool) $data['status'] : false;
                 $result = Cache_Hive_Cloudflare::set_dev_mode( $status );
                 if ( $result['success'] ) {
                    return new WP_REST_Response( ['message' => 'Cloudflare Development Mode updated.'], 200 );
                 } else {
                    return new WP_REST_Response( ['error' => $result['message']], 400 );
                 }
                 
            default:
                return new WP_REST_Response( ['error' => 'Invalid action specified.'], 404 );
        }
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
     * Get browser cache settings.
     *
     * @since 1.0.0
     * @return WP_REST_Response
     */
    public static function get_browser_cache_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $browser_cache_settings = [
            'browserCacheEnabled' => isset( $settings['browserCacheEnabled'] ) ? $settings['browserCacheEnabled'] : '',
            'browserCacheTTL' => isset( $settings['browserCacheTTL'] ) ? $settings['browserCacheTTL'] : '',
        ];
        return new WP_REST_Response( $browser_cache_settings, 200 );
    }

    /**
     * Update browser cache settings.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response
     */
    public static function update_browser_cache_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'browserCacheEnabled':
                    $updated_settings[$key] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                    break;
                case 'browserCacheTTL':
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
}