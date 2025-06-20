<?php
/**
 * Browser Cache settings REST API logic for Cache Hive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Cache_Hive_Browser_Cache' ) ) {
    require_once dirname(__DIR__) . '/class-cache-hive-browser-cache.php';
}
if ( ! class_exists( 'Cache_Hive_Settings' ) ) {
    require_once dirname(__DIR__) . '/class-cache-hive-settings.php';
}
if ( ! class_exists( 'Cache_Hive_Disk' ) ) {
    require_once dirname(__DIR__) . '/class-cache-hive-disk.php';
}

class Cache_Hive_REST_BrowserCache {
    public static function get_browser_cache_settings() {
        $settings = Cache_Hive_Settings::get_settings();
        $server = Cache_Hive_Browser_Cache::get_server_software();
        $status = [
            'settings' => [
                'browserCacheEnabled' => isset($settings['browserCacheEnabled']) ? (bool)$settings['browserCacheEnabled'] : false,
                'browserCacheTTL' => isset($settings['browserCacheTTL']) ? (int)$settings['browserCacheTTL'] : 0,
            ],
            'server' => $server,
        ];
        if ($server === 'apache' || $server === 'litespeed') {
            if (!function_exists('get_home_path')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $htaccess_file = trailingslashit(get_home_path()) . '.htaccess';
            $status['htaccessWritable'] = is_writable($htaccess_file);
            $rules = Cache_Hive_Browser_Cache::generate_htaccess_rules($settings);
            $status['rules'] = $rules;
            $status['rulesPresent'] = false;
            $status['rulesMatchSettings'] = false;
            if (file_exists($htaccess_file)) {
                $contents = @file_get_contents($htaccess_file);
                $hasBlock = $contents && strpos($contents, '# BEGIN Cache Hive Browser Cache') !== false && strpos($contents, '# END Cache Hive Browser Cache') !== false;
                if ($hasBlock) {
                    $status['rulesPresent'] = true;
                    $ttl = Cache_Hive_Browser_Cache::parse_htaccess_ttl($contents);
                    $rulesMatch = $ttl && $settings['browserCacheEnabled'] && $settings['browserCacheTTL'] == $ttl;
                    $status['rulesMatchSettings'] = $rulesMatch;
                    if ($status['htaccessWritable'] && $ttl && (!$settings['browserCacheEnabled'] || $settings['browserCacheTTL'] != $ttl)) {
                        $settings['browserCacheEnabled'] = true;
                        $settings['browserCacheTTL'] = $ttl;
                        update_option('cache_hive_settings', $settings);
                        Cache_Hive_Disk::create_config_file($settings);
                        $status['settings']['browserCacheEnabled'] = true;
                        $status['settings']['browserCacheTTL'] = $ttl;
                    }
                    if (!$status['htaccessWritable'] && $ttl) {
                        $status['settings']['browserCacheEnabled'] = true;
                        $status['settings']['browserCacheTTL'] = $ttl;
                    }
                }
            }
            if (!$status['rulesPresent'] && !$status['htaccessWritable']) {
                $default_settings = $settings;
                $default_settings['browserCacheTTL'] = 31536000;
                $status['rules'] = Cache_Hive_Browser_Cache::generate_htaccess_rules($default_settings);
            }
        } elseif ($server === 'nginx') {
            $test_url = site_url('/wp-includes/css/dashicons.min.css');
            $response = wp_remote_head($test_url, ['timeout' => 5]);
            $ttl = 0;
            $verified = false;
            if (!is_wp_error($response) && isset($response['headers']['cache-control'])) {
                $cc = $response['headers']['cache-control'];
                if (is_array($cc)) {
                    $cc = implode(',', $cc);
                }
                if (preg_match('/max-age=(\d+)/', $cc, $m)) {
                    $ttl = (int)$m[1];
                    $verified = true;
                }
            }
            if (!is_wp_error($response) && isset($response['headers']['expires']) && !$verified) {
                $expires = strtotime($response['headers']['expires']);
                $now = time();
                if ($expires > $now) {
                    $ttl = $expires - $now;
                    $verified = true;
                }
            }
            $status['nginxVerified'] = $verified;
            if ($verified) {
                $status['settings']['browserCacheEnabled'] = true;
                $status['settings']['browserCacheTTL'] = $ttl;
                $status['rulesPresent'] = true;
                $status['rules'] = '';
            } else {
                $status['settings']['browserCacheEnabled'] = false;
                $status['settings']['browserCacheTTL'] = 31536000;
                $status['rulesPresent'] = false;
                $status['rules'] = Cache_Hive_Browser_Cache::generate_nginx_rules(['browserCacheTTL' => 31536000]);
            }
        }
        return new WP_REST_Response($status, 200);
    }

    public static function update_browser_cache_settings(WP_REST_Request $request) {
        $params = $request->get_json_params();
        $settings = Cache_Hive_Settings::get_settings();
        $updated_settings = $settings;
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'browserCacheEnabled':
                    $updated_settings[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'browserCacheTTL':
                    $updated_settings[$key] = intval($value);
                    break;
                default:
                    continue 2;
            }
        }
        $new_settings = Cache_Hive_Settings::sanitize_settings($updated_settings);
        update_option('cache_hive_settings', $new_settings);
        Cache_Hive_Disk::create_config_file($new_settings);
        $server = Cache_Hive_Browser_Cache::get_server_software();
        if ($server === 'apache' || $server === 'litespeed') {
            if (!function_exists('get_home_path')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            $htaccess_file = trailingslashit(get_home_path()) . '.htaccess';
            $rules = Cache_Hive_Browser_Cache::generate_htaccess_rules($new_settings);
            $result = Cache_Hive_Browser_Cache::update_htaccess($new_settings);
            if (is_wp_error($result)) {
                $currentStatus = self::get_browser_cache_settings()->get_data();
                return new WP_REST_Response([
                    'error' => $result->get_error_message(),
                    'code' => $result->get_error_code(),
                    'rules' => $rules,
                    'currentStatus' => $currentStatus,
                ], 500);
            }
        }
        $status = self::get_browser_cache_settings()->get_data();
        return new WP_REST_Response($status, 200);
    }

    public static function verify_nginx_browser_cache(WP_REST_Request $request) {
        $test_url = site_url('/wp-includes/css/dashicons.min.css');
        $response = wp_remote_head($test_url, ['timeout' => 5]);
        $ttl = 0;
        $verified = false;
        $message = '';
        if (!is_wp_error($response) && isset($response['headers']['cache-control'])) {
            $cc = $response['headers']['cache-control'];
            if (preg_match('/max-age=(\d+)/', $cc, $m)) {
                $ttl = (int)$m[1];
                $verified = true;
                $message = 'Browser cache detected (max-age=' . $ttl . ' seconds).';
            }
        }
        if (!is_wp_error($response) && isset($response['headers']['expires']) && !$verified) {
            $expires = strtotime($response['headers']['expires']);
            $now = time();
            if ($expires > $now) {
                $ttl = $expires - $now;
                $verified = true;
                $message = 'Browser cache detected (Expires header, TTL=' . $ttl . ' seconds).';
            }
        }
        if (!$verified) {
            $message = 'Browser cache headers not detected. Please add the recommended Nginx config.';
        }
        return new WP_REST_Response([
            'verified' => $verified,
            'ttl' => $ttl,
            'message' => $message,
        ], 200);
    }
}
