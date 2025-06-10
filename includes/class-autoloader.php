<?php
/**
 * Autoloader Class
 *
 * Handles the autoloading of all plugin classes using spl_autoload_register.
 *
 * @package CacheHive
 */

namespace CacheHive\Includes;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Spl_autoload_register implementation for CacheHive.
 *
 * Handles loading all classes of the plugin.
 */
class Autoloader {

	/**
	 * Constructor.
	 *
	 * Registers the autoloader callback with spl_autoload_register.
	 */
	public function __construct() {
		spl_autoload_register( array( $this, 'load' ) );
	}

	/**
	 * The autoloader logic.
	 *
	 * @param string $class_name The fully-qualified name of the class to load.
	 */
	public function load( $class_name ) {
		// Only autoload classes from this plugin's namespace.
		if ( false === strpos( $class_name, 'CacheHive\\' ) ) {
			return;
		}

		// Remove the root namespace.
		$class_path = str_replace( 'CacheHive\\Includes\\', '', $class_name );

		// Split the class name into parts.
		$class_parts = explode( '\\', $class_path );
		$class_file  = array_pop( $class_parts );

		// Convert class name to file name (e.g., Admin_Menu -> class-admin-menu.php).
		$class_file = 'class-' . str_replace( '_', '-', strtolower( $class_file ) ) . '.php';

		// Build the path with the remaining parts (subdirectories).
		$path = CACHEHIVE_PLUGIN_DIR . 'includes/';
		if ( ! empty( $class_parts ) ) {
			$path .= strtolower( implode( '/', $class_parts ) ) . '/';
		}

		$file = $path . $class_file;

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
