<?php
/**
 * Exclusions settings REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Hive_REST_Exclusions {
    public static function get_exclusions_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $exclusions_settings = [
            'excludeUris' => isset( $settings['excludeUris'] ) ? $settings['excludeUris'] : '',
            'excludeQueryStrings' => isset( $settings['excludeQueryStrings'] ) ? $settings['excludeQueryStrings'] : '',
            'excludeCookies' => isset( $settings['excludeCookies'] ) ? $settings['excludeCookies'] : '',
            'excludeRoles' => isset( $settings['excludeRoles'] ) && is_array($settings['excludeRoles']) ? array_values($settings['excludeRoles']) : [],
        ];
        return new WP_REST_Response( $exclusions_settings, 200 );
    }

    public static function update_exclusions_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'excludeUris':
                case 'excludeQueryStrings':
                case 'excludeCookies':
                    $updated_settings[$key] = sanitize_textarea_field( $value );
                    break;
                case 'excludeRoles':
                    if (is_array($value)) {
                        $updated_settings[$key] = array_map('sanitize_text_field', $value);
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
