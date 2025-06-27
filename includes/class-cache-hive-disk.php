<?php
/**
 * Class for handling all disk-related operations.
 *
 * @since 1.0.0
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Final class for handling all disk-related operations for Cache Hive.
 *
 * This class is responsible for creating and deleting cache files, managing
 * the wp-config.php constant, and handling the advanced-cache.php drop-in.
 *
 * @since 1.0.0
 */
final class Cache_Hive_Disk {

	/**
	 * Setup environment: create advanced-cache.php and set WP_CACHE constant.
	 *
	 * @since 1.0.0
	 */
	public static function setup_environment() {
		self::create_advanced_cache_file();
		self::set_wp_cache_constant( true );
	}

	/**
	 * Cleanup environment: remove advanced-cache.php and unset WP_CACHE constant.
	 *
	 * @since 1.0.0
	 */
	public static function cleanup_environment() {
		if ( file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			@unlink( WP_CONTENT_DIR . '/advanced-cache.php' );
		}
		self::set_wp_cache_constant( false );
		self::delete_config_file();
	}

	/**
	 * Creates the advanced-cache.php file in wp-content.
	 *
	 * @since 1.0.0
	 * @return bool Success or failure.
	 */
	public static function create_advanced_cache_file() {
		if ( ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}
		$advanced_cache_source_file      = CACHE_HIVE_DIR . 'class-cache-hive-advanced-cache.php';
		$advanced_cache_destination_file = WP_CONTENT_DIR . '/advanced-cache.php';
		if ( ! is_readable( $advanced_cache_source_file ) ) {
			return false;
		}
		return copy( $advanced_cache_source_file, $advanced_cache_destination_file );
	}

	/**
	 * Sets or unsets the WP_CACHE constant in wp-config.php.
	 *
	 * @since 1.0.0
	 * @param bool $enable True to set the constant, false to remove.
	 */
	private static function set_wp_cache_constant( $enable = true ) {
		$config_path = self::find_wp_config_path();
		if ( ! $config_path || ! is_writable( $config_path ) ) {
			return;
		}

		$config_content = file_get_contents( $config_path );
		$define_string  = "define( 'WP_CACHE', true ); // Added by Cache Hive.";

		$config_content = preg_replace( "/^[\t\s]*define\s*\(\s*['\"]WP_CACHE['\"]\s*,\s*.*\s*\);.*?\R/mi", '', $config_content );

		if ( $enable ) {
			$config_content = preg_replace( '/(<\?php)/', "<?php\n" . $define_string, $config_content, 1 );
		}

		file_put_contents( $config_path, $config_content, LOCK_EX );
	}

	/**
	 * Create the config file with current settings for advanced-cache.php to read.
	 *
	 * @since 1.0.0
	 * @param array $settings The settings array.
	 */
	public static function create_config_file( $settings ) {
		if ( ! is_dir( CACHE_HIVE_CONFIG_DIR ) ) {
			@mkdir( CACHE_HIVE_CONFIG_DIR, 0755, true );
		}

		$config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
		$contents    = '<?php return ' . var_export( $settings, true ) . ';';
		file_put_contents( $config_file, $contents, LOCK_EX );
	}

	/**
	 * Deletes the config file.
	 *
	 * @since 1.0.0
	 */
	public static function delete_config_file() {
		$config_file = CACHE_HIVE_CONFIG_DIR . '/config.php';
		if ( file_exists( $config_file ) ) {
			@unlink( $config_file );
		}
		if ( is_dir( CACHE_HIVE_CONFIG_DIR ) ) {
			@rmdir( CACHE_HIVE_CONFIG_DIR );
		}
	}

