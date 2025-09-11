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
if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
	return;
}

// WRAP: Check if constants are already defined to prevent warnings. This file loads before plugins.
if ( ! defined( 'CACHE_HIVE_BASE_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_BASE_CACHE_DIR', ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__ ) ) . '/cache/cache-hive' );
}
if ( ! defined( 'CACHE_HIVE_PUBLIC_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PUBLIC_CACHE_DIR', CACHE_HIVE_BASE_CACHE_DIR . '/public' );
}
if ( ! defined( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR', CACHE_HIVE_BASE_CACHE_DIR . '/private/user_cache' );
}
// NOTE: The `/private/url_index` directory is not needed for reading cache, only for purging, so it is not defined here.

/**
 * Main class for handling the advanced cache logic.
 */
final class Cache_Hive_Advanced_Cache {

	/**
	 * The loaded settings array or false if not loaded.
	 *
	 * @var array|false
	 */
	private $settings;

	/**
	 * Whether the current request is from a mobile device.
	 *
	 * @var bool
	 */
	private $is_mobile = false;

	/**
	 * Whether the current user is logged in.
	 *
	 * @var bool
	 */
	private $is_logged_in = false;

	/**
	 * The username of the logged-in user, if applicable.
	 *
	 * @var string
	 */
	private $username = '';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();
		if ( empty( $this->settings ) || ! ( $this->settings['enable_cache'] ?? false ) ) {
			return; }
		if ( $this->should_bypass_early() ) {
			return; }
		$this->is_mobile = $this->check_if_mobile();
		if ( $this->should_bypass_exclusions() ) {
			return; }
		$this->deliver_cache();
	}

	/**
	 * Loads settings from the config file.
	 *
	 * @return array|false
	 */
	private function get_settings() {
		// Use the correct config dir constant for consistency.
		$config_file = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__ ) ) . '/cache-hive-config/config.php';
		if ( @is_readable( $config_file ) ) {
			return include $config_file; }
		return false;
	}

	/**
	 * Performs very early checks for request method and user status.
	 *
	 * @return bool True if caching should be bypassed.
	 */
	private function should_bypass_early() {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return true; }
		if ( ! empty( $_COOKIE ) ) {
			$cookie_hash = defined( 'COOKIEHASH' ) ? COOKIEHASH : '';
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
					$this->is_logged_in = true;
					$parts              = explode( '|', $value );
					if ( isset( $parts[0] ) ) {
						$this->username = $parts[0];
					}
					break;
				}
			}
			if ( ! empty( $_COOKIE[ 'wp-postpass_' . $cookie_hash ] ) ) {
				return true; }
			if ( ! ( $this->settings['cache_commenters'] ?? false ) && ! empty( $_COOKIE[ 'comment_author_' . $cookie_hash ] ) ) {
				return true; }
		}
		if ( $this->is_logged_in && ! ( $this->settings['cache_logged_users'] ?? false ) ) {
			return true; }
		return false;
	}

	/**
	 * Performs exclusion checks based on settings.
	 *
	 * @return bool True if caching should be bypassed.
	 */
	private function should_bypass_exclusions() {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( ! empty( $this->settings['exclude_uris'] ) ) {
			foreach ( $this->settings['exclude_uris'] as $pattern ) {
				if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $request_uri ) ) {
					return true; }
			}
		}
		if ( ! empty( $_SERVER['QUERY_STRING'] ) && ! empty( $this->settings['exclude_query_strings'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $query_params );
			$query_keys = array_keys( $query_params );
			foreach ( $query_keys as $key ) {
				foreach ( $this->settings['exclude_query_strings'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $key ) ) {
							return true; }
				}
			}
		}
		if ( ! empty( $this->settings['exclude_cookies'] ) ) {
			foreach ( array_keys( $_COOKIE ) as $key ) {
				foreach ( $this->settings['exclude_cookies'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $key ) ) {
							return true; }
				}
			}
		}
		return false;
	}

	/**
	 * Checks if the current visitor is a mobile device.
	 *
	 * @return bool
	 */
	private function check_if_mobile() {
		if ( ! ( $this->settings['cache_mobile'] ?? false ) || empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false; }
		$user_agents = $this->settings['mobile_user_agents'] ?? array();
		if ( empty( $user_agents ) ) {
			return false; }
		$regex = '/' . implode( '|', array_map( '\preg_quote', $user_agents, array_fill( 0, count( $user_agents ), '/' ) ) ) . '/i';
		return (bool) \preg_match( $regex, $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Delivers the cached file if it is valid.
	 *
	 * @return void
	 */
	private function deliver_cache() {
		$cache_file = $this->get_cache_file_path();
		if ( $this->is_cache_valid( $cache_file ) ) {
			header( 'X-Cache-Hive: Hit (Advanced)' );
			$this->serve_file( $cache_file );
		}
	}

	/**
	 * Constructs the full path to the potential cache file by routing to the correct method.
	 *
	 * @return string The cache file path.
	 */
	private function get_cache_file_path() {
		$is_private_cache = $this->is_logged_in && ! empty( $this->username ) && ( $this->settings['cache_logged_users'] ?? false );
		if ( $is_private_cache ) {
			return $this->get_private_cache_path();
		} else {
			return $this->get_public_cache_path();
		}
	}

	/**
	 * Constructs the path for a public cache file.
	 *
	 * @return string
	 */
	private function get_public_cache_path() {
		$host      = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = md5( $cache_key );

		$level1_dir    = substr( $url_hash, 0, 2 );
		$level2_dir    = substr( $url_hash, 2, 2 );
		$filename_base = substr( $url_hash, 4 );
		$file_suffix   = $this->is_mobile ? '-mobile' : '';

		$dir_path  = CACHE_HIVE_PUBLIC_CACHE_DIR . '/' . $level1_dir . '/' . $level2_dir;
		$file_name = $filename_base . $file_suffix . '.html';

		return $dir_path . '/' . $file_name;
	}

	/**
	 * Constructs the path for a private cache file from the primary `/user_cache/` storage.
	 *
	 * @return string
	 */
	private function get_private_cache_path() {
		if ( empty( $this->username ) ) {
			return '';
		}

		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
		$user_hash = md5( $this->username . $auth_key );

		$user_level1_dir = substr( $user_hash, 0, 2 );
		$user_level2_dir = substr( $user_hash, 2, 2 );
		$user_dir_base   = substr( $user_hash, 4 );
		// Use the correct constant for the primary user cache storage path.
		$user_dir_path = CACHE_HIVE_PRIVATE_USER_CACHE_DIR . '/' . $user_level1_dir . '/' . $user_level2_dir . '/' . $user_dir_base;

		$host      = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = md5( $cache_key );

		$file_suffix = $this->is_mobile ? '-mobile' : '';
		$file_name   = $url_hash . $file_suffix . '.html';

		return $user_dir_path . '/' . $file_name;
	}


	/**
	 * Serves the file with appropriate headers.
	 *
	 * @param string $file_path The full path to the cache file.
	 */
	private function serve_file( $file_path ) {
		if ( ( $this->settings['browser_cache_enabled'] ?? false ) ) {
			$ttl = (int) ( $this->settings['browser_cache_ttl'] ?? 0 );
			if ( $ttl > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl ) . ' GMT' );
			}
		}
		$html = @file_get_contents( $file_path );

		// ------ Dynamic placeholder replacement for logged-in users -----
		if ( $this->is_logged_in && class_exists( '\\Cache_Hive\\Includes\\Cache_Hive_Logged_In_Cache' ) && false !== $html ) {
			$html = \Cache_Hive\Includes\Cache_Hive_Logged_In_Cache::inject_dynamic_elements_from_placeholders( $html );
		}
		// ---------------------------------------------------------------

		// Output as HTML with correct content type.
		header( 'Content-Type: text/html; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;

		// Properly flush and close all output buffers before exit to avoid zlib/ob_gzhandler warning.
		while ( ob_get_level() > 0 ) {
			// Only flush if ob_end_flush() returns true, avoid silencing errors.
			if ( ob_end_flush() === false ) {
				break;
			}
		}
		exit;
	}

	/**
	 * Checks if a cache file is valid (exists and is not expired).
	 *
	 * @param string $cache_file The full path to the cache file.
	 * @return bool
	 */
	private function is_cache_valid( $cache_file ) {
		if ( empty( $cache_file ) ) {
			return false;
		}
		$meta_file = $cache_file . '.meta';
		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return false; }
		$meta_data = json_decode( @file_get_contents( $meta_file ), true );
		if ( empty( $meta_data['created'] ) || ! isset( $meta_data['ttl'] ) ) {
			return false; }
		if ( 0 === (int) $meta_data['ttl'] ) {
			return true; }
		return ( $meta_data['created'] + (int) $meta_data['ttl'] ) > time();
	}
}

new Cache_Hive_Advanced_Cache();
