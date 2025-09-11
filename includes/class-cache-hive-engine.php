<?php
/**
 * Class for handling the core caching engine operations (The "Factory").
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core Cache Hive engine.
 */
final class Cache_Hive_Engine {

	/**
	 * Whether the engine has started.
	 *
	 * @var bool
	 */
	public static $started = false;

	/**
	 * Plugin settings array.
	 *
	 * @var array
	 */
	public static $settings;

	/**
	 * Starts the Cache Hive engine if conditions are met.
	 */
	public static function start() {
		if ( self::should_start() ) {
			self::$started = true;
			new self();
		}
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		\add_action( 'template_redirect', array( __CLASS__, 'start_buffering' ), 1 );
	}

	/**
	 * Determines if the engine should start.
	 *
	 * @return bool
	 */
	public static function should_start() {
		if ( self::$started ) {
			return false;
		}
		if ( is_null( self::$settings ) ) {
			self::$settings = Cache_Hive_Settings::get_settings();
		}
		if ( ! ( self::$settings['enable_cache'] ?? false ) ) {
			return false;
		}
		if ( \is_admin() || \wp_doing_cron() || \wp_doing_ajax() || ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return false;
		}
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? \strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $request_method ) {
			return false;
		}
		if ( \defined( 'REST_REQUEST' ) && REST_REQUEST && ! ( self::$settings['cache_rest_api'] ?? false ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Starts output buffering to capture page content.
	 */
	public static function start_buffering() {
		if ( self::should_bypass_cache_creation() ) {
			\header( 'X-Cache-Hive-Engine: Bypass' );
			return;
		}
		\ob_start( array( __CLASS__, 'write_cache_file' ) );
	}

	/**
	 * Callback function for output buffering to write the cache file.
	 * Implements the dual-index symlink strategy for private cache on compatible systems.
	 *
	 * @param string $buffer The captured output buffer content.
	 * @return string The original buffer content.
	 */
	private static function write_cache_file( $buffer ) {
		// Flag to track if cache generation was attempted on this request.
		$cache_was_generated = false;

		if ( self::is_content_cacheable( $buffer ) ) {
			$is_private_cache = \is_user_logged_in() && ( self::$settings['cache_logged_users'] ?? false );

			if ( $is_private_cache ) {
				// --- NEW: Replace dynamic HTML elements with placeholders before caching. ---
				if ( class_exists( '\\Cache_Hive\\Includes\\Cache_Hive_Logged_In_Cache' ) ) {
					$buffer = \Cache_Hive\Includes\Cache_Hive_Logged_In_Cache::replace_dynamic_elements_with_placeholders( $buffer );
				}
				// ---------------------------------------------------------------------------

				// OS-aware logic: Use symlinks only on compatible systems (non-Windows).
				if ( self::$settings['use_symlinks'] ) {
					list( $cache_file, $symlink_file ) = self::get_private_cache_paths();
					if ( $cache_file && $symlink_file ) {
						Cache_Hive_Disk::cache_page( $buffer, $cache_file );
						self::create_symlink( $cache_file, $symlink_file );
						$cache_was_generated = true;
					}
				} else {
					// Fallback for Windows: Only write the primary cache file.
					$cache_file = self::get_private_cache_primary_path();
					if ( $cache_file ) {
						Cache_Hive_Disk::cache_page( $buffer, $cache_file );
						$cache_was_generated = true;
					}
				}
			} else {
				// Public cache logic is universal.
				$cache_file = self::get_public_cache_path();
				if ( $cache_file ) {
					Cache_Hive_Disk::cache_page( $buffer, $cache_file );
					$cache_was_generated = true;
				}
			}
		}

		// If we just generated a cache file, this request served an unoptimized version.
		// Send headers to instruct upstream caches (like Cloudflare) NOT to cache this specific response.
		// This is the key to your architecture.
		if ( $cache_was_generated && ! headers_sent() ) {
			// Custom header for debugging and specific Cloudflare rules.
			header( 'X-Cache-Hive-Status: Generating' );
			// Standard header to prevent caching by any compliant proxy or browser.
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		}

		// Return the original, unoptimized buffer to the browser for the fastest possible response.
		return $buffer;
	}

	/**
	 * Generates the path for a public cache file.
	 * Path: /public/{L1}/{L2}/{remainder}.html
	 *
	 * @return string The public cache file path.
	 */
	private static function get_public_cache_path() {
		$host      = \strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = \strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = \rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = \md5( $cache_key );

		$level1_dir    = \substr( $url_hash, 0, 2 );
		$level2_dir    = \substr( $url_hash, 2, 2 );
		$filename_base = \substr( $url_hash, 4 );
		$file_suffix   = self::is_mobile() ? '-mobile' : '';

		$dir_path  = CACHE_HIVE_PUBLIC_CACHE_DIR . '/' . $level1_dir . '/' . $level2_dir;
		$file_name = $filename_base . $file_suffix . '.html';

		return $dir_path . '/' . $file_name;
	}

	/**
	 * Generates paths for a private cache file and its corresponding symlink index.
	 * This is the high-performance path for non-Windows systems.
	 *
	 * @return array An array containing [Real cache file path, Symlink path], or [null, null] on failure.
	 */
	private static function get_private_cache_paths() {
		// Get the primary storage path first.
		$real_path = self::get_private_cache_primary_path();
		if ( ! $real_path ) {
			return array( null, null );
		}

		// Now, generate the symlink path for the URL-based index.
		$host      = \strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = \strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = \rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = \md5( $cache_key );

		$user      = \wp_get_current_user();
		$username  = $user->user_login;
		$auth_key  = \defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
		$user_hash = \md5( $username . $auth_key );

		$url_level1_dir    = \substr( $url_hash, 0, 2 );
		$url_level2_dir    = \substr( $url_hash, 2, 2 );
		$url_filename_base = \substr( $url_hash, 4 );
		$file_suffix       = self::is_mobile() ? '-mobile' : '';
		$symlink_dir       = CACHE_HIVE_PRIVATE_URL_INDEX_DIR . '/' . $url_level1_dir . '/' . $url_level2_dir;
		// The symlink name must be unique per-user for the same URL.
		$symlink_name = $url_filename_base . '-' . $user_hash . $file_suffix . '.ln';
		$symlink_path = $symlink_dir . '/' . $symlink_name;

		return array( $real_path, $symlink_path );
	}

	/**
	 * Generates only the primary storage path for a private cache file.
	 * This is used by both symlink and non-symlink (Windows) modes.
	 * Path: /private/user_cache/{user_L1}/{user_L2}/{user_remainder}/{url_hash}.html
	 *
	 * @return string|null The path to the real cache file, or null on failure.
	 */
	private static function get_private_cache_primary_path() {
		$user = \wp_get_current_user();
		if ( ! $user || ! $user->ID > 0 || empty( $user->user_login ) ) {
			return null;
		}
		$username  = $user->user_login;
		$auth_key  = \defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
		$user_hash = \md5( $username . $auth_key );

		$user_level1_dir = \substr( $user_hash, 0, 2 );
		$user_level2_dir = \substr( $user_hash, 2, 2 );
		$user_dir_base   = \substr( $user_hash, 4 );
		$user_dir_path   = CACHE_HIVE_PRIVATE_USER_CACHE_DIR . '/' . $user_level1_dir . '/' . $user_level2_dir . '/' . $user_dir_base;

		$host      = \strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = \strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = \rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = \md5( $cache_key );

		$file_suffix = self::is_mobile() ? '-mobile' : '';
		$file_name   = $url_hash . $file_suffix . '.html';

		return $user_dir_path . '/' . $file_name;
	}


	/**
	 * Creates a symlink from the symlink file to the real cache file.
	 * This is a core part of the dual-index strategy for private cache.
	 *
	 * @param string $real_path The target file (in `/user_cache/`).
	 * @param string $symlink_path The link to be created (in `/url_index/`).
	 */
	private static function create_symlink( $real_path, $symlink_path ) {
		if ( ! $real_path || ! $symlink_path ) {
			return;
		}
		$symlink_dir = dirname( $symlink_path );
		if ( ! is_dir( $symlink_dir ) ) {
			if ( ! @\mkdir( $symlink_dir, 0755, true ) ) {
				return; // Cannot create symlink if directory fails.
			}
		}
		// If a broken symlink from a previous purge exists, remove it first.
		if ( file_exists( $symlink_path ) || is_link( $symlink_path ) ) {
			@unlink( $symlink_path );
		}
		// Create the link from the `url_index` index to the `user_cache` file.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		@\symlink( $real_path, $symlink_path );
	}

	/**
	 * Checks if the current request is from a mobile user agent.
	 *
	 * @return bool True if mobile, false otherwise.
	 */
	public static function is_mobile() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		if ( ! ( self::$settings['cache_mobile'] ?? false ) ) {
			return false;
		}
		$user_agents = self::$settings['mobile_user_agents'] ?? array();
		if ( empty( $user_agents ) ) {
			return false;
		}
		$user_agent = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		foreach ( $user_agents as $pattern ) {
			if ( ! empty( $pattern ) && \preg_match( '#' . \preg_quote( $pattern, '#' ) . '#i', $user_agent ) ) {
						return true;
			}
		}
		return false;
	}

	/**
	 * Determines if the content in the buffer is cacheable.
	 *
	 * @param string $buffer The content buffer.
	 * @return bool True if cacheable, false otherwise.
	 */
	public static function is_content_cacheable( $buffer ) {
		if ( \strlen( $buffer ) < 255 ) {
			return false;
		}
		if ( ! \preg_match( '/<html|<!DOCTYPE/i', $buffer ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Determines if cache creation should be bypassed based on various conditions.
	 *
	 * @return bool True if bypass is needed.
	 */
	private static function should_bypass_cache_creation() {
		if ( \is_404() || \is_search() || \is_preview() || \is_trackback() || \post_password_required() ) {
			return true;
		}
		if ( \is_user_logged_in() && ! ( self::$settings['cache_logged_users'] ?? false ) ) {
			return true;
		}
		$cookie_hash = \defined( 'COOKIEHASH' ) ? COOKIEHASH : '';
		if ( ! ( self::$settings['cache_commenters'] ?? false ) && ! empty( $_COOKIE[ 'comment_author_' . $cookie_hash ] ) ) {
			return true;
		}
		if ( \is_user_logged_in() && ! empty( self::$settings['exclude_roles'] ) ) {
			$user = \wp_get_current_user();
			if ( ! empty( \array_intersect( (array) $user->roles, self::$settings['exclude_roles'] ) ) ) {
				return true;
			}
		}
		$request_uri = \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( ! empty( self::$settings['exclude_uris'] ) ) {
			foreach ( self::$settings['exclude_uris'] as $pattern ) {
				if ( ! empty( $pattern ) && \preg_match( '#' . $pattern . '#i', $request_uri ) ) {
					return true;
				}
			}
		}
		if ( ! empty( $_GET ) && ! empty( self::$settings['exclude_query_strings'] ) ) {
			$get_keys = \array_keys( \stripslashes_deep( $_GET ) );
			foreach ( $get_keys as $query_key ) {
				foreach ( self::$settings['exclude_query_strings'] as $pattern ) {
					if ( ! empty( $pattern ) && \preg_match( '#' . $pattern . '#i', $query_key ) ) {
						return true;
					}
				}
			}
		}
		if ( ! empty( $_COOKIE ) && ! empty( self::$settings['exclude_cookies'] ) ) {
			foreach ( \array_keys( $_COOKIE ) as $cookie_name ) {
				foreach ( self::$settings['exclude_cookies'] as $pattern ) {
					if ( ! empty( $pattern ) && \preg_match( '#' . $pattern . '#i', $cookie_name ) ) {
						return true;
					}
				}
			}
		}
		return (bool) \apply_filters( 'cache_hive_bypass_cache', false );
	}
}
