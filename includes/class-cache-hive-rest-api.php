<?php
/**
 * Manages all REST API endpoints for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Includes\API\Cache_Hive_REST_Autopurge;
use Cache_Hive\Includes\API\Cache_Hive_REST_BrowserCache;
use Cache_Hive\Includes\API\Cache_Hive_REST_Cache;
use Cache_Hive\Includes\API\Cache_Hive_REST_Exclusions;
use Cache_Hive\Includes\API\Cache_Hive_REST_ObjectCache;
use Cache_Hive\Includes\API\Cache_Hive_REST_Optimizers_CSS;
use Cache_Hive\Includes\API\Cache_Hive_REST_Optimizers_HTML;
use Cache_Hive\Includes\API\Cache_Hive_REST_Optimizers_JS;
use Cache_Hive\Includes\API\Cache_Hive_REST_Optimizers_Media;
use Cache_Hive\Includes\API\Cache_Hive_REST_Roles;
use Cache_Hive\Includes\API\Cache_Hive_REST_TTL;
use WP_REST_Server;

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
require_once __DIR__ . '/api/class-cache-hive-rest-optimizers-css.php';
require_once __DIR__ . '/api/class-cache-hive-rest-optimizers-js.php';
require_once __DIR__ . '/api/class-cache-hive-rest-optimizers-html.php';
require_once __DIR__ . '/api/class-cache-hive-rest-optimizers-media.php';

/**
 * Manages all REST API endpoints for Cache Hive.
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
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST API routes.
	 */
	public static function register_routes() {
		$routes = array(
			'/cache'            => Cache_Hive_REST_Cache::class,
			'/ttl'              => Cache_Hive_REST_TTL::class,
			'/autopurge'        => Cache_Hive_REST_Autopurge::class,
			'/exclusions'       => Cache_Hive_REST_Exclusions::class,
			'/object-cache'     => Cache_Hive_REST_ObjectCache::class,
			'/browser-cache'    => Cache_Hive_REST_BrowserCache::class,
			'/optimizers/css'   => Cache_Hive_REST_Optimizers_CSS::class,
			'/optimizers/js'    => Cache_Hive_REST_Optimizers_JS::class,
			'/optimizers/html'  => Cache_Hive_REST_Optimizers_HTML::class,
			'/optimizers/media' => Cache_Hive_REST_Optimizers_Media::class,
		);

		foreach ( $routes as $endpoint => $class ) {
			register_rest_route(
				self::$namespace,
				$endpoint,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $class, 'get_settings' ),
						'permission_callback' => array( __CLASS__, 'permissions_check' ),
					),
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $class, 'update_settings' ),
						'permission_callback' => array( __CLASS__, 'permissions_check' ),
					),
				)
			);
		}

		register_rest_route(
			self::$namespace,
			'/browser-cache/verify-nginx',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( Cache_Hive_REST_BrowserCache::class, 'verify_nginx_browser_cache' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::$namespace,
			'/actions/(?P<action>\S+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'perform_action' ),
				'permission_callback' => array( __CLASS__, 'permissions_check' ),
			)
		);

		register_rest_route(
			self::$namespace,
			'/roles',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( Cache_Hive_REST_Roles::class, 'get_roles' ),
					'permission_callback' => array( __CLASS__, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if the user has permissions to access the endpoint.
	 *
	 * @return bool
	 */
	public static function permissions_check() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Perform a specified action from the admin bar or other UI elements.
	 *
	 * This method acts as a router for various cache management actions.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response The JSON response for the API call.
	 */
	public static function perform_action( \WP_REST_Request $request ) {
		$action = $request->get_param( 'action' );

		switch ( $action ) {
			case 'purge_all':
				Cache_Hive_Purge::purge_all();
				return new \WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'All caches purged successfully.', 'cache-hive' ),
					),
					200
				);

			case 'purge_disk_cache':
				Cache_Hive_Purge::purge_disk_cache();
				return new \WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Disk cache purged successfully.', 'cache-hive' ),
					),
					200
				);

			case 'purge_object_cache':
				Cache_Hive_Purge::purge_object_cache();
				return new \WP_REST_Response(
					array(
						'success' => true,
						'message' => __( 'Object cache purged successfully.', 'cache-hive' ),
					),
					200
				);

			case 'purge_current_page':
				$params = $request->get_json_params();
				$url    = isset( $params['url'] ) ? esc_url_raw( $params['url'] ) : null;

				if ( ! $url ) {
					return new \WP_REST_Response(
						array(
							'success' => false,
							'message' => __( 'URL not provided for purging.', 'cache-hive' ),
						),
						400
					);
				}

				Cache_Hive_Purge::purge_url( $url );
				return new \WP_REST_Response(
					array(
						'success' => true,
						'message' => sprintf(
							/* translators: %s: The URL that was purged. */
							__( 'Cache for %s purged successfully.', 'cache-hive' ),
							esc_html( $url )
						),
					),
					200
				);

			default:
				return new \WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid action.', 'cache-hive' ),
					),
					400
				);
		}
	}
}
