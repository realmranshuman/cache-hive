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
 * Handles all cache purging operations using a sharded pointer file index strategy.
 */
final class Cache_Hive_Purge {

	/**
	 * Initializes the purge hooks.
	 *
	 * @since 1.0.0
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

		// Purge when a user's profile is updated (e.g., role change).
		add_action( 'profile_update', array( __CLASS__, 'purge_user_private_cache' ), 10, 1 );

		// Register custom purge hooks from settings.
		$custom_hooks = Cache_Hive_Settings::get( 'custom_purge_hooks' );
		if ( ! empty( $custom_hooks ) && is_array( $custom_hooks ) ) {
			foreach ( $custom_hooks as $hook ) {
				add_action( $hook, array( __CLASS__, 'purge_all' ) );
			}
		}
	}

	/**
	 * Purges the entire cache for the current site or the entire network.
	 * This is the master purge function that orchestrates all other purge types.
	 *
	 * @since 1.0.0
	 * @param bool $network_wide If true, purges all sites in the network.
	 */
	public static function purge_all( $network_wide = false ) {
		if ( is_multisite() && ( $network_wide || is_network_admin() ) ) {
			foreach ( get_sites( array( 'fields' => 'ids' ) ) as $blog_id ) {
				switch_to_blog( $blog_id );
				self::purge_site_caches();
				restore_current_blog();
			}
		} else {
			self::purge_site_caches();
		}
	}

	/**
	 * Helper function to purge all caches for the current site.
	 *
	 * @since 1.1.0
	 */
	private static function purge_site_caches() {
		self::purge_disk_cache();
		self::purge_object_cache();

		if ( Cache_Hive_Settings::get( 'cloudflare_enabled' ) ) {
			Integrations\Cache_Hive_Cloudflare::purge_all();
		}
	}


	/**
	 * Purges the entire disk cache (both public and private page cache) for the current site.
	 *
	 * @since 1.0.0
	 */
	public static function purge_disk_cache() {
		if ( defined( 'CACHE_HIVE_BASE_CACHE_DIR' ) && is_dir( CACHE_HIVE_BASE_CACHE_DIR ) ) {
			self::delete_directory( CACHE_HIVE_BASE_CACHE_DIR );
		}
	}

