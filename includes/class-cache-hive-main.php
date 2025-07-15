<?php
/**
 * Main plugin class for Cache Hive.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

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
		// Future components can be initialized here.
	}

	/**
	 * Define all plugin hooks.
	 */
	private function define_hooks() {
		// Core plugin initialization.
		add_action( 'init', array( Cache_Hive_Engine::class, 'start' ), 0 );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( Cache_Hive_Base_Optimizer::class, 'init_hooks' ) );
	}

	/**
	 * Load plugin textdomain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cache-hive', false, dirname( CACHE_HIVE_BASE ) . '/languages' );
	}
}
