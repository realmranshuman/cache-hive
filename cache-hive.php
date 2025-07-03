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

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// =========================================================================
// PLUGIN CONSTANTS & VITE CONFIG
// =========================================================================
define( 'CACHE_HIVE_VERSION', '1.0.0' );
define( 'CACHE_HIVE_FILE', __FILE__ );
define( 'CACHE_HIVE_BASE', plugin_basename( CACHE_HIVE_FILE ) );
define( 'CACHE_HIVE_DIR', plugin_dir_path( CACHE_HIVE_FILE ) );
define( 'CACHE_HIVE_URL', plugin_dir_url( CACHE_HIVE_FILE ) );
define( 'CACHE_HIVE_CACHE_DIR', WP_CONTENT_DIR . '/cache/cache-hive' );
define( 'CACHE_HIVE_CONFIG_DIR', WP_CONTENT_DIR . '/cache-hive-config' );
define( 'CACHE_HIVE_VITE_DEV_SERVER', 'http://localhost:5173' );
define( 'CACHE_HIVE_VITE_ENTRY_POINT', 'src/index.tsx' );

// =========================================================================
// BOOTSTRAP COMPOSER AUTOLOADER
// =========================================================================
if ( file_exists( CACHE_HIVE_DIR . 'vendor/autoload.php' ) ) {
	require_once CACHE_HIVE_DIR . 'vendor/autoload.php';
}

// =========================================================================
// PLUGIN LIFECYCLE HOOKS
// =========================================================================
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-lifecycle.php';
register_activation_hook( __FILE__, array( 'Cache_Hive_Lifecycle', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Cache_Hive_Lifecycle', 'on_deactivation' ) );

// =========================================================================
// REQUIRE PLUGIN FILES
// =========================================================================
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
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-main.php';
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-object-cache.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/interface-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-phpredis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-predis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-credis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-memcached-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-array-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-object-cache-factory.php';

// Register hooks for cache purging on logout.
if ( class_exists( 'Cache_Hive_Disk' ) ) {
	Cache_Hive_Purge::register_hooks();
}

// =========================================================================
// ADMIN UI SETUP
// =========================================================================

add_action(
	'admin_menu',
	function () {
		add_menu_page(
			'Cache Hive',
			'Cache Hive',
			'manage_options',
			'cache-hive',
			'cache_hive_admin_page',
			'dashicons-performance',
			80
		);
		add_submenu_page(
			'cache-hive',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'cache-hive',
			'cache_hive_admin_page'
		);
		add_submenu_page(
			'cache-hive',
			'Caching Settings',
			'Caching',
			'manage_options',
			'cache-hive-caching',
			'cache_hive_admin_page'
		);
		add_submenu_page(
			'cache-hive',
			'Page Optimization',
			'Page Optimization',
			'manage_options',
			'cache-hive-optimization',
			'cache_hive_admin_page'
		);
		add_submenu_page(
			'cache-hive',
			'Cloudflare',
			'Cloudflare',
			'manage_options',
			'cache-hive-cloudflare',
			'cache_hive_admin_page'
		);
	}
);

/**
 * Renders the root div for the React admin application.
 *
 * @since 1.0.0
 */
function cache_hive_admin_page() {
	echo '<div id="cache-hive-root"></div>';
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only load assets on Cache Hive admin pages.
		if ( false === strpos( $hook, 'cache-hive' ) ) {
			return;
		}

		// Always enqueue the built JS file.
		$plugin_url   = plugin_dir_url( __FILE__ );
		$js_file_path = plugin_dir_path( __FILE__ ) . 'build/index.js';
		if ( file_exists( $js_file_path ) ) {
			wp_enqueue_script(
				'cache-hive-app',
				$plugin_url . 'build/index.js',
				array( 'wp-element' ),
				filemtime( $js_file_path ),
				true
			);
			// Localize REST API settings for JS.
			wp_localize_script(
				'cache-hive-app',
				'wpApiSettings',
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		$css_file_path = plugin_dir_path( __FILE__ ) . 'build/index.css';
		if ( file_exists( $css_file_path ) ) {
			wp_enqueue_style(
				'cache-hive-style',
				$plugin_url . 'build/index.css',
				array(),
				filemtime( $css_file_path )
			);

			// This ensures the React app can go full-width.
			$custom_css = "
            body[class*='_page_cache-hive'] #wpcontent {
                padding-left: 0;
            }
        ";
			wp_add_inline_style( 'cache-hive-style', $custom_css );
		}
	},
	100
);

/**
 * Begins execution of the plugin.
 *
 * @since 1.0.0
 */
function cache_hive_run() {
	return Cache_Hive_Main::instance();
}

// Let's get this party started.
cache_hive_run();
