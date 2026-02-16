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
	 * Callback function for output buffering to write the cache file and pointer index.
	 *
	 * @param string $buffer The captured output buffer content.
	 * @return string The original or optimized buffer content.
	 */
	private static function write_cache_file( $buffer ) {
		if ( ! self::is_content_cacheable( $buffer ) ) {
			return $buffer;
		}

		$is_private_cache = \is_user_logged_in() && ( self::$settings['cache_logged_users'] ?? false );
		$cache_file       = null;
		$original_buffer  = $buffer; // Keep a copy of the original buffer for fallback.

		if ( $is_private_cache ) {
			if ( class_exists( '\\Cache_Hive\\Includes\\Cache_Hive_Logged_In_Cache' ) ) {
				$buffer = \Cache_Hive\Includes\Cache_Hive_Logged_In_Cache::replace_dynamic_elements_with_placeholders( $buffer );
			}
			$cache_file = self::get_private_cache_path();
		} else {
			$cache_file = self::get_public_cache_path();
		}

		if ( $cache_file ) {
			$optimized_buffer = Cache_Hive_Disk::cache_page( $buffer, $cache_file );

			if ( false !== $optimized_buffer ) {
				// If this was a private cache, create the pointer file for purging.
				if ( $is_private_cache ) {
					// We need the hashes again to record the index.
					// This is slightly inefficient but keeps the method signature clean.
					// Or we could have get_private_cache_path return metadata.
					// For now, let's recalculate simply.
					$user      = \wp_get_current_user();
					$username  = $user->user_login;
					$auth_key  = \defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
					$user_hash = \md5( $username . $auth_key );

					$host      = \strtolower( $_SERVER['HTTP_HOST'] ?? '' );
					$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
					$uri       = \strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
					$uri       = \rtrim( $uri, '/' );
					$uri       = empty( $uri ) ? '/' : $uri;
					$cache_key = $scheme . '://' . $host . $uri;
					$url_hash  = \md5( $cache_key );

					self::record_private_cache_index( $user_hash, $url_hash );
				}

				if ( ! headers_sent() ) {
					header( 'X-Cache-Hive-Engine: Miss (Generated)' );
				}

				$final_content_for_browser = $optimized_buffer;
				if ( $is_private_cache && class_exists( '\\Cache_Hive\\Includes\\Cache_Hive_Logged_In_Cache' ) ) {
					$final_content_for_browser = \Cache_Hive\Includes\Cache_Hive_Logged_In_Cache::inject_dynamic_elements_from_placeholders( $optimized_buffer );
				}

				return $final_content_for_browser . Cache_Hive_Disk::get_cache_signature();
			}
		}

		// Fallback: If caching failed or no path was determined, return the original buffer.
		return $original_buffer;
	}


	/**
	 * Generates the path for a public cache file.
	 * Path: /public/{L1}/{L2}/{remainder}.cache
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

		$dir_path  = \CACHE_HIVE_PUBLIC_CACHE_DIR . '/' . $level1_dir . '/' . $level2_dir;
		$file_name = $filename_base . $file_suffix . '.cache';

		return $dir_path . '/' . $file_name;
	}

	/**
	 * Generates the primary storage path for a private cache file.
	 * Path: /private/user_cache/{user_L1}/{user_L2}/{user_remainder}/{url_hash}.cache
	 *
	 * @return string|null The path to the real cache file, or null on failure.
	 */
	/**
	 * Generates the primary storage path for a private cache file.
	 * Path: /private/user_cache/{user_L1}/{user_L2}/{url_hash}/{user_hash}.cache
	 * Note: We now group by URL first (roughly) to allow easy URL purging.
	 * Wait, no. The request is URL -> User.
	 * So /private/user_cache/{url_L1}/{url_L2}/{url_rem}/{user_hash}.cache
	 * But we want to reuse existing constants if possible.
	 * Let's stick to the plan: /private/user_cache/{url_L1}/{url_L2}/{url_remainder}/{user_hash}.cache
	 *
	 * @return string|null The path to the real cache file, or null on failure.
	 */
	private static function get_private_cache_path() {
		$user = \wp_get_current_user();
		if ( ! $user || ! $user->ID > 0 || empty( $user->user_login ) ) {
			return null;
		}

		// 1. Calculate URL Hash
		$host      = \strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$scheme    = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ) ? 'https' : 'http';
		$uri       = \strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri       = \rtrim( $uri, '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = \md5( $cache_key );

		// 2. Calculate User Hash
		$username  = $user->user_login;
		$auth_key  = \defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
		$user_hash = \md5( $username . $auth_key );

		// 3. Build Path: .../public_structure/{user_hash}.cache
		$url_l1       = \substr( $url_hash, 0, 2 );
		$url_l2       = \substr( $url_hash, 2, 2 );
		$url_rem      = \substr( $url_hash, 4 );
		$url_dir_path = \CACHE_HIVE_PRIVATE_USER_CACHE_DIR . '/' . $url_l1 . '/' . $url_l2 . '/' . $url_rem;

		$file_suffix = self::is_mobile() ? '-mobile' : '';
		$file_name   = $user_hash . $file_suffix . '.cache';

		return $url_dir_path . '/' . $file_name;
	}

	/**
	 * Records the User-URL relationship in the database index.
	 *
	 * @param string $user_hash The user hash.
	 * @param string $url_hash  The URL hash.
	 * @return bool|int False on failure, number of rows affected on success.
	 */
	private static function record_private_cache_index( $user_hash, $url_hash ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'cache_hive_private_index';

		// Use INSERT IGNORE to avoid errors on duplicate entries (which are fine).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->prefix}cache_hive_private_index (user_hash, url_hash) VALUES (%s, %s)",
				$user_hash,
				$url_hash
			)
		);
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
