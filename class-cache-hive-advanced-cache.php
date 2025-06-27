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

/**
 * Main class for handling the advanced cache logic.
 */
class Cache_Hive_Advanced_Cache {

	/**
	 * @var array|false The loaded settings.
	 */
	private $settings;

	/**
	 * @var bool Whether the request is from a mobile device.
	 */
	private $is_mobile = false;

	/**
	 * @var bool Whether a 'wordpress_logged_in' cookie is present.
	 */
	private $is_logged_in = false;

	/**
	 * @var int The User ID extracted from the login cookie.
	 */
	private $user_id = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();

		if ( empty( $this->settings ) || ! ( $this->settings['enableCache'] ?? false ) ) {
			return;
		}

		// Perform initial checks for request type, user status, and basic exclusions.
		if ( $this->should_bypass_early() ) {
			return;
		}

		$this->is_mobile = $this->check_if_mobile();

		// Perform more specific exclusion checks now that we have user/mobile context.
		if ( $this->should_bypass_exclusions() ) {
			return;
		}

		$this->deliver_cache();
	}

	/**
	 * Loads settings from the config file.
	 *
	 * @return array|false
	 */
	private function get_settings() {
		$config_file = WP_CONTENT_DIR . '/cache-hive-config/config.php';
		if ( @is_readable( $config_file ) ) {
			return include $config_file;
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
					// SOLID FIX: Reliably extract User ID from the cookie.
					$parts         = explode( '|', $value );
					$this->user_id = (int) end( $parts ); // The last part is the user_id.
					break;
				}
			}
			if ( ! empty( $_COOKIE[ 'wp-postpass_' . $cookie_hash ] ) ) {
				return true;
			}
			if ( ! ( $this->settings['cacheCommenters'] ?? false ) && ! empty( $_COOKIE[ 'comment_author_' . $cookie_hash ] ) ) {
				return true;
			}
		}

		if ( $this->is_logged_in && ! ( $this->settings['cacheLoggedUsers'] ?? false ) ) {
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

		// Exclude URIs.
		$exclude_uris = $this->settings['excludeUris'] ?? array();
		if ( ! empty( $exclude_uris ) ) {
			foreach ( $exclude_uris as $pattern ) {
				if ( ! empty( $pattern ) && @preg_match( '#' . $pattern . '#i', $request_uri ) ) {
					return true;
				}
			}
		}

		// Exclude Query Strings.
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$exclude_qs = $this->settings['excludeQueryStrings'] ?? array();
			if ( ! empty( $exclude_qs ) ) {
				parse_str( $_SERVER['QUERY_STRING'], $query_params );
				$query_keys = array_keys( $query_params );
				foreach ( $query_keys as $key ) {
					foreach ( $exclude_qs as $pattern ) {
						if ( ! empty( $pattern ) && @preg_match( '#' . $pattern . '#i', $key ) ) {
							return true;
						}
					}
				}
			}
		}

		// Exclude Cookies.
		$exclude_cookies = $this->settings['excludeCookies'] ?? array();
		if ( ! empty( $exclude_cookies ) ) {
			$cookie_keys = array_keys( $_COOKIE );
			foreach ( $cookie_keys as $key ) {
				foreach ( $exclude_cookies as $pattern ) {
					if ( ! empty( $pattern ) && @preg_match( '#' . $pattern . '#i', $key ) ) {
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
		if ( ! ( $this->settings['cacheMobile'] ?? false ) || empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		$user_agents = $this->settings['mobileUserAgents'] ?? array();
		if ( empty( $user_agents ) ) {
			return false;
		}
		// Use preg_quote to safely handle special characters in user agent strings.
		$regex = '/' . implode( '|', array_map( 'preg_quote', $user_agents, array_fill( 0, count( $user_agents ), '/' ) ) ) . '/i';
		return (bool) @preg_match( $regex, $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * The core delivery logic. Finds the correct file and serves it.
	 */
	private function deliver_cache() {
		$cache_file = $this->get_cache_file_path();
		if ( $this->is_cache_valid( $cache_file ) ) {
			header( 'X-Cache-Hive: Hit (Advanced)' );
			$this->serve_file( $cache_file );
		}
	}

	/**
	 * Constructs the full path to the potential cache file.
	 *
	 * @return string The cache file path.
	 */
	private function get_cache_file_path() {
		$uri = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri = rtrim( $uri, '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__';
		}

		$host     = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$dir_path = WP_CONTENT_DIR . '/cache/cache-hive/' . $host . $uri;

		// Append user-specific hash for private cache.
		if ( $this->is_logged_in && $this->user_id > 0 && ( $this->settings['cacheLoggedUsers'] ?? false ) ) {
			$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive';
			$user_hash = 'user_' . md5( $this->user_id . $auth_key );
			$dir_path .= '/' . $user_hash;
		}

		// SOLID FIX: Determine filename based on mobile status.
		$file_name = $this->is_mobile ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	/**
	 * Serves the file with appropriate headers.
	 *
	 * @param string $file_path The full path to the cache file.
	 */
	private function serve_file( $file_path ) {
		if ( $this->settings['browserCacheEnabled'] ?? false ) {
			$ttl = absint( $this->settings['browserCacheTTL'] ?? 0 );
			if ( $ttl > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl ) . ' GMT' );
			}
		}
		@readfile( $file_path );
		exit;
	}

	/**
	 * Checks if a cache file is valid (exists and is not expired).
	 *
	 * @param string $cache_file The full path to the cache file.
	 * @return bool
	 */
	private function is_cache_valid( $cache_file ) {
		$meta_file = $cache_file . '.meta';
		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return false;
		}
		$meta_data = json_decode( @file_get_contents( $meta_file ), true );
		if ( empty( $meta_data['created'] ) || ! isset( $meta_data['ttl'] ) ) {
			return false;
		}
		if ( 0 === (int) $meta_data['ttl'] ) {
			return true;
		}
		return ( $meta_data['created'] + (int) $meta_data['ttl'] ) > time();
	}
}

new Cache_Hive_Advanced_Cache();