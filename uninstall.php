<?php
/**
 * Cache Hive Uninstall
 *
 * This file is triggered when the user uninstalls the plugin.
 * It ensures all plugin data, settings, and files are completely removed.
 *
 * @since 1.0.0
 * @package CacheHive
 */

// Exit if accessed directly and not during an uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Define constants needed by the classes.
define( 'CACHE_HIVE_CACHE_DIR', WP_CONTENT_DIR . '/cache/cache-hive' );
define( 'CACHE_HIVE_CONFIG_DIR', WP_CONTENT_DIR . '/cache-hive-config' );

// Load the necessary class files to perform the uninstall.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cache-hive-settings.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cache-hive-disk.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cache-hive-purge.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-cache-hive-lifecycle.php';

// Trigger the static uninstall method.
Cache_Hive_Lifecycle::on_uninstall();
