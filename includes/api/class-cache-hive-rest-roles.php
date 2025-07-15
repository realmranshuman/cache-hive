<?php
/**
 * Roles REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for fetching user roles.
 */
class Cache_Hive_REST_Roles {
	/**
	 * Retrieves all editable WordPress user roles.
	 *
	 * This method is named get_roles to be consistent, but it doesn't have a corresponding
	 * update_roles method, so it's treated specially in the main REST API class.
	 *
	 * @return WP_REST_Response The response object containing the roles.
	 */
	public static function get_roles() {
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}
		$roles = array();
		foreach ( get_editable_roles() as $role_key => $role ) {
			$roles[] = array(
				'id'   => $role_key,
				'name' => $role['name'],
			);
		}
		return new WP_REST_Response( $roles, 200 );
	}
}
