<?php
/**
 * Plugin Name:       Cache Hive
 * Plugin URI:        https://github.com/realmranshuman/cache-hive
 * Description:       A powerful caching and performance optimization plugin for WordPress.
 * Version:           1.1.0
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
	define( 'CACHE_HIVE_VERSION', '1.1.0' );
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
 * The absolute root directory for all Cache Hive cache files across a multisite network.
 *
 * @since 1.1.0
 */
if ( ! defined( 'CACHE_HIVE_ROOT_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_ROOT_CACHE_DIR', WP_CONTENT_DIR . '/cache/cache-hive' );
}

/**
 * The absolute root directory for all Cache Hive configuration files across a multisite network.
 *
 * @since 1.1.0
 */
if ( ! defined( 'CACHE_HIVE_ROOT_CONFIG_DIR' ) ) {
	define( 'CACHE_HIVE_ROOT_CONFIG_DIR', WP_CONTENT_DIR . '/cache-hive-config' );
}

/**
 * Define site-specific constants immediately.
 * This ensures they are available for activation hooks and all subsequent actions.
 * WordPress functions like is_multisite() are NOT available here, so we must rely on
 * the `get_current_blog_id()` which will be defined if MS is loaded.
 */
$site_path_segment = defined( 'MULTISITE' ) && MULTISITE ? '/' . get_current_blog_id() : '';

$base_cache_dir  = CACHE_HIVE_ROOT_CACHE_DIR . $site_path_segment;
$base_config_dir = CACHE_HIVE_ROOT_CONFIG_DIR . $site_path_segment;

if ( ! defined( 'CACHE_HIVE_BASE_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_BASE_CACHE_DIR', $base_cache_dir ); }
if ( ! defined( 'CACHE_HIVE_PUBLIC_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PUBLIC_CACHE_DIR', $base_cache_dir . '/public' ); }
if ( ! defined( 'CACHE_HIVE_PRIVATE_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_CACHE_DIR', $base_cache_dir . '/private' ); }
if ( ! defined( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_USER_CACHE_DIR', $base_cache_dir . '/private/user_cache' ); }
if ( ! defined( 'CACHE_HIVE_PRIVATE_URL_INDEX_DIR' ) ) {
	define( 'CACHE_HIVE_PRIVATE_URL_INDEX_DIR', $base_cache_dir . '/private/url_index' ); }
if ( ! defined( 'CACHE_HIVE_IMAGE_CACHE_DIR' ) ) {
	define( 'CACHE_HIVE_IMAGE_CACHE_DIR', $base_cache_dir . '/images' ); }
if ( ! defined( 'CACHE_HIVE_CONFIG_DIR' ) ) {
	define( 'CACHE_HIVE_CONFIG_DIR', $base_config_dir ); }


// =========================================================================
// BOOTSTRAP COMPOSER AUTOLOADER & PLUGIN FILES
// =========================================================================
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-lifecycle.php';
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
require_once CACHE_HIVE_DIR . 'includes/class-cache-hive-object-cache.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/interface-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-phpredis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-predis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-redis-credis-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-memcached-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-array-backend.php';
require_once CACHE_HIVE_DIR . 'includes/object-cache/class-cache-hive-object-cache-factory.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/image-optimizer/class-cache-hive-image-stats.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/image-optimizer/class-cache-hive-image-meta.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/image-optimizer/class-cache-hive-image-rewrite.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/image-optimizer/class-cache-hive-image-optimizer.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/image-optimizer/class-cache-hive-image-batch-processor.php';
require_once CACHE_HIVE_DIR . 'includes/optimizers/image-optimizer/class-cache-hive-media-integration.php';

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
add_action( 'plugins_loaded', 'cache_hive_run', 10 );


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
	add_menu_page( 'Cache Hive', 'Cache Hive', 'manage_options', 'cache-hive', 'cache_hive_render_admin_page', 'dashicons-performance', 80 );
	add_submenu_page( 'cache-hive', 'Dashboard', 'Dashboard', 'manage_options', 'cache-hive', 'cache_hive_render_admin_page' );
	add_submenu_page( 'cache-hive', 'Caching Settings', 'Caching', 'manage_options', 'cache-hive-caching', 'cache_hive_render_admin_page' );
	add_submenu_page( 'cache-hive', 'Page Optimization', 'Page Optimization', 'manage_options', 'cache-hive-optimization', 'cache_hive_render_admin_page' );
	add_submenu_page( 'cache-hive', 'Image Optimization', 'Image Optimization', 'manage_options', 'cache-hive-image-optimization', 'cache_hive_render_admin_page' );
	add_submenu_page( 'cache-hive', 'Cloudflare', 'Cloudflare', 'manage_options', 'cache-hive-cloudflare', 'cache_hive_render_admin_page' );
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
/**
 * Enqueues scripts and styles for the admin area.
 *
 * @since 1.0.0
 * @param string $hook The current admin page hook.
 */
function cache_hive_enqueue_admin_assets( $hook ) {
	// Enqueue the main React application script on its pages.
	if ( false !== strpos( $hook, 'cache-hive' ) ) {
		$script_path = CACHE_HIVE_DIR . 'build/index.js';

		if ( file_exists( $script_path ) ) {
			// Enqueue the main JS bundle. CSS is now included within this file.
			wp_enqueue_script( 'cache-hive-app', CACHE_HIVE_URL . 'build/index.js', array( 'wp-element', 'wp-i18n' ), filemtime( $script_path ), true );

			// Localize script with required data.
			wp_localize_script(
				'cache-hive-app',
				'wpApiSettings',
				array(
					'root'  => esc_url_raw( rest_url() ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
				)
			);
		}
	}

	// Enqueue the separate vanilla JS for the Media Library.
	$media_library_hooks = array( 'upload.php', 'post.php' );
	if ( in_array( $hook, $media_library_hooks, true ) ) {
		$media_script_path = CACHE_HIVE_DIR . 'build/media-library.js';

		if ( file_exists( $media_script_path ) ) {
			wp_enqueue_script( 'cache-hive-media-library', CACHE_HIVE_URL . 'build/media-library.js', array(), filemtime( $media_script_path ), true );

			wp_localize_script(
				'cache-hive-media-library',
				'cacheHiveMedia',
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'cache-hive-admin-nonce' ),
					'l10n'    => array(
						'processing' => esc_html__( 'Processing...', 'cache-hive' ),
						'error'      => esc_html__( 'An error occurred.', 'cache-hive' ),
					),
				)
			);
		}
	}
}
add_action( 'admin_enqueue_scripts', 'cache_hive_enqueue_admin_assets', 100 );
