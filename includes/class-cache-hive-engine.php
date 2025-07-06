<?php
/**
 * Class for handling the core caching engine operations (The "Factory").
 *
 * @since 1.2.3
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

if ( ! \defined( 'ABSPATH' ) ) {
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
			return false; }
		if ( is_null( self::$settings ) ) {
			self::$settings = Cache_Hive_Settings::get_settings(); }
		if ( ! ( self::$settings['enable_cache'] ?? false ) ) {
			return false; }
		if ( \is_admin() || \wp_doing_cron() || \wp_doing_ajax() || ( \defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) ) {
			return false; }
		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? \strtoupper( \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $request_method ) {
			return false; }
		if ( \defined( 'REST_REQUEST' ) && REST_REQUEST && ! ( self::$settings['cache_rest_api'] ?? false ) ) {
			return false; }
		return true;
	}

	/**
	 * Starts output buffering to capture page content.
	 *
	 * @return void
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
	 *
	 * @param string $buffer The captured output buffer content.
	 * @return string The original buffer content.
	 */
	private static function write_cache_file( $buffer ) {
		if ( self::is_content_cacheable( $buffer ) ) {
			// This now works as intended. The engine decides the path...
			$cache_file_path = self::get_cache_file_path_for_writing();
			// ...and passes it to the Disk writer, which will now obey.
			Cache_Hive_Disk::cache_page( $buffer, $cache_file_path );
		}
		return $buffer;
	}

	/**
	 * Generates the full path for the cache file to be written.
	 *
	 * @return string The full cache file path.
	 */
	private static function get_cache_file_path_for_writing() {
		$uri = \strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
		$uri = \rtrim( $uri, '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__'; }

		$host     = \strtolower( $_SERVER['HTTP_HOST'] ?? '' );
		$dir_path = WP_CONTENT_DIR . '/cache/cache-hive/' . $host . $uri;

		// Use the USERNAME to create the hash, matching the drop-in.
		if ( \is_user_logged_in() && ( self::$settings['cache_logged_users'] ?? false ) ) {
			$user = \wp_get_current_user();
			if ( $user && $user->ID > 0 && ! empty( $user->user_login ) ) {
				$username  = $user->user_login;
				$auth_key  = \defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
				$user_hash = 'user_' . \md5( $username . $auth_key );
				$dir_path .= '/' . $user_hash;
			}
		}

		$file_name = self::is_mobile() ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	/**
	 * Checks if the current request is from a mobile user agent.
	 *
	 * @return bool True if mobile, false otherwise.
	 */
	public static function is_mobile() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		} if ( ! ( self::$settings['cache_mobile'] ?? false ) ) {
			return false;
		} $user_agents = self::$settings['mobile_user_agents'] ?? array();
		if ( empty( $user_agents ) ) {
			return false;
		} $user_agent = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		foreach ( $user_agents as $pattern ) {
			if ( ! empty( $pattern ) && \preg_match( '#' . \preg_quote( $pattern, '#' ) . '#i', $user_agent ) ) {
				return true;
			}
		} return false; }

	/**
	 * Determines if the content in the buffer is cacheable.
	 *
	 * @param string $buffer The content buffer.
	 * @return bool True if cacheable, false otherwise.
	 */
	public static function is_content_cacheable( $buffer ) {
		if ( \strlen( $buffer ) < 255 ) {
			return false;
		} if ( ! \preg_match( '/<html|<!DOCTYPE/i', $buffer ) ) {
			return false;
		} return true; }

	/**
	 * Determines if cache creation should be bypassed based on various conditions.
	 */
	private static function should_bypass_cache_creation() {
		if ( \is_404() || \is_search() || \is_preview() || \is_trackback() || \post_password_required() ) {
			return true;
		} if ( \is_user_logged_in() && ! ( self::$settings['cache_logged_users'] ?? false ) ) {
			return true;
		} $cookie_hash = \defined( 'COOKIEHASH' ) ? COOKIEHASH : '';
		if ( ! ( self::$settings['cache_commenters'] ?? false ) && ! empty( $_COOKIE[ 'comment_author_' . $cookie_hash ] ) ) {
			return true;
		} if ( \is_user_logged_in() && ! empty( self::$settings['exclude_roles'] ) ) {
			$user = \wp_get_current_user();
			if ( ! empty( \array_intersect( (array) $user->roles, self::$settings['exclude_roles'] ) ) ) {
				return true;
			}
		} $request_uri = \esc_url_raw( \wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( ! empty( self::$settings['exclude_uris'] ) ) {
			foreach ( self::$settings['exclude_uris'] as $pattern ) {
				if ( ! empty( $pattern ) && \preg_match( '#' . $pattern . '#i', $request_uri ) ) {
					return true;
				}
			}
		} if ( ! empty( $_GET ) && ! empty( self::$settings['exclude_query_strings'] ) ) {
			$get_keys = \array_keys( \stripslashes_deep( $_GET ) );
			foreach ( $get_keys as $query_key ) {
				foreach ( self::$settings['exclude_query_strings'] as $pattern ) {
					if ( ! empty( $pattern ) && \preg_match( '#' . $pattern . '#i', $query_key ) ) {
						return true;
					}
				}
			}
		} if ( ! empty( $_COOKIE ) && ! empty( self::$settings['exclude_cookies'] ) ) {
			foreach ( \array_keys( $_COOKIE ) as $cookie_name ) {
				foreach ( self::$settings['exclude_cookies'] as $pattern ) {
					if ( ! empty( $pattern ) && \preg_match( '#' . $pattern . '#i', $cookie_name ) ) {
						return true;
					}
				}
			}
		} return (bool) \apply_filters( 'cache_hive_bypass_cache', false ); }
}
