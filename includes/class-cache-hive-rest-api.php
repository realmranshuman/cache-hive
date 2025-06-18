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
}