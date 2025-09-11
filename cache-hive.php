<?php
/**
 * Plugin Name:       Cache Hive
 * Plugin URI:        https://github.com/realmranshuman/cache-hive
 * Description:       A powerful caching and performance optimization plugin for WordPress.
 * Version:           1.0.0
 * Author:            Anshuman
 * Author URI:        https://github.com/realmranshuman
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cache-hive
 * Domain Path:       /languages
 *
 * @package           Cache_Hive
 */

use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Main;
use Cache_Hive\Includes\Cache_Hive_Purge;
use Cache_Hive\Includes\Cache_Hive_Admin_Bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// =========================================================================
// PLUGIN CONSTANTS
// =========================================================================
if ( ! defined( 'CACHE_HIVE_VERSION' ) ) {
	define( 'CACHE_HIVE_VERSION', '1.0.0' );
}
if ( ! defined( 'CACHE_HIVE_FILE' ) ) {
	define( 'CACHE_HIVE_FILE', __FILE__ );
}
if ( ! defined( 'CACHE_HIVE_BASE' ) ) {
	define( 'CACHE_HIVE_BASE', plugin_basename( CACHE_HIVE_FILE ) );
}
if ( ! defined( 'CACHE_HIVE_DIR' ) ) {
	define( 'CACHE_HIVE_DIR', plugin_dir_path( CACHE_HIVE_FILE ) );
}
if ( ! defined( 'CACHE_HIVE_URL' ) ) {
	define( 'CACHE_HIVE_URL', plugin_dir_url( CACHE_HIVE_FILE ) );
}

/**
 * The base directory for all Cache Hive cache files.
 *
 * @since 1.0.0
 */
if ( ! defined( 'CACHE_HIVE_BASE_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_BASE_CACHE_DIR', WP_CONTENT_DIR . '/cache/cache-hive' );
}

/**
 * The directory for storing public (anonymous user) cache files.
 *
 * @since 1.0.0
 */
if ( ! defined( 'CACHE_HIVE_PUBLIC_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PUBLIC_CACHE_DIR', CACHE_HIVE_BASE_CACHE_DIR . '/public' );
}

/**
 * The base directory for all private (logged-in user) cache data.
 *
 * @since 1.0.0
 */
if ( ! defined( 'CACHE_HIVE_PRIVATE_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_CACHE_DIR', CACHE_HIVE_BASE_CACHE_DIR . '/private' );
}

/**
 * The primary storage directory for private cache, organized by user hash.
 * This is where the actual cache files are stored.
 *
 * @since 1.0.0
 */
