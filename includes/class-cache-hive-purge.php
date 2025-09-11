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
 * Handles all cache purging operations using a dual-index symlink strategy.
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

		// Register custom purge hooks from settings.
		$custom_hooks = Cache_Hive_Settings::get( 'custom_purge_hooks' );
		if ( ! empty( $custom_hooks ) && is_array( $custom_hooks ) ) {
			foreach ( $custom_hooks as $hook ) {
				add_action( $hook, array( __CLASS__, 'purge_all' ) );
			}
		}

		// Hook up the garbage collector to a daily cron job, only if using symlinks.
		if ( Cache_Hive_Settings::get( 'use_symlinks' ) ) {
			add_action( 'cache_hive_garbage_collection', array( __CLASS__, 'garbage_collect_private_cache' ) );
			if ( ! wp_next_scheduled( 'cache_hive_garbage_collection' ) ) {
				wp_schedule_event( time(), 'daily', 'cache_hive_garbage_collection' );
			}
		}
	}

	/**
	 * Purges the entire cache, including disk, object, and third-party caches like Cloudflare.
	 * This is the master purge function that orchestrates all other purge types.
	 *
	 * @since 1.0.0
	 */
	public static function purge_all() {
		self::purge_disk_cache();
		self::purge_object_cache();

		// Also purge Cloudflare if it's enabled.
		if ( Cache_Hive_Settings::get( 'cloudflare_enabled' ) ) {
			Cache_Hive_Cloudflare::purge_all();
		}
	}

	/**
	 * Purges the entire disk cache (both public and private page cache).
	 *
	 * @since 1.0.0
	 */
	public static function purge_disk_cache() {
		if ( is_dir( CACHE_HIVE_BASE_CACHE_DIR ) ) {
			self::delete_directory( CACHE_HIVE_BASE_CACHE_DIR );
		}
	}

	/**
	 * Purges the object cache.
	 *
	 * This function leverages the standard WordPress function `wp_cache_flush()`,
	 * which correctly triggers the flush method of any active persistent object
	 * cache drop-in (e.g., Redis, Memcached).
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
	 * Purges all public and private cache files for a single URL using an OS-aware strategy.
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

		// --- 1. Purge Public Cache (Fast, Direct Deletion) ---
		$level1_dir      = substr( $url_hash, 0, 2 );
		$level2_dir      = substr( $url_hash, 2, 2 );
		$filename_prefix = substr( $url_hash, 4 );
		$target_dir      = CACHE_HIVE_PUBLIC_CACHE_DIR . '/' . $level1_dir . '/' . $level2_dir;

		if ( is_dir( $target_dir ) ) {
			try {
				$iterator = new DirectoryIterator( $target_dir );
				foreach ( $iterator as $fileinfo ) {
					if ( ! $fileinfo->isDot() && $fileinfo->isFile() && str_starts_with( $fileinfo->getFilename(), $filename_prefix ) ) {
						@unlink( $fileinfo->getRealPath() );
						@unlink( $fileinfo->getRealPath() . '.meta' );
					}
				}
			} catch ( \Exception $e ) {
				// Ignore errors if directory is not readable.
			}
		}

		// --- 2. Purge Private Cache (OS-Aware Strategy) ---
		if ( Cache_Hive_Settings::get( 'use_symlinks' ) ) {
			// Optimized Path (Linux): Purge symlinks precisely.
			$url_level1_dir = substr( $url_hash, 0, 2 );
			$url_level2_dir = substr( $url_hash, 2, 2 );
			$symlink_dir    = CACHE_HIVE_PRIVATE_URL_INDEX_DIR . '/' . $url_level1_dir . '/' . $url_level2_dir;
			$symlink_prefix = substr( $url_hash, 4 );

			// Instead of deleting the entire directory, iterate and delete only matching symlinks.
			if ( is_dir( $symlink_dir ) ) {
				try {
					$iterator = new DirectoryIterator( $symlink_dir );
					foreach ( $iterator as $fileinfo ) {
						if ( ! $fileinfo->isDot() && $fileinfo->isFile() && str_starts_with( $fileinfo->getFilename(), $symlink_prefix ) ) {
							@unlink( $fileinfo->getRealPath() );
						}
					}
				} catch ( \Exception $e ) {
					// Ignore errors.
				}
			}
		} else {
			// Compatible Path (Windows): Recursively scan the primary user cache directory.
			if ( is_dir( CACHE_HIVE_PRIVATE_USER_CACHE_DIR ) ) {
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator( CACHE_HIVE_PRIVATE_USER_CACHE_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
					RecursiveIteratorIterator::SELF_FIRST
				);
				foreach ( $iterator as $file ) {
					if ( $file->isFile() && str_starts_with( $file->getFilename(), $url_hash ) ) {
						@unlink( $file->getRealPath() );
						@unlink( $file->getRealPath() . '.meta' );
					}
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
	 * Purges the entire private cache for a specific user ID.
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

		$user_level1_dir = substr( $user_hash, 0, 2 );
		$user_level2_dir = substr( $user_hash, 2, 2 );
		$user_dir_base   = substr( $user_hash, 4 );
		$user_dir_path   = CACHE_HIVE_PRIVATE_USER_CACHE_DIR . '/' . $user_level1_dir . '/' . $user_level2_dir . '/' . $user_dir_base;

		if ( is_dir( $user_dir_path ) ) {
			self::delete_directory( $user_dir_path );
		}
	}

	/**
	 * Performs garbage collection on the private cache indexes.
	 */
	public static function garbage_collect_private_cache() {
		if ( ! Cache_Hive_Settings::get( 'use_symlinks' ) ) {
			return;
		}

		if ( ! is_dir( CACHE_HIVE_PRIVATE_URL_INDEX_DIR ) ) {
			return;
		}
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( CACHE_HIVE_PRIVATE_URL_INDEX_DIR, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file->isFile() && '.ln' === substr( $file->getFilename(), -3 ) ) {
				if ( ! @file_exists( $file->getRealPath() ) ) {
					@unlink( $file->getRealPath() );
				}
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
