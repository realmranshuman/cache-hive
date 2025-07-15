<?php
/**
 * Handles browser cache settings and integration for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles browser cache settings and integration.
 */
final class Cache_Hive_Browser_Cache {

	/**
	 * Send browser cache headers if enabled in settings.
	 *
	 * @param array $settings The plugin settings array.
	 */
	public static function send_headers( $settings ) {
		if ( ! empty( $settings['browser_cache_enabled'] ) && is_singular() && ! is_user_logged_in() ) {
			$ttl_seconds = absint( $settings['browser_cache_ttl'] ?? 0 );
			if ( $ttl_seconds > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl_seconds );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl_seconds ) . ' GMT' );
			}
		}
	}

	/**
	 * Detect the server software (apache, nginx, litespeed, unknown).
	 *
	 * @return string
	 */
	public static function get_server_software() {
		$software = strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ?? '' ) ) );
		if ( str_contains( $software, 'apache' ) ) {
			return 'apache';
		}
		if ( str_contains( $software, 'litespeed' ) ) {
			return 'litespeed';
		}
		if ( str_contains( $software, 'nginx' ) ) {
			return 'nginx';
		}
		return 'unknown';
	}

	/**
	 * Generate .htaccess rules for browser cache.
	 *
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_htaccess_rules( $settings ) {
		$ttl = absint( $settings['browser_cache_ttl'] ?? 0 );
		if ( $ttl <= 0 ) {
			return '';
		}

		$types = array(
			'image/jpg',
			'image/jpeg',
			'image/gif',
			'image/png',
			'image/svg+xml',
			'text/css',
			'text/javascript',
			'application/javascript',
			'application/x-javascript',
			'application/font-woff2',
			'application/font-woff',
			'application/vnd.ms-fontobject',
			'font/ttf',
			'font/otf',
			'application/pdf',
		);

		$block  = "# BEGIN Cache Hive Browser Cache\n";
		$block .= "<IfModule mod_expires.c>\n";
		$block .= "    ExpiresActive On\n";
		foreach ( $types as $type ) {
			$block .= "    ExpiresByType {$type} \"access plus {$ttl} seconds\"\n";
		}
		$block .= "</IfModule>\n";
		$block .= "# END Cache Hive Browser Cache\n";

		return $block;
	}

	/**
	 * Generate nginx rules for browser cache.
	 *
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_nginx_rules( $settings ) {
		$ttl = absint( $settings['browser_cache_ttl'] ?? 0 );
		if ( $ttl <= 0 ) {
			return '';
		}

		if ( 0 === $ttl % 31536000 ) {
			$nginx_ttl = ( $ttl / 31536000 ) . 'y'; } elseif ( 0 === $ttl % 2592000 ) {
			$nginx_ttl = ( $ttl / 2592000 ) . 'M'; } elseif ( 0 === $ttl % 604800 ) {
				$nginx_ttl = ( $ttl / 604800 ) . 'w'; } elseif ( 0 === $ttl % 86400 ) {
				$nginx_ttl = ( $ttl / 86400 ) . 'd'; } elseif ( 0 === $ttl % 3600 ) {
					$nginx_ttl = ( $ttl / 3600 ) . 'h'; } elseif ( 0 === $ttl % 60 ) {
					$nginx_ttl = ( $ttl / 60 ) . 'm'; } else {
						$nginx_ttl = $ttl . 's'; }

					$block  = "# BEGIN Cache Hive Browser Cache\n";
					$block .= "location ~* \.(jpg|jpeg|gif|png|css|js|woff2?|ttf|otf|eot|svg|pdf)$ {\n";
					$block .= "    expires {$nginx_ttl};\n";
					$block .= "    add_header Cache-Control \"public\";\n";
					$block .= "}\n";
					$block .= "# END Cache Hive Browser Cache\n";
					return $block;
	}

	/**
	 * Update .htaccess with browser cache rules.
	 *
	 * @param array $settings The plugin settings array.
	 * @return true|WP_Error
	 */
	public static function update_htaccess( $settings ) {
		$server = self::get_server_software();
		if ( 'apache' !== $server && 'litespeed' !== $server ) {
			return true;
		}
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess_file = trailingslashit( get_home_path() ) . '.htaccess';
		if ( ! is_writable( $htaccess_file ) ) {
			return new WP_Error( 'htaccess_not_writable', __( 'The .htaccess file is not writable.', 'cache-hive' ) );
		}

		$rules    = self::generate_htaccess_rules( $settings );
		$contents = file_get_contents( $htaccess_file );
		if ( false === $contents ) {
			$contents = '';
		}

		$pattern  = "/# BEGIN Cache Hive Browser Cache.*?# END Cache Hive Browser Cache\n?/s";
		$contents = preg_replace( $pattern, '', $contents );

		if ( ! empty( $settings['browser_cache_enabled'] ) && ! empty( $rules ) ) {
			$contents .= "\n" . $rules;
		}

		if ( false === file_put_contents( $htaccess_file, $contents, LOCK_EX ) ) {
			return new WP_Error( 'htaccess_write_failed', __( 'Failed to write to .htaccess.', 'cache-hive' ) );
		}
		return true;
	}
}
