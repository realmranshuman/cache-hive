<?php
/**
 * TTL settings REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Hive_REST_TTL {
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
                    $updated_settings[$key] = $value;
                    break;
                default:
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );
        update_option( 'cache_hive_settings', $new_settings );
        Cache_Hive_Disk::create_config_file( $new_settings );

        return new WP_REST_Response( $new_settings, 200 );
    }
}
