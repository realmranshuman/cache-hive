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
final class Cache_Hive_Advanced_Cache {

	/**
	 * The loaded settings array or false if not loaded.
	 *
	 * @var array|false
	 */
	private $settings;

	/**
	 * Whether the request is from a mobile device.
	 *
	 * @var bool
	 */
	private $is_mobile = false;

	/**
	 * Whether a 'wordpress_logged_in' cookie is present.
	 *
	 * @var bool
	 */
	private $is_logged_in = false;

	/**
	 * The User ID extracted from the login cookie.
	 *
	 * @var int
	 */
	private $user_id = 0;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = $this->get_settings();

		if ( empty( $this->settings ) || ! ( $this->settings['enable_cache'] ?? false ) ) {
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
			// The config file returns a PHP array.
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_COOKIE ) ) {
			$cookie_hash = defined( 'COOKIEHASH' ) ? COOKIEHASH : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			foreach ( $_COOKIE as $name => $value ) {
				if ( strpos( $name, 'wordpress_logged_in' ) === 0 ) {
					$this->is_logged_in = true;
					// Reliably extract User ID from the cookie. The last part is the user_id.
					$parts         = explode( '|', $value );
					$this->user_id = isset( $parts[2] ) ? (int) $parts[2] : 0;
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		// Exclude URIs.
		$exclude_uris = $this->settings['exclude_uris'] ?? array();
		if ( ! empty( $exclude_uris ) ) {
			foreach ( $exclude_uris as $pattern ) {
				if ( ! empty( $pattern ) && @preg_match( '#' . str_replace( '#', '\#', $pattern ) . '#i', $request_uri ) ) {
					return true;
				}
			}
		}

		// Exclude Query Strings.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! empty( $_SERVER['QUERY_STRING'] ) ) {
			$exclude_qs = $this->settings['exclude_query_strings'] ?? array();
			if ( ! empty( $exclude_qs ) ) {
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				parse_str( $_SERVER['QUERY_STRING'], $query_params );
				$query_keys = array_keys( $query_params );
				foreach ( $query_keys as $key ) {
					foreach ( $exclude_qs as $pattern ) {
						if ( ! empty( $pattern ) && @preg_match( '#' . str_replace( '#', '\#', $pattern ) . '#i', $key ) ) {
							return true;
						}
					}
				}
			}
		}

		// Exclude Cookies.
		$exclude_cookies = $this->settings['exclude_cookies'] ?? array();
		if ( ! empty( $exclude_cookies ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$cookie_keys = array_keys( $_COOKIE );
			foreach ( $cookie_keys as $key ) {
				foreach ( $exclude_cookies as $pattern ) {
					if ( ! empty( $pattern ) && @preg_match( '#' . str_replace( '#', '\#', $pattern ) . '#i', $key ) ) {
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if ( ! ( $this->settings['cache_mobile'] ?? false ) || empty( $user_agent ) ) {
			return false;
		}
		$mobile_user_agents = $this->settings['mobile_user_agents'] ?? array();
		if ( empty( $mobile_user_agents ) ) {
			return false;
		}

		$regex = '/' . implode( '|', array_map( 'preg_quote', $mobile_user_agents, array( '/' ) ) ) . '/i';
		return (bool) @preg_match( $regex, $user_agent );
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
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$uri = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri = rtrim( $uri, '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__';
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$host     = strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$dir_path = WP_CONTENT_DIR . '/cache/cache-hive/' . $host . $uri;

		// Append user-specific hash for private cache.
		if ( $this->is_logged_in && $this->user_id > 0 && ( $this->settings['cache_logged_users'] ?? false ) ) {
			$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cache_hive_default_salt';
			$user_hash = 'user_' . md5( $this->user_id . $auth_key );
			$dir_path .= '/' . $user_hash;
		}

		$file_name = $this->is_mobile ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	/**
	 * Serves the file with appropriate headers.
	 *
	 * @param string $file_path The full path to the cache file.
	 */
	private function serve_file( $file_path ) {
		if ( $this->settings['browser_cache_enabled'] ?? false ) {
			$ttl = absint( $this->settings['browser_cache_ttl'] ?? 0 );
			if ( $ttl > 0 ) {
				header( 'Cache-Control: public, max-age=' . $ttl );
				header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + $ttl ) . ' GMT' );
			}
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$meta_data = json_decode( @file_get_contents( $meta_file ), true );
		if ( empty( $meta_data['created'] ) || ! isset( $meta_data['ttl'] ) ) {
			return false;
		}

		// A TTL of 0 means the cache never expires on its own.
		if ( 0 === (int) $meta_data['ttl'] ) {
			return true;
		}

		return ( $meta_data['created'] + (int) $meta_data['ttl'] ) > time();
	}
}

new Cache_Hive_Advanced_Cache();
