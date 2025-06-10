<?php
/**
 * Plugin Name:       CacheHive
 * Plugin URI:        https://github.com/realmranshuman/cache-hive
 * Description:       An enterprise-grade performance and caching suite for WordPress.
 * Version:           1.0.0
 * Author:            realmranshuman
 * Author URI:        https://github.com/realmranshuman
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cache-hive
 * Domain Path:       /languages
 *
 * @package CacheHive
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants.
define( 'CACHEHIVE_VERSION', '1.0.0' );
define( 'CACHEHIVE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CACHEHIVE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CACHEHIVE_SETTINGS_SLUG', 'cachehive' );

/**
 * The main function to launch the plugin.
 */
function cache_hive_run() {
	// Load the autoloader.
	require_once CACHEHIVE_PLUGIN_DIR . 'includes/class-autoloader.php';
	new \CacheHive\Includes\Autoloader();

	// Instantiate the core class.
	\CacheHive\Includes\Core::get_instance();
}

cache_hive_run();
