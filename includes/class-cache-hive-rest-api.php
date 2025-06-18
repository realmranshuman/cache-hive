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
        return new WP_REST_Response( $settings, 200 );
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
        
        // Only allow keys that exist in defaults
        $new_settings = Cache_Hive_Settings::sanitize_settings( $params );
        // Do not merge with current_settings, just use sanitized new_settings
        update_option( 'cache_hive_settings', $new_settings );
        // After updating settings, we need to regenerate the config file.
        Cache_Hive_Disk::create_config_file( $new_settings );

        // If Cloudflare settings changed, test connection.
        if ( isset($new_settings['cloudflare_api_token']) || isset($new_settings['cloudflare_api_key']) ) {
             Cache_Hive_Cloudflare::verify_credentials();
        }
        
        // Purge cache after settings save.
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
}