	/**
	 * Get the full path to the cache file for the current request.
	 *
	 * @since 1.0.0
	 * @return string The cache file path.
	 */
	public static function get_cache_file_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? strtok( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ), '?' ) : '';
		$uri = rtrim( $uri, '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__';
		}

		$host     = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) : '';
		$dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

		// For logged-in users, add a hashed user folder. This maintains the subdirectory structure.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			$settings = Cache_Hive_Settings::get_settings();
			if ( ! empty( $settings['cacheLoggedUsers'] ) ) {
				$user = wp_get_current_user();
				// SOLID FIX: Use the stable User ID for hashing.
				if ( $user && $user->ID ) {
					$auth_key  = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'cachehive';
					$user_hash = 'user_' . md5( $user->ID . $auth_key );
					$dir_path .= '/' . $user_hash;
				}
			}
		}

		// Use different filenames for mobile and desktop cache.
		$file_name = ( method_exists( 'Cache_Hive_Engine', 'is_mobile' ) && Cache_Hive_Engine::is_mobile() ) ? 'index-mobile.html' : 'index.html';
		return $dir_path . '/' . $file_name;
	}

	/**
	 * Creates a static HTML file and its metadata file.
	 *
	 * @since 1.0.0
	 * @param string $buffer The page content to cache.
	 */
	public static function cache_page( $buffer ) {
		$cache_file = self::get_cache_file_path();
		$meta_file  = $cache_file . '.meta';
		$cache_dir  = dirname( $cache_file );

		if ( ! is_dir( $cache_dir ) ) {
			if ( ! @mkdir( $cache_dir, 0755, true ) ) {
				error_log( "[Cache Hive] Failed to create cache directory: {$cache_dir}" );
				return;
			}
		}

		$cache_created = file_put_contents( $cache_file, $buffer . self::get_cache_signature(), LOCK_EX );

		if ( $cache_created ) {
			if ( false !== strpos( $cache_file, '/user_' ) ) {
				$settings = Cache_Hive_Settings::get_settings();
				$ttl      = $settings['privateCacheTTL'] ?? 1800;
			} else {
				$ttl = self::get_current_page_ttl();
			}

			$http_host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$url         = ( isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http' ) . '://' . $http_host . $request_uri;

			$meta_data = array(
				'created' => time(),
				'ttl'     => (int) $ttl,
				'url'     => $url,
			);
			file_put_contents( $meta_file, json_encode( $meta_data ), LOCK_EX );
		}
	}

	/**
	 * Checks if a cache file is valid (exists and is not expired).
	 *
	 * @since 1.0.0
	 * @param string $cache_file The full path to the cache file.
	 * @return bool
	 */
	public static function is_cache_valid( $cache_file ) {
		$meta_file = $cache_file . '.meta';

		if ( ! @is_readable( $cache_file ) || ! @is_readable( $meta_file ) ) {
			return false;
		}

		$meta_data_json = @file_get_contents( $meta_file );
		if ( ! $meta_data_json ) {
			return false;
		}

		$meta_data = json_decode( $meta_data_json, true );

		if ( empty( $meta_data['created'] ) || ! isset( $meta_data['ttl'] ) ) {
			return false;
		}

		if ( 0 === (int) $meta_data['ttl'] ) {
			return true;
		}

		return ( $meta_data['created'] + (int) $meta_data['ttl'] ) > time();
	}

	/**
	 * Purges the entire page cache directory.
	 *
	 * @since 1.0.0
	 */
	public static function purge_all() {
		if ( is_dir( CACHE_HIVE_CACHE_DIR ) ) {
			self::delete_directory( CACHE_HIVE_CACHE_DIR );
		}
	}

	/**
	 * Purges a single URL. This now correctly purges the subdirectory containing both mobile and desktop files.
	 *
	 * @since 1.0.0
	 * @param string $url The URL to purge.
	 */
	public static function purge_url( $url ) {
		$url_parts = wp_parse_url( $url );
		if ( empty( $url_parts['path'] ) ) {
			return;
		}

		$uri = rtrim( $url_parts['path'], '/' );
		if ( empty( $uri ) ) {
			$uri = '/__index__';
		}

		$host     = strtolower( $url_parts['host'] );
		$dir_path = CACHE_HIVE_CACHE_DIR . '/' . $host . $uri;

		if ( is_dir( $dir_path ) ) {
			self::delete_directory( $dir_path );
		}
	}

	/**
	 * Purges all private user cache directories.
	 *
	 * @since 1.0.0
	 */
	public static function purge_all_private() {
		/* ... unchanged ... */ }

	/**
	 * Purges the private cache for a specific URL.
	 *
	 * @since 1.0.0
	 * @param string $url The URL to purge private cache for.
	 */
	public static function purge_private_url( $url ) {
		/* ... unchanged ... */ }

	/**
	 * Recursively deletes a directory and its contents.
	 *
	 * @since 1.0.0
	 * @param string $dir The directory path to delete.
	 */
	private static function delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return;
		} $it  = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
		$files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				@rmdir( $file->getRealPath() );
			} else {
				@unlink( $file->getRealPath() );
			}
		} @rmdir( $dir ); }

	/**
	 * Gets the TTL (time to live) for the current page based on context.
	 *
	 * @since 1.0.0
	 * @return int TTL in seconds.
	 */
	private static function get_current_page_ttl() {
		$settings = Cache_Hive_Settings::get_settings();
		if ( is_front_page() || is_home() ) {
			return $settings['frontPageTTL'];
		} if ( is_feed() ) {
			return $settings['feedTTL'];
		} if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $settings['restTTL'];
		} if ( is_user_logged_in() ) {
			return $settings['privateCacheTTL'];
		} return $settings['publicCacheTTL']; }

	/**
	 * Returns the cache signature comment appended to cached files.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	private static function get_cache_signature() {
		return '<!-- Cache served by Cache Hive on ' . gmdate( 'Y-m-d H:i:s' ) . ' -->'; }

	/**
	 * Finds the path to wp-config.php.
	 *
	 * @since 1.0.0
	 * @return string|false Path to wp-config.php or false if not found.
	 */
	private static function find_wp_config_path() {
		if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		} if ( file_exists( dirname( ABSPATH ) . '/wp-config.php' ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		} return false; }

	/**
	 * Registers hooks for purging private cache on user logout.
	 *
	 * @since 1.0.0
	 */
	public static function register_hooks() {
		add_action( 'wp_logout', array( __CLASS__, 'purge_current_user_private_cache' ) ); }

	/**
	 * Purges the private cache for the current user on logout.
	 *
	 * @since 1.0.0
	 */
	public static function purge_current_user_private_cache() {
		if ( function_exists( 'wp_get_current_user' ) ) {
			$user = wp_get_current_user();
			if ( $user && $user->ID ) {
				self::purge_user_private_cache( $user->ID ); }
		} }

	/**
	 * Purges the private cache for a specific user ID.
	 *
	 * @since 1.0.0
	 * @param int $user_id The user ID whose private cache should be purged.
	 */
	public static function purge_user_private_cache( $user_id ) {
	}
}
