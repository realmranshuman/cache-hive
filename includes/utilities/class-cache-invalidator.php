<?php
namespace CacheHive\Includes\Utilities;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles all cache invalidation (clearing) tasks.
 */
class Cache_Invalidator {

	public function __construct() {
		$this->init_invalidation_hooks();
	}

	/**
	 * Registers all WordPress hooks that should trigger cache invalidation.
	 */
	public function init_invalidation_hooks() {
		// Post and page actions.
		add_action( 'save_post', array( $this, 'on_post_change' ), 10, 2 );
		add_action( 'wp_trash_post', array( $this, 'on_post_change' ) );
		add_action( 'delete_post', array( $this, 'on_post_change' ) );

		// Comment actions.
		add_action( 'comment_post', array( $this, 'on_comment_change' ), 10, 2 );
		add_action( 'edit_comment', array( $this, 'on_comment_change' ) );
		add_action( 'transition_comment_status', array( $this, 'on_comment_status_change' ), 10, 3 );

		// Other common actions.
		add_action( 'switch_theme', array( $this, 'clear_entire_cache' ) );
		add_action( 'activated_plugin', array( $this, 'clear_entire_cache' ) );
		add_action( 'deactivated_plugin', array( $this, 'clear_entire_cache' ) );

		// Manual clearing from admin bar/dashboard will be added later.
		// Listen for the manual clear request from the settings page.
		add_action( 'cachehive_manual_clear_cache_request', array( $this, 'clear_entire_cache' ) );
	}

	/**
	 * Callback for post-related actions.
	 * For now, we clear the entire cache. Granular clearing will be added later.
	 */
	public function on_post_change() {
		$this->clear_entire_cache();
	}

	/**
	 * Callback for when a new comment is posted or edited.
	 */
	public function on_comment_change( $comment_id, $comment_approved = null ) {
		if ( 1 === $comment_approved || 'approve' === $comment_approved ) {
			$this->clear_entire_cache();
		}
	}

	/**
	 * Callback for when a comment's status changes.
	 */
	public function on_comment_status_change( $new_status, $old_status, $comment ) {
		if ( $old_status !== $new_status && ( 'approved' === $new_status || 'approved' === $old_status ) ) {
			$this->clear_entire_cache();
		}
	}

	/**
	 * Deletes all files and folders in the CacheHive cache directory.
	 * This is the refactored logic from the old plugin's `cache_iterator`.
	 */
	public function clear_entire_cache() {
		$cache_dir = \CacheHive\Includes\Caching\Page_Cache_Manager::CACHE_DIR;

		if ( ! is_dir( $cache_dir ) ) {
			return;
		}

		$iterator = new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
		$files    = new \RecursiveIteratorIterator( $iterator, \RecursiveIteratorIterator::CHILD_FIRST );

		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getRealPath() );
			} else {
				unlink( $file->getRealPath() );
			}
		}

		// Re-add the security file.
		if ( ! file_exists( $cache_dir . 'index.php' ) ) {
			@file_put_contents( $cache_dir . 'index.php', '<?php // Silence is golden.' );
		}

		do_action( 'cachehive_entire_cache_cleared' );
	}

	/**
	 * Deletes expired cache files. Called by the WP-Cron job.
	 */
	public function clear_expired_cache() {
		// Need to get the setting from the database directly, as Settings class isn't passed here.
		$options  = get_option( CACHEHIVE_SETTINGS_SLUG );
		$lifespan = isset( $options['cache_lifespan'] ) ? (int) $options['cache_lifespan'] : 0;

		if ( $lifespan <= 0 ) {
			return; // Expiry is disabled.
		}

		$lifespan_seconds = $lifespan * HOUR_IN_SECONDS;
		$cache_dir        = \CacheHive\Includes\Caching\Page_Cache_Manager::CACHE_DIR;

		if ( ! is_dir( $cache_dir ) ) {
			return;
		}

		$iterator = new \RecursiveDirectoryIterator( $cache_dir, \RecursiveDirectoryIterator::SKIP_DOTS );
		$files    = new \RecursiveIteratorIterator( $iterator, \RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $files as $file ) {
			if ( $file->isFile() && $file->getMTime() < ( time() - $lifespan_seconds ) ) {
				unlink( $file->getRealPath() );
			}
		}
	}
}
