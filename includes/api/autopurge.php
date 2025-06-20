<?php
/**
 * Auto Purge settings REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cache_Hive_REST_AutoPurge {
    public static function get_autopurge_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $autopurge_settings = [
            'autoPurgeEntireSite' => isset( $settings['autoPurgeEntireSite'] ) ? $settings['autoPurgeEntireSite'] : '',
            'autoPurgeFrontPage' => isset( $settings['autoPurgeFrontPage'] ) ? $settings['autoPurgeFrontPage'] : '',
            'autoPurgeHomePage' => isset( $settings['autoPurgeHomePage'] ) ? $settings['autoPurgeHomePage'] : '',
            'autoPurgePages' => isset( $settings['autoPurgePages'] ) ? $settings['autoPurgePages'] : '',
            'autoPurgeAuthorArchive' => isset( $settings['autoPurgeAuthorArchive'] ) ? $settings['autoPurgeAuthorArchive'] : '',
            'autoPurgePostTypeArchive' => isset( $settings['autoPurgePostTypeArchive'] ) ? $settings['autoPurgePostTypeArchive'] : '',
            'autoPurgeYearlyArchive' => isset( $settings['autoPurgeYearlyArchive'] ) ? $settings['autoPurgeYearlyArchive'] : '',
            'autoPurgeMonthlyArchive' => isset( $settings['autoPurgeMonthlyArchive'] ) ? $settings['autoPurgeMonthlyArchive'] : '',
            'autoPurgeDailyArchive' => isset( $settings['autoPurgeDailyArchive'] ) ? $settings['autoPurgeDailyArchive'] : '',
            'autoPurgeTermArchive' => isset( $settings['autoPurgeTermArchive'] ) ? $settings['autoPurgeTermArchive'] : '',
            'purgeOnUpgrade' => isset( $settings['purgeOnUpgrade'] ) ? $settings['purgeOnUpgrade'] : '',
            'serveStale' => isset( $settings['serveStale'] ) ? $settings['serveStale'] : '',
            'customPurgeHooks' => isset( $settings['customPurgeHooks'] ) ? $settings['customPurgeHooks'] : '',
        ];
        return new WP_REST_Response( $autopurge_settings, 200 );
    }

    public static function update_autopurge_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;

        foreach ( $params as $key => $value ) {
            switch ( $key ) {
                case 'autoPurgeEntireSite':
                case 'autoPurgeFrontPage':
                case 'autoPurgeHomePage':
                case 'autoPurgePages':
                case 'autoPurgeAuthorArchive':
                case 'autoPurgePostTypeArchive':
                case 'autoPurgeYearlyArchive':
                case 'autoPurgeMonthlyArchive':
                case 'autoPurgeDailyArchive':
                case 'autoPurgeTermArchive':
                case 'purgeOnUpgrade':
                case 'serveStale':
                    $updated_settings[$key] = filter_var( $value, FILTER_VALIDATE_BOOLEAN );
                    break;
                case 'customPurgeHooks':
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
