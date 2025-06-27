<?php
/**
 * Cache Hive - Advanced Cache Drop-in
 *
 * This file is executed very early by WordPress if the WP_CACHE constant is enabled.
 * Its job is to check for a valid cached version of the requested page and serve
 * it directly, bypassing the full WordPress load for maximum performance.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// If WP_CACHE is not enabled, do nothing.
if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
	return;
}

/**
 * Main class for handling the advanced cache logic.
 */
class Cache_Hive_Advanced_Cache {

	private $settings;
	private $is_mobile = false;
	private $is_logged_in = false;
	private $is_commenter = false;

	public function __construct() {
		$this->settings = $this->get_settings();

		if ( empty( $this->settings ) || ! ( $this->settings['enableCache'] ?? false ) ) {
			return;
		}

		// Run pre-checks to determine user status FIRST.
		$this->pre_checks();

		// If this request type should never be cached, bail.
		if ( $this->should_bypass_request_type() ) {
			return;
		}
		
		// Determine mobile status.
		$this->is_mobile = $this->check_if_mobile();

		// Attempt to deliver cache. This now respects user/mobile status.
		$this->deliver_cache();
	}

	private function get_settings() {
		$config_file = WP_CONTENT_DIR . '/cache-hive-config/config.php';
		if ( @is_readable( $config_file ) ) {
			return include $config_file;
		}
		return array();
	}

	/**
	 * Checks user status based on cookies. This is crucial for path generation.
	 */
	private function pre_checks() {
		if ( ! empty( $_COOKIE ) ) {
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
					$this->is_logged_in = true;
					break; // Found it, no need to continue.
				}
			}
			if ( defined( 'COOKIEHASH' ) && ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
				$this->is_commenter = true;
			}
		}
	}

	/**
	 * Performs very early, essential checks for request types that should NEVER be cached.
	 *
	 * @return bool True to bypass, false to continue.
	 */
	private function should_bypass_request_type() {
		// Only cache GET requests.
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return true;
		}

		// Don't serve cache for logged-in users if the setting is disabled.
		if ( $this->is_logged_in && ! ( $this->settings['cacheLoggedUsers'] ?? false ) ) {
			return true;
		}

		// Don't serve cache for commenters if the setting is disabled.
		if ( $this->is_commenter && ! ( $this->settings['cacheCommenters'] ?? false ) ) {
			return true;
		}

		// Check for WordPress post password cookie.
		if ( defined( 'COOKIEHASH' ) && ! empty( $_COOKIE[ 'wp-postpass_' . COOKIEHASH ] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if the current visitor is a mobile device.
	 *
	 * @return bool
	 */
	private function check_if_mobile() {
		if ( ! ( $this->settings['cacheMobile'] ?? false ) || ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		
		// This now correctly reads the array from the settings.
		$user_agents = $this->settings['mobileUserAgents'] ?? array();

		if ( empty( $user_agents ) ) {
			return false;
		}

		// Escape each user agent string for regex safety.
		$escaped_agents = array_map(
			function ( $ua ) {
				return preg_quote( $ua, '/' );
			},
			$user_agents
		);
		$regex = '/' . implode( '|', $escaped_agents ) . '/i';
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
		return (bool) preg_match( $regex, $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * The core delivery logic. Finds the correct file and serves it.
	 */
	private function deliver_cache() {
		$cache_file = $this->get_cache_file_path();

		// Check validity.
		if ( ! $this->is_cache_valid( $cache_file ) ) {
			@unlink( $cache_file );
			@unlink( $cache_file . '.meta' );
			if ( ( $this->settings['serveStale'] ?? false ) && @is_readable( $cache_file ) ) {
				header( 'X-Cache-Hive: Stale (Advanced)' );
			} else {
				return; // No valid or stale cache to serve.
			}
		} else {
			header( 'X-Cache-Hive: Hit (Advanced)' );
		}
		
		// Add browser caching headers if enabled.
		if ( $this->settings['browserCacheEnabled'] ?? false ) {
			$ttl = absint( $this->settings['browserCacheTTL'] ?? 0 );
			if ( $ttl > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl ) . ' GMT' );
			}
		}

		if ( @is_readable( $cache_file ) ) {
			@readfile( $cache_file );
			exit;
		}
	}

	/**
	 * Constructs the full path to the potential cache file.
	 * This logic now correctly mirrors the logic in Cache_Hive_Disk.
	 *
	 * @return string The cache file path.
	 */
	private function get_cache_file_path() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
		$uri = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri = rtrim( $uri, '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotUnslashed
		$host     = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$dir_path = WP_CONTENT_DIR . '/cache/cache-hive/' . $host . $uri;

		// SOLID FIX: Correctly append user hash to the directory path for private cache.
		if ( $this->is_logged_in && ( $this->settings['cacheLoggedUsers'] ?? false ) ) {
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
					// We don't have user ID here, so we must rely on hashing the cookie value itself
					// which is less secure but the only option in the drop-in. Let's use the token.
					$parts = explode( '|', $value );
					$token = $parts[2] ?? ''; // The session token.
					if ( $token && defined('AUTH_KEY') ) {
						// Hashing the token provides a unique value per session.
						// This hash must match the one generated in Cache_Hive_Disk.
						$user_hash = 'user_' . md5( $token . AUTH_KEY );
						$dir_path .= '/' . $user_hash;
					}
					break;
				}
			}
		}

		$file_name = $this->is_mobile ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	private function is_cache_valid( $cache_file ) {
		$meta_file = $cache_file . '.meta';
		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return false;
		}
		$meta_data_json = @file_get_contents( $meta_file );
		if ( ! $meta_data_json ) {
			return false;
		}
		$meta_data = json_decode( $meta_data_json, true );
		if ( ! $meta_data || ! isset( $meta_data['created'], $meta_data['ttl'] ) ) {
			return false;
		}
		if ( 0 === (int) $meta_data['ttl'] ) {
			return true;
		}
		return ( $meta_data['created'] + $meta_data['ttl'] ) > time();
	}
}

new Cache_Hive_Advanced_Cache();