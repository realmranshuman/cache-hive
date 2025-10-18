<?php
/**
 * Main plugin class for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Batch_Processor;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Optimizer;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Media_Integration;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Stats;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Meta;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main Cache Hive plugin class.
 *
 * Initializes the plugin, loads components, and sets up hooks.
 */
final class Cache_Hive_Main {

	/**
	 * The single instance of the class.
	 *
	 * @var Cache_Hive_Main|null
	 */
	private static $instance = null;

	/**
	 * Main Cache_Hive_Main Instance.
	 *
	 * @static
	 * @return Cache_Hive_Main Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_hooks();
		$this->init_components();
	}

	/**
	 * Initialize plugin components.
	 */
	private function init_components() {
		Cache_Hive_REST_API::init();
		Cache_Hive_Purge::init();
		Cache_Hive_Object_Cache::init();

		// Initialize Image Optimization components.
		if ( is_admin() ) {
			new Cache_Hive_Media_Integration();
		}

		// Schedule cron if batch processing is enabled.
		if ( Cache_Hive_Settings::get( 'image_batch_processing', false ) ) {
			Cache_Hive_Image_Batch_Processor::schedule_event();
		} else {
			Cache_Hive_Image_Batch_Processor::clear_scheduled_event();
		}
	}

	/**
	 * Define all plugin hooks.
	 */
	private function define_hooks() {
		// Core plugin initialization.
		add_action( 'init', array( Cache_Hive_Engine::class, 'start' ), 0 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( Cache_Hive_Base_Optimizer::class, 'init_hooks' ) );

		// Image Optimization hooks.
		add_action( 'add_attachment', array( Cache_Hive_Image_Optimizer::class, 'auto_optimize_on_upload' ) );
		add_action( 'delete_attachment', array( Cache_Hive_Image_Optimizer::class, 'cleanup_on_delete' ) );
		// Use the dynamic, site-specific hook name.
		add_action( Cache_Hive_Image_Batch_Processor::get_cron_hook(), array( Cache_Hive_Image_Batch_Processor::class, 'process_batch' ) );

		// Image Stats atomic counter hooks.
		add_action( 'add_attachment', array( Cache_Hive_Image_Stats::class, 'increment_total_count' ) );
		add_action( 'delete_attachment', array( $this, 'handle_attachment_deletion_for_stats' ) );
	}

	/**
	 * A wrapper for delete_attachment to handle both total and optimized counts.
	 *
	 * @param int $post_id The ID of the attachment being deleted.
	 */
	public function handle_attachment_deletion_for_stats( int $post_id ) {
		// We get the meta BEFORE deleting it to check if it was optimized.
		$was_optimized = Cache_Hive_Image_Meta::is_optimized( $post_id );

		Cache_Hive_Image_Stats::decrement_total_count();
		if ( $was_optimized ) {
			Cache_Hive_Image_Stats::decrement_optimized_count();
		}
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cache-hive', false, dirname( CACHE_HIVE_BASE ) . '/languages' );
	}
}
