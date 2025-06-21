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
            'objectCacheMethod' => isset( $settings['objectCacheMethod'] ) ? $settings['objectCacheMethod'] : 'redis',
            'objectCacheHost' => isset( $settings['objectCacheHost'] ) ? $settings['objectCacheHost'] : '127.0.0.1',
            'objectCachePort' => isset( $settings['objectCachePort'] ) ? $settings['objectCachePort'] : 6379,
            'objectCacheLifetime' => isset( $settings['objectCacheLifetime'] ) ? $settings['objectCacheLifetime'] : 3600,
            'objectCacheUsername' => isset( $settings['objectCacheUsername'] ) ? $settings['objectCacheUsername'] : '',
            'objectCachePassword' => isset( $settings['objectCachePassword'] ) ? $settings['objectCachePassword'] : '',
            'objectCacheGlobalGroups' => isset($settings['objectCacheGlobalGroups']) ? (is_array($settings['objectCacheGlobalGroups']) ? $settings['objectCacheGlobalGroups'] : preg_split('/[\s,]+/', trim($settings['objectCacheGlobalGroups']))) : [],
            'objectCacheNoCacheGroups' => isset($settings['objectCacheNoCacheGroups']) ? (is_array($settings['objectCacheNoCacheGroups']) ? $settings['objectCacheNoCacheGroups'] : preg_split('/[\s,]+/', trim($settings['objectCacheNoCacheGroups']))) : [],
            'objectCachePersistentConnection' => isset( $settings['objectCachePersistentConnection'] ) ? $settings['objectCachePersistentConnection'] : false,
        ];
        
        // Add live status if the drop-in is active
        if ( function_exists( 'wp_cache_get_info' ) ) {
             $object_cache_settings['liveStatus'] = wp_cache_get_info();
        } else {
             $object_cache_settings['liveStatus'] = ['status' => 'Disabled', 'client' => 'Drop-in not active.'];
        }

        return new WP_REST_Response( $object_cache_settings, 200 );
    }

    public static function update_object_cache_settings( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $current_settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $current_settings;

        // Use a whitelist of keys to update
        $allowed_keys = [
            'objectCacheEnabled', 'objectCachePersistentConnection', 'objectCacheMethod',
            'objectCacheHost', 'objectCacheUsername', 'objectCachePassword',
            'objectCachePort', 'objectCacheLifetime', 'objectCacheGlobalGroups', 'objectCacheNoCacheGroups'
        ];

        foreach ( $allowed_keys as $key ) {
            if ( ! isset( $params[ $key ] ) ) {
                continue;
            }
            $value = $params[$key];
            
            switch ( $key ) {
                case 'objectCacheEnabled':
                case 'objectCachePersistentConnection':
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
                    $updated_settings[$key] = intval( $value );
                    break;
                case 'objectCacheGlobalGroups':
                case 'objectCacheNoCacheGroups':
                    if (is_array($value)) {
                        $updated_settings[$key] = array_map('sanitize_text_field', $value);
                    } elseif (is_string($value)) {
                        $updated_settings[$key] = array_values( array_filter( array_map('sanitize_text_field', preg_split('/[\s,]+/', $value)) ) );
                    } else {
                        $updated_settings[$key] = [];
                    }
                    break;
            }
        }

        $new_settings = Cache_Hive_Settings::sanitize_settings( $updated_settings );

        // --- Object Cache Connection Test ---
        if ( ! empty( $new_settings['objectCacheEnabled'] ) ) {
            // Prepare config for backend factory
            $backend_config = [
                'method'     => $new_settings['objectCacheMethod'],
                'host'       => $new_settings['objectCacheHost'],
                'port'       => $new_settings['objectCachePort'],
                'timeout'    => 2,
                'database'   => 0,
                'user'       => $new_settings['objectCacheUsername'],
                'pass'       => $new_settings['objectCachePassword'],
                'persistent' => !empty($new_settings['objectCachePersistentConnection']),
                'lifetime'   => $new_settings['objectCacheLifetime'],
            ];
            require_once __DIR__ . '/../object-cache/factory.php';
            $backend = Cache_Hive_Object_Cache_Factory::create($backend_config);
            if ( ! $backend || ! $backend->is_connected() ) {
                return new WP_REST_Response([
                    'error' => 'Could not connect to the object cache backend with the provided settings.'
                ], 400);
            }
            // If persistent is off, ensure backend disables persistent connection if possible
            if ( empty($new_settings['objectCachePersistentConnection']) && method_exists($backend, 'close') ) {
                $backend->close();
            }
        }

        // Only update settings, write to config.php, and regenerate drop-in
        update_option( 'cache_hive_settings', $new_settings, 'yes' );
        Cache_Hive_Disk::create_config_file( $new_settings );
        Cache_Hive_Object_Cache::manage_dropin( $new_settings );

        // Return the latest settings and status
        $response_data = self::get_object_cache_settings()->get_data();
        return new WP_REST_Response( $response_data, 200 );
    }
}