if ( ! defined( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR', CACHE_HIVE_PRIVATE_CACHE_DIR . '/user_cache' );
}

/**
 * The symlink index directory for private cache, organized by URL hash.
 * This directory only contains pointers to the real files for fast URL-based purges.
 *
 * @since 1.0.0
 */
if ( ! defined( 'CACHE_HIVE_PRIVATE_URL_INDEX_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_URL_INDEX_DIR', CACHE_HIVE_PRIVATE_CACHE_DIR . '/url_index' );
}

/**
 * The configuration directory for Cache Hive.
 *
 * @since 1.0.0
 */
if ( ! defined( 'CACHE_HIVE_CONFIG_DIR' ) ) {
	define( 'CACHE_HIVE_CONFIG_DIR', WP_CONTENT_DIR . '/cache-hive-config' );
}


// =========================================================================
// BOOTSTRAP COMPOSER AUTOLOADER & PLUGIN FILES
// =========================================================================
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-settings.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-disk.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-engine.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-purge.php';
require_once CACHE_HIVE_DIR . 'includes/integrations/class-cache-hive-cloudflare.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/class-cache-hive-base-optimizer.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/class-cache-hive-html-optimizer.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/class-cache-hive-css-optimizer.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/class-cache-hive-js-optimizer.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/class-cache-hive-media-optimizer.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-rest-api.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-admin-bar.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-main.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-lifecycle.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-object-cache.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/interface-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-phpredis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-predis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-credis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-memcached-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-array-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-object-cache-factory.php';


// =========================================================================
// PLUGIN LIFECYCLE & INITIALIZATION
// =========================================================================
register_activation_hook( __FILE__, array( Cache_Hive_Lifecycle::class, 'on_activation' ) );
register_deactivation_hook( __FILE__, array( Cache_Hive_Lifecycle::class, 'on_deactivation' ) );

/**
 * Initializes the plugin's core components and hooks.
 * This function ensures all necessary classes are loaded and hooks are registered.
 *
 * @since 1.0.0
 */
function cache_hive_init() {
	if ( class_exists( 'Cache_Hive\Includes\Cache_Hive_Purge' ) ) {
		Cache_Hive_Purge::init();
	}
	// Initialize the admin bar functionality.
	if ( class_exists( 'Cache_Hive\Includes\Cache_Hive_Admin_Bar' ) ) {
		Cache_Hive_Admin_Bar::init();
	}
}
add_action( 'init', 'cache_hive_init' );


/**
 * Begins execution of the main plugin class on plugins_loaded.
 *
 * @since 1.0.0
 * @return Cache_Hive_Main
 */
function cache_hive_run() {
	return Cache_Hive_Main::instance();
}
add_action( 'plugins_loaded', 'cache_hive_run' );


// =========================================================================
// ADMIN UI SETUP
// =========================================================================

/**
 * Adds a settings link to the plugin's action links.
 *
 * @since 1.0.0
 * @param array $links An array of plugin action links.
 * @return array The modified array of links.
 */
function cache_hive_add_settings_link( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=cache-hive' ) . '">' . esc_html__( 'Settings', 'cache-hive' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
}
add_filter( 'plugin_action_links_' . CACHE_HIVE_BASE, 'cache_hive_add_settings_link' );

/**
 * Registers the admin menu pages for Cache Hive.
 *
 * @since 1.0.0
 */
function cache_hive_register_admin_menu() {
	add_menu_page(
		'Cache Hive',
		'Cache Hive',
		'manage_options',
		'cache-hive',
		'cache_hive_render_admin_page',
		'dashicons-performance',
		80
	);
	add_submenu_page(
		'cache-hive',
		'Dashboard',
		'Dashboard',
		'manage_options',
		'cache-hive',
		'cache_hive_render_admin_page'
	);
	add_submenu_page(
		'cache-hive',
		'Caching Settings',
		'Caching',
		'manage_options',
		'cache-hive-caching',
		'cache_hive_render_admin_page'
	);
	add_submenu_page(
		'cache-hive',
		'Page Optimization',
		'Page Optimization',
		'manage_options',
		'cache-hive-optimization',
		'cache_hive_render_admin_page'
	);
	add_submenu_page(
		'cache-hive',
		'Cloudflare',
		'Cloudflare',
		'manage_options',
		'cache-hive-cloudflare',
		'cache_hive_render_admin_page'
	);
}
add_action( 'admin_menu', 'cache_hive_register_admin_menu' );

/**
 * Renders the root div for the React admin application.
 *
 * @since 1.0.0
 */
function cache_hive_render_admin_page() {
	echo '<div id="cache-hive-root"></div>';
}

/**
 * Enqueues scripts and styles for the admin area.
 *
 * @since 1.0.0
 * @param string $hook The current admin page hook.
 */
function cache_hive_enqueue_admin_assets( $hook ) {
	if ( false === strpos( $hook, 'cache-hive' ) ) {
		return;
	}

	$script_path = CACHE_HIVE_DIR . 'build/index.js';
	$style_path  = CACHE_HIVE_DIR . 'build/index.css';

	if ( file_exists( $script_path ) ) {
		wp_enqueue_script(
			'cache-hive-app',
			CACHE_HIVE_URL . 'build/index.js',
			array( 'wp-element', 'wp-i18n' ),
			filemtime( $script_path ),
			true
		);

		wp_localize_script(
			'cache-hive-app',
			'wpApiSettings',
			array(
				'root'  => esc_url_raw( rest_url() ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	if ( file_exists( $style_path ) ) {
		wp_enqueue_style(
			'cache-hive-style',
			CACHE_HIVE_URL . 'build/index.css',
			array(),
			filemtime( $style_path )
		);

		// Ensure the React app can use full width in admin.
		$custom_css = "\n\tbody[class*='_page_cache-hive'] #wpcontent {\n\t\tpadding-left: 0;\n\t}\n\t";
		wp_add_inline_style( 'cache-hive-style', $custom_css );
	}
}
add_action( 'admin_enqueue_scripts', 'cache_hive_enqueue_admin_assets', 100 );
