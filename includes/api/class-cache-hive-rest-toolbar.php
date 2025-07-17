<?php
/**
 * Handles REST API callbacks for the Cache Hive admin toolbar.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\API;

use Cache_Hive\Includes\Cache_Hive_Purge;
use Cache_Hive\Includes\Cache_Hive_Cloudflare;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling toolbar REST API actions.
 */
final class Cache_Hive_REST_Toolbar {

	/**
	 * Handles the 'purge_all' action.
	 *
	 * @return WP_REST_Response
	 */
	public static function purge_all() {
		Cache_Hive_Purge::purge_all();
		return new WP_REST_Response( array( 'message' => 'All caches purged.' ), 200 );
	}

	/**
	 * Handles the 'purge_disk_cache' action.
	 *
	 * @return WP_REST_Response
	 */
	public static function purge_disk_cache() {
		Cache_Hive_Purge::purge_disk_cache();
		return new WP_REST_Response( array( 'message' => 'Disk cache purged.' ), 200 );
	}

	/**
	 * Handles the 'purge_object_cache' action.
	 *
	 * @return WP_REST_Response
	 */
	public static function purge_object_cache() {
		Cache_Hive_Purge::purge_object_cache();
		return new WP_REST_Response( array( 'message' => 'Object cache purged.' ), 200 );
	}

	/**
	 * Handles the 'purge_cloudflare' action.
	 *
	 * @return WP_REST_Response
	 */
	public static function purge_cloudflare() {
		if ( class_exists( 'Cache_Hive\Includes\Cache_Hive_Cloudflare' ) ) {
			Cache_Hive_Cloudflare::purge_all();
		}
		return new WP_REST_Response( array( 'message' => 'Cloudflare cache purged.' ), 200 );
	}

	/**
	 * Handles the 'purge_this_page' action for an admin.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function purge_this_page( WP_REST_Request $request ) {
		$url = $request->get_param( 'url' );
		if ( empty( $url ) ) {
			// If URL is empty, it's likely an admin page. Purge it by its generic admin URL.
			$url = admin_url();
		}
		Cache_Hive_Purge::purge_url( $url );
		return new WP_REST_Response( array( 'message' => 'Cache for this page has been purged.' ), 200 );
	}

	/**
	 * Purges the entire private cache for the current logged-in user.
	 *
	 * @return WP_REST_Response
	 */
	public static function purge_my_private_cache() {
		Cache_Hive_Purge::purge_current_user_private_cache();
		return new WP_REST_Response( array( 'message' => 'Your private cache has been purged.' ), 200 );
	}
}
