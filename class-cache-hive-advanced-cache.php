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

// NOTE: This file runs before plugins are loaded. Do not use plugin-specific functions here.

/**
 * Main class for handling the advanced cache logic.
 */
final class Cache_Hive_Advanced_Cache {

	/**
	 * The loaded settings array or false if not loaded.
	 *
	 * @since 1.0.0
	 * @var array|false
	 */
	private $settings;

	/**
	 * Whether the current request is from a mobile device.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $is_mobile = false;

	/**
	 * Whether the current user is logged in.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private $is_logged_in = false;

	/**
	 * The username of the logged-in user, if applicable.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $username = '';

	/**
	 * The current blog ID for multisite compatibility.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $blog_id = 1;

	/**
	 * Flag to indicate a critical failure, such as being unable to read the multisite map.
	 *
	 * @since 1.2.0
	 * @var bool
	 */
	private $critical_failure = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->determine_multisite_context_from_map();
		if ( $this->critical_failure ) {
			return;
		}

		$this->define_multisite_paths();

		$this->settings = $this->get_settings();
		if ( empty( $this->settings ) || ! ( $this->settings['enable_cache'] ?? false ) ) {
			return;
		}
		if ( $this->should_bypass_early() ) {
			return;
		}
		$this->is_mobile = $this->check_if_mobile();
		if ( $this->should_bypass_exclusions() ) {
			return;
		}
		$this->deliver_cache();
	}

	/**
	 * Determines the blog ID from a pre-compiled map in the config file.
	 * This avoids any database queries for maximum performance.
	 */
	private function determine_multisite_context_from_map() {
		$main_site_config_path = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__ ) ) . '/cache-hive-config/1/config.php';

		if ( ! @is_readable( $main_site_config_path ) ) {
			return;
		}

		$config_json = include $main_site_config_path;
		if ( ! is_string( $config_json ) ) {
			if ( defined( 'MULTISITE' ) && MULTISITE ) {
				$this->critical_failure = true;
			}
			return;
		}
		$config = json_decode( $config_json, true );

		$sitemap = $config['multisite_map'] ?? array();

		if ( empty( $sitemap ) || ! is_array( $sitemap ) ) {
			return;
		}

		$domain = rtrim( strtolower( $_SERVER['HTTP_HOST'] ?? '' ), '.' );
		if ( substr( $domain, 0, 4 ) === 'www.' ) {
			$domain = substr( $domain, 4 );
		}
		$request_path = '/';
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri  = stripslashes( (string) $_SERVER['REQUEST_URI'] );
			$request_path = strtok( $request_uri, '?' );
		}

		foreach ( $sitemap as $site ) {
			$site = (object) $site;
			if ( isset( $site->domain, $site->path ) && $site->domain === $domain && strpos( $request_path, $site->path ) === 0 ) {
				$this->blog_id = (int) $site->blog_id;
				return;
			}
		}
	}

	/**
	 * Defines site-specific constants for this file's scope based on the determined blog_id.
	 */
	private function define_multisite_paths() {
		$site_path_segment = ( defined( 'MULTISITE' ) && MULTISITE ) ? '/' . $this->blog_id : '';
		$base_cache_dir    = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__ ) ) . '/cache/cache-hive' . $site_path_segment;

		if ( ! defined( 'CACHE_HIVE_BASE_CACHE_DIR' ) ) {
			define( 'CACHE_HIVE_BASE_CACHE_DIR', $base_cache_dir );
		}
		if ( ! defined( 'CACHE_HIVE_PUBLIC_CACHE_DIR' ) ) {
			define( 'CACHE_HIVE_PUBLIC_CACHE_DIR', CACHE_HIVE_BASE_CACHE_DIR . '/public' );
		}
		if ( ! defined( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR' ) ) {
			define( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR', CACHE_HIVE_BASE_CACHE_DIR . '/private/user_cache' );
		}
	}

	/**
	 * Loads settings from the site-specific config file.
	 *
	 * @return array|false
	 */
	private function get_settings() {
		$site_path_segment = ( defined( 'MULTISITE' ) && MULTISITE ) ? '/' . $this->blog_id : '';
		$config_dir        = ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( __DIR__ ) ) . '/cache-hive-config' . $site_path_segment;
		$config_file       = $config_dir . '/config.php';

		if ( @is_readable( $config_file ) ) {
			$config_json = include $config_file;
			if ( is_string( $config_json ) ) {
				return json_decode( $config_json, true );
			}
		}
		return false;
	}

	/**
	 * Performs very early checks for request method and user status.
	 *
	 * @return bool True if caching should be bypassed.
	 */
	private function should_bypass_early() {
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return true;
		}
		if ( ! empty( $_COOKIE ) ) {
			$cookie_hash = defined( 'COOKIEHASH' ) ? COOKIEHASH : '';
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
					$this->is_logged_in = true;
					$parts              = explode( '|', $value );
					if ( isset( $parts[0] ) ) {
						$this->username = preg_replace( '/[^a-zA-Z0-9\._-]/', '', $parts[0] );
					}
					break;
				}
			}
			if ( ! empty( $_COOKIE[ 'wp-postpass_' . $cookie_hash ] ) ) {
				return true;
			}
			if ( ! ( $this->settings['cache_commenters'] ?? false ) && ! empty( $_COOKIE[ 'comment_author_' . $cookie_hash ] ) ) {
				return true;
			}
		}
		if ( $this->is_logged_in && ! ( $this->settings['cache_logged_users'] ?? false ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Performs exclusion checks based on settings.
	 *
	 * @return bool True if caching should be bypassed.
	 */
	private function should_bypass_exclusions() {
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( ! empty( $this->settings['exclude_uris'] ) && is_array( $this->settings['exclude_uris'] ) ) {
			foreach ( $this->settings['exclude_uris'] as $pattern ) {
				if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $request_uri ) ) {
					return true;
				}
			}
		}
		if ( ! empty( $_SERVER['QUERY_STRING'] ) && ! empty( $this->settings['exclude_query_strings'] ) && is_array( $this->settings['exclude_query_strings'] ) ) {
			parse_str( $_SERVER['QUERY_STRING'], $query_params );
			$query_keys = array_keys( $query_params );
			foreach ( $query_keys as $key ) {
				foreach ( $this->settings['exclude_query_strings'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $key ) ) {
							return true;
					}
				}
			}
		}
		if ( ! empty( $this->settings['exclude_cookies'] ) && is_array( $this->settings['exclude_cookies'] ) ) {
			foreach ( array_keys( $_COOKIE ) as $key ) {
				foreach ( $this->settings['exclude_cookies'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $key ) ) {
							return true;
					}
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
			return false;
		}
		$user_agents = $this->settings['mobile_user_agents'] ?? array();
		if ( empty( $user_agents ) || ! is_array( $user_agents ) ) {
			return false;
		}
		$regex = '/' . implode( '|', array_map( '\preg_quote', $user_agents, array_fill( 0, count( $user_agents ), '/' ) ) ) . '/i';
		return (bool) \preg_match( $regex, $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Delivers the cached file if it is valid.
	 *
	 * @return void
	 */
	private function deliver_cache() {
		$cache_file   = $this->get_cache_file_path();
		$cache_status = $this->is_cache_valid( $cache_file );

		switch ( $cache_status ) {
			case 'valid':
				header( 'X-Cache-Hive: Hit (Advanced)' );
				$this->serve_file( $cache_file );
				break;

			case 'expired':
				if ( ! empty( $this->settings['serve_stale'] ) ) {
					header( 'X-Cache-Hive: Stale' );
					register_shutdown_function( array( $this, 'trigger_background_regeneration' ) );
					$this->serve_file( $cache_file );
				}
				// If not serving stale, we fall through and treat it as a miss.
				break;

			case 'invalid':
			default:
				// Do nothing, let WordPress handle the request. This is a cache miss.
				break;
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
		$file_name = $filename_base . $file_suffix . '.cache';

		return $dir_path . '/' . $file_name;
	}

	/**
	 * Constructs the direct path for a private cache file from the primary `/user_cache/` storage.
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
		$user_dir_path   = CACHE_HIVE_PRIVATE_USER_CACHE_DIR . '/' . $user_level1_dir . '/' . $user_level2_dir . '/' . $user_dir_base;

		$host      = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = md5( $cache_key );

		$file_suffix = $this->is_mobile ? '-mobile' : '';
		$file_name   = $url_hash . $file_suffix . '.cache';

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

		if ( $this->is_logged_in && class_exists( '\\Cache_Hive\\Includes\\Cache_Hive_Logged_In_Cache' ) && false !== $html ) {
			$html = \Cache_Hive\Includes\Cache_Hive_Logged_In_Cache::inject_dynamic_elements_from_placeholders( $html );
		}

		header( 'Content-Type: text/html; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $html;

		while ( ob_get_level() > 0 ) {
			if ( false === ob_end_flush() ) {
				break;
			}
		}
		exit;
	}

	/**
	 * Checks if a cache file is valid, expired, or invalid.
	 *
	 * @param string $cache_file The full path to the cache file.
	 * @return string 'valid', 'expired', or 'invalid'.
	 */
	private function is_cache_valid( $cache_file ) {
		if ( empty( $cache_file ) ) {
			return 'invalid';
		}
		$meta_file = $cache_file . '.meta';
		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return 'invalid';
		}
		$meta_contents = @file_get_contents( $meta_file );
		if ( false === $meta_contents ) {
			return 'invalid';
		}
		$meta_data = json_decode( $meta_contents, true );
		if ( ! is_array( $meta_data ) || empty( $meta_data['created'] ) || ! isset( $meta_data['ttl'] ) ) {
			return 'invalid';
		}
		if ( 0 === (int) $meta_data['ttl'] ) {
			return 'valid';
		}

		if ( ( $meta_data['created'] + (int) $meta_data['ttl'] ) > time() ) {
			return 'valid';
		}

		return 'expired';
	}

	/**
	 * Triggers a non-blocking background request to regenerate the cache.
	 * This is fired as a shutdown function after serving stale content.
	 */
	public function trigger_background_regeneration() {
		$scheme  = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'ssl://' : '';
		$host    = $_SERVER['HTTP_HOST'] ?? '';
		$port    = ( 'ssl://' === $scheme ) ? 443 : 80;
		$path    = $_SERVER['REQUEST_URI'] ?? '/';
		$timeout = 1; // We just need to fire the request, not wait for a response.

		if ( empty( $host ) ) {
			return;
		}

		// Open a socket connection without waiting for the full response.
		$fp = fsockopen( $scheme . $host, $port, $errno, $errstr, $timeout );
		if ( ! is_resource( $fp ) ) {
			return; // Could not open socket.
		}

		// We use a POST request because it is already bypassed by the caching engine.
		$request  = "POST {$path} HTTP/1.1\r\n";
		$request .= "Host: {$host}\r\n";
		$request .= "User-Agent: Cache-Hive-Regenerator/1.0\r\n";
		$request .= "Connection: Close\r\n";
		$request .= "Content-Length: 0\r\n\r\n";

		fwrite( $fp, $request );
		fclose( $fp );
	}
}

new Cache_Hive_Advanced_Cache();
