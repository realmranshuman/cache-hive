<?php
/**
 * Handles reading the Vite manifest files for proper asset enqueueing.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Hive_Vite_Manifest
 *
 * This class is responsible for locating and parsing the Vite manifest files
 * to correctly enqueue versioned, code-split JavaScript and CSS assets.
 */
final class Cache_Hive_Vite_Manifest {

	/**
	 * An array to hold the parsed manifest data to avoid redundant file reads.
	 *
	 * @var array
	 */
	private static $manifests = array();

	/**
	 * Loads and parses a specific manifest file.
	 *
	 * @param string $manifest_name The name of the manifest file (e.g., 'manifest-app.json').
	 * @return array|null The parsed manifest data or null on failure.
	 */
	private static function get_manifest( $manifest_name ) {
		if ( isset( self::$manifests[ $manifest_name ] ) ) {
			return self::$manifests[ $manifest_name ];
		}

		$manifest_path = CACHE_HIVE_DIR . 'build/' . $manifest_name;
		if ( ! file_exists( $manifest_path ) ) {
			self::$manifests[ $manifest_name ] = null;
			return null;
		}

		$manifest_content                  = file_get_contents( $manifest_path );
		self::$manifests[ $manifest_name ] = json_decode( $manifest_content, true );

		return self::$manifests[ $manifest_name ];
	}

	/**
	 * Enqueues the assets for a given entry point from the Vite manifest.
	 *
	 * @param string $entry         The manifest key for the entry point (e.g., 'src/index.tsx').
	 * @param string $manifest_name The name of the manifest file to use.
	 * @param array  $deps          Optional. An array of script dependencies.
	 */
	public static function enqueue_assets( $entry, $manifest_name, $deps = array() ) {
		$manifest = self::get_manifest( $manifest_name );

		if ( is_null( $manifest ) || ! isset( $manifest[ $entry ] ) ) {
			return;
		}

		$entry_data  = $manifest[ $entry ];
		$handle_base = 'cache-hive-' . basename( $entry, '.tsx' );

		// Enqueue any associated CSS files for the main entry.
		if ( isset( $entry_data['css'] ) && is_array( $entry_data['css'] ) ) {
			foreach ( $entry_data['css'] as $index => $css_file ) {
				wp_enqueue_style(
					$handle_base . '-css-' . $index,
					CACHE_HIVE_URL . 'build/' . $css_file,
					array(),
					null
				);
			}
		}

		// Enqueue imported vendor chunks.
		if ( isset( $entry_data['imports'] ) && is_array( $entry_data['imports'] ) ) {
			foreach ( $entry_data['imports'] as $import_key ) {
				if ( isset( $manifest[ $import_key ] ) ) {
					$import_data   = $manifest[ $import_key ];
					$import_handle = 'cache-hive-' . basename( $import_data['file'], '.js' );

					wp_enqueue_script(
						$import_handle,
						CACHE_HIVE_URL . 'build/' . $import_data['file'],
						$deps,
						null,
						true
					);

					// Add the vendor chunk handle to the main script's dependencies.
					$deps[] = $import_handle;
				}
			}
		}

		// Enqueue the main entry script with all its dependencies.
		wp_enqueue_script(
			$handle_base,
			CACHE_HIVE_URL . 'build/' . $entry_data['file'],
			$deps,
			null,
			true
		);
	}
}
