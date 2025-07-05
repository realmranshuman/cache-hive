<?php
/**
 * Handles all cache purging operations for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use DirectoryIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles all cache purging operations.
 */
final class Cache_Hive_Purge {

	/**
	 * Initializes the purge hooks.
	 */
	public static function init() {
		// Purge on post/page/cpt updates.
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 10, 2 );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_trash_post' ) );

		// Purge on comment changes.
		add_action( 'comment_post', array( __CLASS__, 'on_comment_change' ) );
		add_action( 'edit_comment', array( __CLASS__, 'on_comment_change' ) );
		add_action( 'transition_comment_status', array( __CLASS__, 'on_comment_change' ) );

		// Purge on theme/plugin/core updates.
		add_action( 'upgrader_process_complete', array( __CLASS__, 'on_upgrade' ), 10, 2 );

		// Register custom purge hooks from settings.
		$custom_hooks = Cache_Hive_Settings::get( 'custom_purge_hooks' );
		if ( ! empty( $custom_hooks ) && is_array( $custom_hooks ) ) {
			foreach ( $custom_hooks as $hook ) {
				add_action( $hook, array( __CLASS__, 'purge_all' ) );
			}
		}
	}

	/**
	 * Purges the entire cache for all sites and integrated services.
	 */
	public static function purge_all() {
		if ( is_dir( CACHE_HIVE_CACHE_DIR ) ) {
			self::delete_directory( CACHE_HIVE_CACHE_DIR );
		}

		if ( Cache_Hive_Settings::get( 'cloudflare_enabled' ) ) {
			Cache_Hive_Cloudflare::purge_all();
		}
	}

	/**
	 * Fired when a post is saved or updated.
	 *
	 * @param int     $post_id The post ID.
	 * @param WP_Post $post    The post object.
	 */
	public static function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}
		self::run_purge_rules( $post );
	}

	/**
	 * Fired when a post is trashed.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function on_trash_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			self::run_purge_rules( $post );
		}
	}

	/**
	 * Fired on any comment status change.
	 */
	public static function on_comment_change() {
		self::run_purge_rules();
	}

	/**
	 * Fired when WordPress, a plugin, or a theme is upgraded.
	 *
	 * @param object $upgrader The upgrader instance.
	 * @param array  $options  An array of options for the upgrade process.
	 */
	public static function on_upgrade( $upgrader, $options ) {
		if ( Cache_Hive_Settings::get( 'purge_on_upgrade' ) && 'update' === ( $options['action'] ?? '' ) ) {
			self::purge_all();
		}
	}

	/**
	 * Central logic to decide what to purge based on settings.
	 *
	 * @param WP_Post|null $post The post object, if available.
	 */
	private static function run_purge_rules( $post = null ) {
		if ( true === Cache_Hive_Settings::get( 'auto_purge_entire_site', false ) ) {
			self::purge_all();
			return;
		}

		if ( $post ) {
			self::purge_url( get_permalink( $post ) );
			self::purge_private_url( get_permalink( $post ) );

			if ( Cache_Hive_Settings::get( 'auto_purge_author_archive' ) ) {
				self::purge_url( get_author_posts_url( $post->post_author ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_post_type_archive' ) && get_post_type_archive_link( $post->post_type ) ) {
				self::purge_url( get_post_type_archive_link( $post->post_type ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_yearly_archive' ) ) {
				self::purge_url( get_year_link( get_the_date( 'Y', $post ) ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_monthly_archive' ) ) {
				self::purge_url( get_month_link( get_the_date( 'Y', $post ), get_the_date( 'm', $post ) ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_daily_archive' ) ) {
				self::purge_url( get_day_link( get_the_date( 'Y', 'm' ), get_the_date( 'd', $post ) ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_term_archive' ) ) {
				$taxonomies = get_object_taxonomies( $post->post_type );
				foreach ( $taxonomies as $taxonomy ) {
					$terms = get_the_terms( $post, $taxonomy );
					if ( ! is_wp_error( $terms ) && $terms ) {
						foreach ( $terms as $term ) {
							self::purge_url( get_term_link( $term ) );
						}
					}
				}
			}
		}

		if ( Cache_Hive_Settings::get( 'auto_purge_front_page' ) || Cache_Hive_Settings::get( 'auto_purge_home_page' ) || ( $post && 'page' === $post->post_type && Cache_Hive_Settings::get( 'auto_purge_pages' ) ) ) {
			self::purge_url( home_url( '/' ) );
		}
	}

	/**
	 * Purges a single URL's cache directory (mobile and desktop).
	 *
	 * @param string|false $url The URL to purge.
	 */
	public static function purge_url( $url ) {
		if ( ! $url ) {
			return;
		}
		$url_parts = wp_parse_url( $url );
		$uri       = rtrim( $url_parts['path'] ?? '', '/' );
		$uri       = empty( $uri ) ? '/__index__' : $uri;
		$host      = strtolower( $url_parts['host'] ?? '' );
		$dir_path  = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

		if ( is_dir( $dir_path ) ) {
			self::delete_directory( $dir_path );
		}
	}

	/**
	 * Purges the private cache for a specific URL.
	 *
	 * @param string $url The URL to purge private cache for.
	 */
	public static function purge_private_url( $url ) {
		$url_parts = wp_parse_url( $url );
		if ( empty( $url_parts['path'] ) ) {
			return;
		}
		$uri = rtrim( $url_parts['path'], '/' );
		$uri = empty( $uri ) ? '/__index__' : $uri;

		$host     = strtolower( $url_parts['host'] ?? '' );
		$dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

		if ( is_dir( $dir_path ) ) {
			$iterator = new DirectoryIterator( $dir_path );
			foreach ( $iterator as $fileinfo ) {
				if ( ! $fileinfo->isDot() && $fileinfo->isDir() && str_starts_with( $fileinfo->getFilename(), 'user_' ) ) {
					self::delete_directory( $fileinfo->getRealPath() );
				}
			}
		}
	}

	/**
	 * Registers hooks for purging private cache on user logout.
	 */
	public static function register_hooks() {
		add_action( 'wp_logout', array( __CLASS__, 'purge_current_user_private_cache' ) );
	}

	/**
	 * Purges the private cache for the current user on logout.
	 */
	public static function purge_current_user_private_cache() {
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && $user->ID ) {
				self::purge_user_private_cache( $user->ID );
			}
		}
	}

	/**
	 * Purges the private cache for a specific user ID.
	 *
	 * @param int $user_id The user ID whose private cache should be purged.
	 */
	public static function purge_user_private_cache( $user_id ) {
		if ( ! Cache_Hive_Settings::get( 'cache_logged_users' ) ) {
			return;
		}
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cache_hive_default_salt';
		$user_hash = 'user_' . md5( $user_id . $auth_key );

		if ( ! is_dir( CACHE_HIVE_CACHE_DIR ) ) {
			return;
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( CACHE_HIVE_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file->isDir() && $file->getFilename() === $user_hash ) {
				self::delete_directory( $file->getRealPath() );
			}
		}
	}

	/**
	 * Recursively deletes a directory and its contents.
	 *
	 * @param string $dir The directory path to delete.
	 */
	public static function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getRealPath() );
			} else {
				@unlink( $file->getRealPath() );
			}
		}
		@rmdir( $dir );
	}
}
