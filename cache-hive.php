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
use Cache_Hive\Includes\Cache_Hive_Vite_Manifest;

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

require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-vite-manifest.php';
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
 * Enqueues scripts and styles for the admin area using the Vite manifest.
 *
 * @since 1.0.0
 * @param string $hook The current admin page hook.
 */
function cache_hive_enqueue_admin_assets( $hook ) {
	// Always load the self-contained toolbar script for any logged-in user in the admin area.
	if ( is_user_logged_in() ) {
		Cache_Hive_Vite_Manifest::enqueue_assets( 'src/toolbar.tsx', 'manifest-toolbar.json', array( 'wp-element', 'wp-i18n' ) );
		wp_localize_script(
			'cache-hive-toolbar',
			'chToolbar',
			array(
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'root'     => esc_url_raw( rest_url() ),
				'page_url' => admin_url(), // For "Purge this Page" on admin pages.
			)
		);
	}

	// Only load the main application assets on the plugin's own pages.
	if ( false === strpos( $hook, 'cache-hive' ) ) {
		return;
	}

	Cache_Hive_Vite_Manifest::enqueue_assets( 'src/index.tsx', 'manifest-app.json', array( 'wp-element', 'wp-i18n' ) );

	wp_localize_script(
		'cache-hive-index',
		'wpApiSettings',
		array(
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		)
	);

	$stylesheet_handle = 'cache-hive-index-css-0';
	$custom_css        = "\n\tbody[class*='_page_cache-hive'] #wpcontent {\n\t\tpadding-left: 0;\n\t}\n\t";
	wp_add_inline_style( $stylesheet_handle, $custom_css );
}
add_action( 'admin_enqueue_scripts', 'cache_hive_enqueue_admin_assets', 100 );

/**
 * Enqueues scripts for the frontend using the Vite manifest.
 *
 * @since 1.0.0
 */
function cache_hive_enqueue_frontend_assets() {
	if ( ! is_user_logged_in() || is_admin() ) {
		return;
	}

	Cache_Hive_Vite_Manifest::enqueue_assets( 'src/toolbar.tsx', 'manifest-toolbar.json', array( 'wp-element', 'wp-i18n' ) );

	wp_localize_script(
		'cache-hive-toolbar',
		'chToolbar',
		array(
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'root'     => esc_url_raw( rest_url() ),
			'page_url' => is_singular() ? get_permalink() : home_url( add_query_arg( null, null ) ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'cache_hive_enqueue_frontend_assets' );


/**
 * Adds `type="module"` to all plugin script tags.
 *
 * @param string $tag    The original <script> tag.
 * @param string $handle The script's handle.
 * @return string The modified <script> tag.
 */
function cache_hive_add_module_type_attribute( $tag, $handle ) {
	if ( str_starts_with( $handle, 'cache-hive-' ) ) {
		$tag = str_replace( '<script ', '<script type="module" ', $tag );
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'cache_hive_add_module_type_attribute', 10, 2 );

/**
 * Adds Cache Hive menu items to the WordPress admin bar.
 *
 * @since 1.0.0
 * @param \WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
 */
function cache_hive_add_toolbar_items( $wp_admin_bar ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	// Top-level menu with icon.
	$wp_admin_bar->add_node(
		array(
			'id'    => 'cache-hive',
			'title' => '<span class="ab-icon dashicons dashicons-performance"></span><span class="ab-label">Cache Hive</span>',
			'href'  => '#',
			'meta'  => array( 'title' => 'Cache Hive' ),
		)
	);

	// Available to all users.
	$wp_admin_bar->add_node(
		array(
			'id'     => 'cache-hive-private-cache',
			'parent' => 'cache-hive',
			'title'  => 'Purge My Private Cache',
			'href'   => '#',
		)
	);

	// Admin-only actions.
	if ( current_user_can( 'manage_options' ) ) {

		// This action should only be available on the frontend, not in the admin dashboard.
		if ( ! is_admin() ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'cache-hive-purge-page',
					'parent' => 'cache-hive',
					'title'  => "Purge This Page's Cache",
					'href'   => '#',
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-disk',
				'parent' => 'cache-hive',
				'title'  => 'Purge Disk Cache',
				'href'   => '#',
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-object-cache',
				'parent' => 'cache-hive',
				'title'  => 'Purge Object Cache',
				'href'   => '#',
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-cloudflare',
				'parent' => 'cache-hive',
				'title'  => 'Purge Cloudflare Cache',
				'href'   => '#',
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-all',
				'parent' => 'cache-hive',
				'title'  => 'Purge All Caches',
				'href'   => '#',
			)
		);
	}
}
add_action( 'admin_bar_menu', 'cache_hive_add_toolbar_items', 100 );
