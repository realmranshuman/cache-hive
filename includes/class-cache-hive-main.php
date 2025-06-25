<?php
/**
 * Main plugin class for Cache Hive.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main Cache Hive plugin class.
 *
 * This class is responsible for initializing the plugin, loading components,
 * and setting up the necessary hooks. It follows a singleton pattern
 * to ensure only one instance exists.
 *
 * @since 1.0.0
 */
final class Cache_Hive_Main {

	/**
	 * The single instance of the class.
	 *
	 * @var Cache_Hive_Main
	 */
	private static $instance;

	/**
	 * Main Cache_Hive_Main Instance.
	 *
	 * Ensures only one instance of Cache_Hive_Main is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return Cache_Hive_Main - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->define_hooks();
		$this->init_components();
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		Cache_Hive_REST_API::init();
		Cache_Hive_Purge::init();
		// Cache_Hive_Cloudflare::init(); // Uncomment when ready.
		// Cache_Hive_HTML_Optimizer::init(); // Uncomment when ready.
		// Cache_Hive_CSS_Optimizer::init(); // Uncomment when ready.
		// Cache_Hive_JS_Optimizer::init(); // Uncomment when ready.
		// Cache_Hive_Media_Optimizer::init(); // Uncomment when ready.
	}

	/**
	 * Define all plugin hooks.
	 *
	 * @since 1.0.0
	 */
	private function define_hooks() {
		// Activation & deactivation are handled by hooks in the main plugin file.
		// Uninstallation is handled by the dedicated uninstall.php file.
		// This keeps the main class clean.

		// Core plugin initialization.
		add_action( 'init', array( 'Cache_Hive_Engine', 'start' ), 0 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Add a simple action link for convenience.
		add_filter( 'plugin_action_links_' . CACHE_HIVE_BASE, array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cache-hive', false, dirname( CACHE_HIVE_BASE ) . '/languages' );
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param  array $links Action links.
	 * @return array Modified action links.
	 */
	public function add_plugin_action_links( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$settings_link = '<a href="' . admin_url( 'admin.php?page=cache-hive' ) . '">' . esc_html__( 'Settings', 'cache-hive' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}
}
