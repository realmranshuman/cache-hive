<?php
/**
 * Class for handling the core caching engine operations.
 *
 * @package Cache_Hive
 * @since 1.0.0
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
	 *
	 * @return bool True if started, false otherwise.
	 */
	public static function start() {
		if ( self::should_start() ) {
			self::$started = true;
			new self();
		}
		return self::$started;
	}

	/**
	 * Constructor. Initializes settings and hooks.
	 */
	private function __construct() {
		self::$settings = Cache_Hive_Settings::get_settings();
		add_action( 'template_redirect', array( __CLASS__, 'deliver_cache' ), 0 );
		add_action( 'template_redirect', array( __CLASS__, 'start_buffering' ), 1 );
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
		if ( is_admin() || defined( 'DOING_CRON' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || defined( 'XMLRPC_REQUEST' ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return false;
		}
		if ( ! Cache_Hive_Settings::get( 'enable_cache', false ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! Cache_Hive_Settings::get( 'cache_rest_api', false ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Tries to deliver a cached page if the drop-in didn't.
	 */
	public static function deliver_cache() {
		if ( self::bypass_cache() ) {
			header( 'X-Cache-Hive: Bypassed (Engine)' );
			return;
		}

		$cache_file = Cache_Hive_Disk::get_cache_file_path();

		if ( self::is_cache_valid( $cache_file ) ) {
			header( 'X-Cache-Hive: Hit (Engine)' );
		} elseif ( ( self::$settings['serve_stale'] ?? false ) && file_exists( $cache_file ) ) {
			header( 'X-Cache-Hive: Stale (Engine)' );
		} else {
			header( 'X-Cache-Hive: Miss (Engine)' );
			return;
		}

		if ( class_exists( Cache_Hive_Browser_Cache::class ) ) {
			Cache_Hive_Browser_Cache::send_headers( self::$settings );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		readfile( $cache_file );
		exit;
	}

	/**
	 * Starts output buffering to capture the page if no cache was delivered.
	 */
	public static function start_buffering() {
		if ( ! self::bypass_cache() ) {
			ob_start( array( __CLASS__, 'end_buffering' ) );
		}
	}

	/**
	 * Callback for output buffering. Caches the page if cacheable.
	 *
	 * @param string $buffer The output buffer contents.
	 * @return string
	 */
	private static function end_buffering( $buffer ) {
		if ( ! self::is_cacheable( $buffer ) || self::bypass_cache() ) {
			return $buffer;
		}
		Cache_Hive_Disk::cache_page( $buffer );
		return $buffer;
	}

	/**
	 * Determines if the buffer is cacheable HTML output.
	 *
	 * @param string $buffer The output buffer contents.
	 * @return bool
	 */
	public static function is_cacheable( $buffer ) {
		if ( strlen( $buffer ) < 255 ) {
			return false;
		}
		return (bool) preg_match( '/<html|<!DOCTYPE/i', $buffer );
	}

	/**
	 * The master list of exclusion rules checked during a full WordPress load.
	 *
	 * @return bool
	 */
	private static function bypass_cache() {
		if ( is_404() || is_search() || is_preview() || is_trackback() || post_password_required() ) {
			return true;
		}
		if ( is_feed() && ( self::$settings['feed_ttl'] ?? 604800 ) <= 0 ) {
			return true;
		}

		if ( is_user_logged_in() ) {
			if ( ! ( self::$settings['cache_logged_users'] ?? false ) ) {
				return true;
			}
			$user = wp_get_current_user();
			if ( ! empty( self::$settings['exclude_roles'] ) && ! empty( array_intersect( (array) $user->roles, self::$settings['exclude_roles'] ) ) ) {
				return true;
			}
		}

		if ( ! ( self::$settings['cache_commenters'] ?? false ) && isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( ! empty( self::$settings['exclude_uris'] ) ) {
			foreach ( self::$settings['exclude_uris'] as $pattern ) {
				if ( ! empty( $pattern ) && preg_match( '#' . str_replace( '#', '\#', $pattern ) . '#i', $request_uri ) ) {
					return true;
				}
			}
		}

		if ( ! empty( $_GET ) && ! empty( self::$settings['exclude_query_strings'] ) ) {
			$get_keys = array_keys( wp_unslash( $_GET ) );
			foreach ( $get_keys as $query_key ) {
				foreach ( self::$settings['exclude_query_strings'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . str_replace( '#', '\#', $pattern ) . '#i', $query_key ) ) {
						return true;
					}
				}
			}
		}

		if ( ! empty( $_COOKIE ) && ! empty( self::$settings['exclude_cookies'] ) ) {
			$cookie_keys = array_keys( wp_unslash( $_COOKIE ) );
			foreach ( $cookie_keys as $cookie_name ) {
				foreach ( self::$settings['exclude_cookies'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . str_replace( '#', '\#', $pattern ) . '#i', $cookie_name ) ) {
						return true;
					}
				}
			}
		}

		return (bool) apply_filters( 'cache_hive_bypass_cache', false );
	}

	/**
	 * Checks if the current visitor is a mobile device.
	 *
	 * @return bool
	 */
	public static function is_mobile() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if ( empty( $user_agent ) || ! ( self::$settings['cache_mobile'] ?? false ) ) {
			return false;
		}

		$mobile_user_agents = self::$settings['mobile_user_agents'] ?? array();
		if ( empty( $mobile_user_agents ) ) {
			return false;
		}

		$regex = '/' . implode( '|', array_map( 'preg_quote', $mobile_user_agents, array( '/' ) ) ) . '/i';
		return (bool) preg_match( $regex, $user_agent );
	}

	/**
	 * Checks if a cache file is valid (exists and is not expired).
	 *
	 * @param string $cache_file The full path to the cache file.
	 * @return bool
	 */
	public static function is_cache_valid( $cache_file ) {
		$meta_file = $cache_file . '.meta';
		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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
