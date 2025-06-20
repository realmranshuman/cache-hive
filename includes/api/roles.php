<?php
/**
 * Roles REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Hive_REST_Roles {
    public static function get_roles() {
        if ( ! function_exists( 'get_editable_roles' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }
        $roles = [];
        foreach ( get_editable_roles() as $role_key => $role ) {
            $roles[] = [
                'id'   => $role_key,
                'name' => $role['name'],
            ];
        }
        return new WP_REST_Response( $roles, 200 );
    }
}
