<?php
/**
 * Cache Hive Uninstall
 *
 * This file is triggered when the user uninstalls the plugin from the WordPress dashboard.
 * It runs in a standalone environment, so it must define its own constants and include all necessary files.
 * It ensures all plugin data, settings, and files are completely removed.
 *
 * @since 1.0.0
 * @package CacheHive
 */

// Exit if accessed directly and not during an uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// --- Import Namespaced Classes ---
// By using the `use` keyword, we can refer to the class by its short name.
use Cache_Hive\Includes\Cache_Hive_Lifecycle;

// --- Define Constants ---
// These must be defined here because the main plugin file is not loaded during uninstall.
// They must match the constants used in the classes we are about to load.
define( 'CACHE_HIVE_ROOT_CACHE_DIR', WP_CONTENT_DIR . '/cache/cache-hive' );
define( 'CACHE_HIVE_ROOT_CONFIG_DIR', WP_CONTENT_DIR . '/cache-hive-config' );

// It's good practice to define this for any functions that might use it, even if just checking existence.
if ( ! defined( 'CACHE_HIVE_DIR' ) ) {
	define( 'CACHE_HIVE_DIR', __DIR__ . '/' );
}


// --- Load All Necessary Class Files ---
// We must manually include every class file that the on_uninstall() method and its sub-methods will use.
require_once __DIR__ . '/includes/class-cache-hive-settings.php';
require_once __DIR__ . '/includes/class-cache-hive-lifecycle.php';
require_once __DIR__ . '/includes/helpers/class-cache-hive-server-rules-helper.php';
require_once __DIR__ . '/includes/class-cache-hive-purge.php';
require_once __DIR__ . '/includes/class-cache-hive-object-cache.php';


// --- Trigger the Uninstall Process ---
// Now we can call the method on the imported class directly.
Cache_Hive_Lifecycle::on_uninstall();
