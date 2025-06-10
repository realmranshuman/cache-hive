<?php
namespace CacheHive\Includes;

use CacheHive\Includes\Admin\Admin;
use CacheHive\Includes\Caching\Page_Cache_Manager;
use CacheHive\Includes\Utilities\Cache_Invalidator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class. Orchestrates all modules.
 */
final class Core {

	private static $instance = null;

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
		$this->load_dependencies();
	}

	private function init_hooks() {
		register_activation_hook( CACHEHIVE_PLUGIN_DIR . 'cache-hive.php', array( __NAMESPACE__ . '\Install', 'activate' ) );
		register_deactivation_hook( CACHEHIVE_PLUGIN_DIR . 'cache-hive.php', array( __NAMESPACE__ . '\Deactivation', 'deactivate' ) );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	private function load_dependencies() {
		$settings = new Settings();

		if ( is_admin() ) {
			new Admin( $settings );
		}

		// Always load the invalidator to handle clearing actions
		$invalidator = new Cache_Invalidator();

		// Only load the page cache manager if it's enabled.
		if ( $settings->get_option( 'page_cache_enabled' ) ) {
			new Page_Cache_Manager( $settings, $invalidator );
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'cache-hive',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}
}
