<?php
/**
 * Class for handling the core caching engine operations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core Cache Hive engine.
 */
final class Cache_Hive_Engine {

	public static $started = false;
	public static $settings;

	public static function start() {
		if ( self::should_start() ) {
			self::$started = true;
			new self();
		}
		return self::$started;
	}

	private function __construct() {
		self::$settings = Cache_Hive_Settings::get_settings();

		// This hook is for when the drop-in misses, but WordPress can still serve cache.
		add_action( 'template_redirect', array( __CLASS__, 'deliver_cache' ), 0 );

		// If no cache was delivered, start output buffering to capture the page.
		add_action( 'template_redirect', array( __CLASS__, 'start_buffering' ), 1 );
	}

	public static function should_start() {
		if ( self::$started ) {
			return false;
		}
		if ( is_admin() || defined( 'DOING_CRON' ) || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) || defined( 'XMLRPC_REQUEST' ) ) {
			return false;
		}
		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' ) {
			return false;
		}
		if ( ! ( Cache_Hive_Settings::get( 'enableCache' ) ?? false ) ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST && ! ( Cache_Hive_Settings::get( 'cacheRestApi' ) ?? false ) ) {
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

		if ( Cache_Hive_Disk::is_cache_valid( $cache_file ) ) {
			header( 'X-Cache-Hive: Hit (Engine)' );
		} elseif ( ( self::$settings['serveStale'] ?? false ) && file_exists( $cache_file ) ) {
			header( 'X-Cache-Hive: Stale (Engine)' );
		} else {
			header( 'X-Cache-Hive: Miss (Engine)' );
			return;
		}
		
		if ( class_exists( 'Cache_Hive_Browser_Cache' ) ) {
			Cache_Hive_Browser_Cache::send_headers( self::$settings );
		}
		
		readfile( $cache_file );
		exit;
	}

	public static function start_buffering() {
		if ( self::bypass_cache() ) {
			return;
		}
		ob_start( array( __CLASS__, 'end_buffering' ) );
	}

	private static function end_buffering( $buffer ) {
		if ( ! self::is_cacheable( $buffer ) || self::bypass_cache() ) {
			return $buffer;
		}
		Cache_Hive_Disk::cache_page( $buffer );
		return $buffer;
	}

	public static function is_cacheable( $buffer ) {
		if ( strlen( $buffer ) < 255 ) {
			return false;
		}
		if ( ! preg_match( '/<html|<!DOCTYPE/i', $buffer ) ) {
			return false;
		}
		if ( preg_match( '/<?xml/i', $buffer ) && ! preg_match( '/<!DOCTYPE/i', $buffer ) ) {
			return false;
		}
		return true;
	}

	/**
	 * The master list of exclusion rules checked during a full WordPress load.
	 */
	private static function bypass_cache() {
		if ( is_404() || is_search() || is_preview() || is_trackback() || post_password_required() ) {
			return true;
		}
		// Special handling for feeds, which have their own TTL.
		if ( is_feed() && ( self::$settings['feedTTL'] ?? 604800 ) <= 0 ) {
			return true;
		}

		if ( ! ( self::$settings['cacheLoggedUsers'] ?? false ) && is_user_logged_in() ) {
			return true;
		}
		if ( ! ( self::$settings['cacheCommenters'] ?? false ) && isset( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
			return true;
		}

		if ( is_user_logged_in() && ! empty( self::$settings['excludeRoles'] ) ) {
			$user = wp_get_current_user();
			if ( ! empty( array_intersect( (array) $user->roles, self::$settings['excludeRoles'] ) ) ) {
				return true;
			}
		}

		$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( ! empty( self::$settings['excludeUris'] ) ) {
			foreach ( self::$settings['excludeUris'] as $pattern ) {
				if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $request_uri ) ) {
					return true;
				}
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET ) && ! empty( self::$settings['excludeQueryStrings'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$get_keys = array_keys( $_GET );
			foreach ( $get_keys as $query_key ) {
				foreach ( self::$settings['excludeQueryStrings'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $query_key ) ) {
						return true;
					}
				}
			}
		}

		if ( ! empty( $_COOKIE ) && ! empty( self::$settings['excludeCookies'] ) ) {
			foreach ( array_keys( $_COOKIE ) as $cookie_name ) {
				foreach ( self::$settings['excludeCookies'] as $pattern ) {
					if ( ! empty( $pattern ) && preg_match( '#' . $pattern . '#i', $cookie_name ) ) {
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
	 * @since 1.0.0
	 * @return bool
	 */
	public static function is_mobile() {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}
		if ( ! ( self::$settings['cacheMobile'] ?? false ) ) {
			return false;
		}

		$user_agents = self::$settings['mobileUserAgents'] ?? array();
		if ( empty( $user_agents ) ) {
			return false;
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
		foreach ( $user_agents as $pattern ) {
			if ( ! empty( $pattern ) ) {
				// SOLID FIX: Use preg_quote to escape special regex characters in user agent strings.
				if ( preg_match( '#' . preg_quote( $pattern, '#' ) . '#i', $user_agent ) ) {
					return true;
				}
			}
		}

		return false;
	}
}