	/**
	 * Purges the object cache for the current site.
	 *
	 * @since 1.0.0
	 */
	public static function purge_object_cache() {
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
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
			self::purge_all( true ); // Purge all sites on upgrade.
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

		$urls_to_purge = array();
		if ( $post ) {
			$urls_to_purge[] = get_permalink( $post );

			if ( Cache_Hive_Settings::get( 'auto_purge_author_archive' ) ) {
				$urls_to_purge[] = get_author_posts_url( $post->post_author );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_post_type_archive' ) && get_post_type_archive_link( $post->post_type ) ) {
				$urls_to_purge[] = get_post_type_archive_link( $post->post_type );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_yearly_archive' ) ) {
				$urls_to_purge[] = get_year_link( get_the_date( 'Y', $post ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_monthly_archive' ) ) {
				$urls_to_purge[] = get_month_link( get_the_date( 'Y', $post ), get_the_date( 'm', $post ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_daily_archive' ) ) {
				$urls_to_purge[] = get_day_link( get_the_date( 'Y', $post ), get_the_date( 'm', $post ), get_the_date( 'd', $post ) );
			}
			if ( Cache_Hive_Settings::get( 'auto_purge_term_archive' ) ) {
				$taxonomies = get_object_taxonomies( $post->post_type );
				foreach ( $taxonomies as $taxonomy ) {
					$terms = get_the_terms( $post, $taxonomy );
					if ( ! is_wp_error( $terms ) && $terms ) {
						foreach ( $terms as $term ) {
							$urls_to_purge[] = get_term_link( $term );
						}
					}
				}
			}
		}

		if ( Cache_Hive_Settings::get( 'auto_purge_front_page' ) || Cache_Hive_Settings::get( 'auto_purge_home_page' ) || ( $post && 'page' === $post->post_type && Cache_Hive_Settings::get( 'auto_purge_pages' ) ) ) {
			$urls_to_purge[] = home_url( '/' );
		}

		foreach ( array_unique( $urls_to_purge ) as $url ) {
			self::purge_url( $url );
		}
	}

	/**
	 * Purges all public and private cache files for a single URL using the pointer index strategy.
	 *
	 * @param string|false $url The URL to purge.
	 */
	public static function purge_url( $url ) {
		if ( ! $url || ! is_string( $url ) ) {
			return;
		}
		$url_parts = wp_parse_url( $url );
		if ( empty( $url_parts['host'] ) || empty( $url_parts['scheme'] ) ) {
			return;
		}

		$scheme    = $url_parts['scheme'];
		$host      = strtolower( $url_parts['host'] );
		$uri       = rtrim( $url_parts['path'] ?? '', '/' );
		$uri       = empty( $uri ) ? '/' : $uri;
		$cache_key = $scheme . '://' . $host . $uri;
		$url_hash  = md5( $cache_key );

		// --- 1. Purge Public Cache ---
		$url_l1          = substr( $url_hash, 0, 2 );
		$url_l2          = substr( $url_hash, 2, 2 );
		$filename_prefix = substr( $url_hash, 4 );
		$public_dir      = CACHE_HIVE_PUBLIC_CACHE_DIR . "/{$url_l1}/{$url_l2}";

		if ( is_dir( $public_dir ) ) {
			try {
				foreach ( new DirectoryIterator( $public_dir ) as $fileinfo ) {
					if ( ! $fileinfo->isDot() && $fileinfo->isFile() && str_starts_with( $fileinfo->getFilename(), $filename_prefix ) ) {
						@unlink( $fileinfo->getRealPath() );
						@unlink( $fileinfo->getRealPath() . '.meta' );
					}
				}
			} catch ( \Exception $e ) {
				// Ignore errors if directory is not readable.
			}
		}

		// --- 2. Purge Private Cache ---
		// New Structure: /private/user_cache/{url_l1}/{url_l2}/{url_rem}/...
		$url_rem         = substr( $url_hash, 4 );
		$private_url_dir = CACHE_HIVE_PRIVATE_USER_CACHE_DIR . "/{$url_l1}/{$url_l2}/{$url_rem}";

		if ( is_dir( $private_url_dir ) ) {
			self::delete_directory( $private_url_dir );
		}

		// Clean up the Database Index.
		global $wpdb;
		$table_name = $wpdb->prefix . 'cache_hive_private_index';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}cache_hive_private_index WHERE url_hash = %s", $url_hash )
		);
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
	 * Purges the entire private cache for a specific user ID.
	 * Moves from O(N) URL scanning to O(1) DB lookup.
	 *
	 * @param int $user_id The user ID whose private cache should be purged.
	 */
	public static function purge_user_private_cache( $user_id ) {
		if ( ! Cache_Hive_Settings::get( 'cache_logged_users' ) ) {
			return;
		}

		$user_data = get_userdata( $user_id );
		if ( ! $user_data || empty( $user_data->user_login ) ) {
			return;
		}
		$username  = $user_data->user_login;
		$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive_fallback_key';
		$user_hash = md5( $username . $auth_key );

		global $wpdb;
		$table_name = $wpdb->prefix . 'cache_hive_private_index';

		// 1. Get all URLs this user has visited from the index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$urls = $wpdb->get_col(
			$wpdb->prepare( "SELECT url_hash FROM {$wpdb->prefix}cache_hive_private_index WHERE user_hash = %s", $user_hash )
		);

		if ( empty( $urls ) ) {
			return;
		}

		// 2. Delete the actual files for each URL.
		foreach ( $urls as $url_hash ) {
			$url_l1  = substr( $url_hash, 0, 2 );
			$url_l2  = substr( $url_hash, 2, 2 );
			$url_rem = substr( $url_hash, 4 );

			$url_dir_path = CACHE_HIVE_PRIVATE_USER_CACHE_DIR . "/{$url_l1}/{$url_l2}/{$url_rem}";
			$file_prefix  = "{$url_dir_path}/{$user_hash}";

			@unlink( $file_prefix . '.cache' );
			@unlink( $file_prefix . '.cache.meta' );
			@unlink( $file_prefix . '-mobile.cache' );
			@unlink( $file_prefix . '-mobile.cache.meta' );
		}

		// 3. Clean up the index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$wpdb->prefix}cache_hive_private_index WHERE user_hash = %s", $user_hash )
		);
	}

	/**
	 * Recursively deletes a directory and its contents with a security safeguard.
	 *
	 * @param string $dir The directory path to delete.
	 */
	public static function delete_directory( $dir ) {
		// SECURITY HARDENING: Add a safeguard to ensure we only operate within our designated directories.
		$real_dir         = realpath( $dir );
		$real_cache_root  = defined( 'CACHE_HIVE_ROOT_CACHE_DIR' ) ? realpath( CACHE_HIVE_ROOT_CACHE_DIR ) : false;
		$real_config_root = defined( 'CACHE_HIVE_ROOT_CONFIG_DIR' ) ? realpath( CACHE_HIVE_ROOT_CONFIG_DIR ) : false;

		// Abort if the path is invalid or outside of our allowed root directories.
		if ( ! $real_dir || ( ( ! $real_cache_root || strpos( $real_dir, $real_cache_root ) !== 0 ) && ( ! $real_config_root || strpos( $real_dir, $real_config_root ) !== 0 ) ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Cache Hive Security: Aborted attempt to delete a directory outside of allowed paths: ' . $dir );
			}
			return;
		}

		if ( ! is_dir( $dir ) ) {
			return;
		}
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach ( $iterator as $file ) {
				if ( $file->isDir() ) {
					// Use @ to suppress warnings on permission errors.
					@rmdir( $file->getRealPath() );
				} else {
					@unlink( $file->getRealPath() );
				}
			}
			@rmdir( $dir );
		} catch ( \Exception $e ) {
			// Fails silently if permissions are not sufficient.
		}
	}
}
