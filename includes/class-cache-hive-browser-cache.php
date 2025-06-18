<?php
/**
 * Handles browser cache settings and integration for Cache Hive.
 *
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Cache_Hive_Browser_Cache {
    // Add methods for browser cache settings and integration here

    /**
     * Send browser cache headers if enabled in settings.
     * @param array $settings The plugin settings array.
     */
    public static function send_headers($settings) {
        if ( isset($settings['browserCacheEnabled']) && $settings['browserCacheEnabled'] ) {
            $ttl_days = isset($settings['browserCacheTTL']) ? absint($settings['browserCacheTTL']) : 0;
            if ( $ttl_days > 0 ) {
                $ttl_seconds = $ttl_days * DAY_IN_SECONDS;
                header( 'Cache-Control: public, max-age=' . $ttl_seconds );
                header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl_seconds ) . ' GMT' );
            }
        }
    }
}
