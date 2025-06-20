<?php
/**
 * Object Cache settings REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Hive_REST_ObjectCache {
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
}
