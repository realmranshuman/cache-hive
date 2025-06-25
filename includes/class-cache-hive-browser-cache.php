<?php
/**
 * Handles browser cache settings and integration for Cache Hive.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final class for handling browser cache settings and integration.
 *
 * This class generates and applies server rules (.htaccess or nginx.conf)
 * to instruct browsers to cache static assets for a specified duration.
 *
 * @since 1.0.0
 */
final class Cache_Hive_Browser_Cache {
	// Add methods for browser cache settings and integration here.

	/**
	 * Send browser cache headers if enabled in settings.
	 *
	 * @since 1.0.0
	 * @param array $settings The plugin settings array.
	 */
	public static function send_headers( $settings ) {
		if (
			isset( $settings['browserCacheEnabled'] ) && $settings['browserCacheEnabled'] &&
			is_singular() && ! is_user_logged_in()
		) {
			$ttl_seconds = isset( $settings['browserCacheTTL'] ) ? absint( $settings['browserCacheTTL'] ) : 0;
			if ( $ttl_seconds > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl_seconds );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl_seconds ) . ' GMT' );
			}
		}
	}

	/**
	 * Detect the server software (apache, nginx, litespeed, unknown).
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public static function get_server_software() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
		if ( false !== strpos( $software, 'apache' ) ) {
			return 'apache';
		}
		if ( false !== strpos( $software, 'litespeed' ) ) {
			return 'litespeed';
		}
		if ( false !== strpos( $software, 'nginx' ) ) {
			return 'nginx';
		}
		return 'unknown';
	}

	/**
	 * Generate .htaccess rules for browser cache.
	 *
	 * @since 1.0.0
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_htaccess_rules( $settings ) {
		$ttl = isset( $settings['browserCacheTTL'] ) ? absint( $settings['browserCacheTTL'] ) : 0;
		if ( $ttl <= 0 ) {
			return '';
		}
		$block  = "# BEGIN Cache Hive Browser Cache\n";
		$block .= "<IfModule mod_expires.c>\n";
		$block .= "ExpiresActive On\n";
		$block .= "ExpiresDefault \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType image/jpg \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType image/jpeg \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType image/gif \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType image/png \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType text/css \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType text/javascript \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType application/javascript \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType application/x-javascript \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType application/font-woff2 \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType application/font-woff \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType application/vnd.ms-fontobject \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType font/ttf \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType font/otf \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType font/woff \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType font/woff2 \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType image/svg+xml \"access plus {$ttl} seconds\"\n";
		$block .= "ExpiresByType application/pdf \"access plus {$ttl} seconds\"\n";
		$block .= "</IfModule>\n";
		$block .= "# END Cache Hive Browser Cache\n";
		return $block;
	}

	/**
	 * Generate nginx rules for browser cache.
	 *
	 * @since 1.0.0
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_nginx_rules( $settings ) {
		$ttl = isset( $settings['browserCacheTTL'] ) ? absint( $settings['browserCacheTTL'] ) : 0;
		if ( $ttl <= 0 ) {
			return '';
		}
		// Convert seconds to nginx time format (e.g., 604800 => 7d).
		$nginx_ttl = $ttl;
		if ( 0 === $ttl % 31536000 ) {
			$nginx_ttl = ( $ttl / 31536000 ) . 'y';
		} elseif ( 0 === $ttl % 2592000 ) {
			$nginx_ttl = ( $ttl / 2592000 ) . 'M';
		} elseif ( 0 === $ttl % 604800 ) {
			$nginx_ttl = ( $ttl / 604800 ) . 'w';
		} elseif ( 0 === $ttl % 86400 ) {
			$nginx_ttl = ( $ttl / 86400 ) . 'd';
		} elseif ( 0 === $ttl % 3600 ) {
			$nginx_ttl = ( $ttl / 3600 ) . 'h';
		} elseif ( 0 === $ttl % 60 ) {
			$nginx_ttl = ( $ttl / 60 ) . 'm';
		} else {
			$nginx_ttl = $ttl . 's';
		}
		$block  = "# BEGIN Cache Hive Browser Cache\n";
		$block .= "location ~* \\.(jpg|jpeg|gif|png|css|js|woff2?|ttf|otf|eot|svg|pdf)$ {\n";
		$block .= "  expires {$nginx_ttl};\n";
		$block .= "  add_header Cache-Control 'public';\n";
		$block .= "}\n";
		$block .= "# END Cache Hive Browser Cache\n";
		return $block;
	}

	/**
	 * Update .htaccess with browser cache rules.
	 *
	 * @since 1.0.0
	 * @param array $settings The plugin settings array.
	 * @return true|WP_Error
	 */
	public static function update_htaccess( $settings ) {
		$server = self::get_server_software();
		if ( 'apache' !== $server && 'litespeed' !== $server ) {
			return true; // Not applicable.
		}
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$htaccess_file = trailingslashit( get_home_path() ) . '.htaccess';
		if ( ! is_writable( $htaccess_file ) ) {
			return new WP_Error( 'htaccess_not_writable', 'The .htaccess file is not writable.' );
		}
		$rules    = self::generate_htaccess_rules( $settings );
		$contents = file_get_contents( $htaccess_file );
		if ( false === $contents ) {
			$contents = '';
		}
		$begin   = '# BEGIN Cache Hive Browser Cache';
		$end     = '# END Cache Hive Browser Cache';
		$pattern = "/$begin.*?$end\n?/s";
		if ( $settings['browserCacheEnabled'] && $rules ) {
			// Add or replace block.
			if ( preg_match( $pattern, $contents ) ) {
				$contents = preg_replace( $pattern, $rules, $contents );
			} else {
				$contents .= ( "\n" === substr( $contents, -1 ) ? '' : "\n" ) . $rules;
			}
		} else {
			// Remove block.
			$contents = preg_replace( $pattern, '', $contents );
		}
		if ( false === file_put_contents( $htaccess_file, $contents ) ) {
			return new WP_Error( 'htaccess_write_failed', 'Failed to write to .htaccess.' );
		}
		return true;
	}

	/**
	 * Parse TTL from .htaccess rules (if present).
	 *
	 * @since 1.0.0
	 * @param string $htaccess_contents The contents of the .htaccess file.
	 * @return int|null TTL in seconds, or null if not found.
	 */
	public static function parse_htaccess_ttl( $htaccess_contents ) {
		if ( preg_match( '/ExpiresDefault\s+\"access plus (\d+) seconds\"/', $htaccess_contents, $m ) ) {
			return (int) $m[1];
		}
		// Try to match any ExpiresByType as fallback.
		if ( preg_match( '/ExpiresByType [^\s]+ \"access plus (\d+) seconds\"/', $htaccess_contents, $m ) ) {
			return (int) $m[1];
		}
		return null;
	}
}
