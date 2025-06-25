<?php
/**
 * Manages all REST API endpoints for Cache Hive.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/api/class-cache-hive-rest-cache.php';
require_once __DIR__ . '/api/class-cache-hive-rest-ttl.php';
require_once __DIR__ . '/api/class-cache-hive-rest-autopurge.php';
require_once __DIR__ . '/api/class-cache-hive-rest-exclusions.php';
require_once __DIR__ . '/api/class-cache-hive-rest-objectcache.php';
require_once __DIR__ . '/api/class-cache-hive-rest-browsercache.php';
require_once __DIR__ . '/api/class-cache-hive-rest-roles.php';

/**
 * Final class for managing all REST API endpoints for Cache Hive.
 *
 * This class registers all the routes and handles the master permission checks.
 *
 * @since 1.0.0
 */
final class Cache_Hive_REST_API {

	/**
	 * REST API namespace.
	 *
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
		// Only register per-section endpoints.
		register_rest_route(
			self::$namespace,
			'/cache',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_Cache', 'get_cache_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Cache_Hive_REST_Cache', 'update_cache_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::$namespace,
			'/ttl',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_TTL', 'get_ttl_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Cache_Hive_REST_TTL', 'update_ttl_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::$namespace,
			'/autopurge',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_AutoPurge', 'get_autopurge_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Cache_Hive_REST_AutoPurge', 'update_autopurge_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::$namespace,
			'/exclusions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_Exclusions', 'get_exclusions_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Cache_Hive_REST_Exclusions', 'update_exclusions_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::$namespace,
			'/object-cache',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_ObjectCache', 'get_object_cache_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Cache_Hive_REST_ObjectCache', 'update_object_cache_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::$namespace,
			'/browser-cache',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_BrowserCache', 'get_browser_cache_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( 'Cache_Hive_REST_BrowserCache', 'update_browser_cache_settings' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
		register_rest_route(
			self::$namespace,
			'/browser-cache/verify-nginx',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( 'Cache_Hive_REST_BrowserCache', 'verify_nginx_browser_cache' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		// Route for performing actions.
		register_rest_route(
			self::$namespace,
			'/actions/(?P<action>\S+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'perform_action' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		// Route for getting WordPress roles.
		register_rest_route(
			self::$namespace,
			'/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( 'Cache_Hive_REST_Roles', 'get_roles' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
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
}
