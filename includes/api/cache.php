<?php
/**
 * Cache settings REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Hive_REST_Cache {
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
}
