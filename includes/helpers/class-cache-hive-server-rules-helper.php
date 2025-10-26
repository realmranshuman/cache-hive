<?php
/**
 * A helper class for generating and managing server-specific configuration rules (e.g., .htaccess, nginx.conf).
 *
 * @package Cache_Hive
 * @since 1.2.0
 */

namespace Cache_Hive\Includes\Helpers;

use Cache_Hive\Includes\Cache_Hive_Settings;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages server rule generation and file writing.
 */
final class Cache_Hive_Server_Rules_Helper {

	/**
	 * Detect the server software (apache, litespeed, nginx, unknown).
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public static function get_server_software(): string {
		if ( defined( 'WP_CLI' ) && WP_CLI && function_exists( 'WP_CLI::get_runner' ) ) {
			$server_flag = WP_CLI::get_runner()->assoc_args['server'] ?? '';
			if ( ! empty( $server_flag ) && in_array( $server_flag, array( 'apache', 'litespeed', 'nginx' ), true ) ) {
				return $server_flag;
			}
		}

		$software = '';
		if ( isset( $_SERVER['SERVER_SOFTWARE'] ) ) {
			$software = strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) );
		}

		if ( str_contains( $software, 'apache' ) ) {
			return 'apache';
		}
		if ( str_contains( $software, 'litespeed' ) ) {
			return 'litespeed';
		}
		if ( str_contains( $software, 'nginx' ) ) {
			return 'nginx';
		}
		return 'unknown';
	}

	/**
	 * Master function to generate and save the complete nginx.conf file.
	 *
	 * @since 1.2.0
	 * @return bool True on success, false on failure.
	 */
	public static function update_nginx_file() {
		$nginx_conf_path = ABSPATH . 'cache-hive-nginx.conf';
		$settings        = Cache_Hive_Settings::get_settings( true ); // Force refresh to get latest.
		$rules           = array();

		// 1. Add Security Rules (unconditional for Nginx).
		$rules[] = self::get_security_nginx_rules();

		// 2. Add Browser Cache Rules (conditional).
		$browser_cache_rules = self::generate_browser_cache_nginx_rules( $settings );
		if ( ! empty( $browser_cache_rules ) ) {
			$rules[] = "# BEGIN Cache Hive Browser Cache\n" . $browser_cache_rules . "# END Cache Hive Browser Cache\n";
		}

		// 3. Add Image Rewrite Rules (conditional).
		$image_rewrite_rules = self::generate_image_rewrite_nginx_rules( $settings );
		if ( ! empty( $image_rewrite_rules ) ) {
			$rules[] = "# BEGIN Cache Hive Image Optimizer\n" . $image_rewrite_rules . "# END Cache Hive Image Optimizer\n";
		}

		$file_content = implode( "\n\n", array_filter( $rules ) );

		// Attempt to write the file and check if the operation was successful.
		$result = file_put_contents( $nginx_conf_path, $file_content, LOCK_EX );

		// file_put_contents returns false on failure.
		if ( false === $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Master function to update the root .htaccess file with all Cache Hive rules.
	 *
	 * @since 1.3.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function update_root_htaccess() {
		$htaccess_path = trailingslashit( get_home_path() ) . '.htaccess';
		$settings      = Cache_Hive_Settings::get_settings( true );
		$rules         = array();

		// Generate rules for Browser Caching.
		$browser_cache_rules = self::generate_browser_cache_htaccess_rules( $settings );
		if ( ! empty( $browser_cache_rules ) ) {
			$rules[] = '# BEGIN Cache Hive Browser Cache';
			$rules[] = $browser_cache_rules;
			$rules[] = '# END Cache Hive Browser Cache';
		}

		// Generate rules for Image Rewriting.
		$image_rewrite_rules = self::generate_image_rewrite_htaccess_rules( $settings );
		if ( ! empty( $image_rewrite_rules ) ) {
			$rules[] = '# BEGIN Cache Hive Image Optimizer';
			$rules[] = $image_rewrite_rules;
			$rules[] = '# END Cache Hive Image Optimizer';
		}

		return self::update_htaccess( $htaccess_path, 'Cache Hive', $rules );
	}

	/**
	 * Removes the Cache Hive block from the root .htaccess file.
	 *
	 * @since 1.3.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function remove_root_htaccess_rules() {
		$htaccess_path = trailingslashit( get_home_path() ) . '.htaccess';
		return self::update_htaccess( $htaccess_path, 'Cache Hive', array() );
	}


	/**
	 * Deletes the nginx.conf file.
	 *
	 * @since 1.2.0
	 */
	public static function delete_nginx_file() {
		$nginx_conf_path = ABSPATH . 'cache-hive-nginx.conf';
		if ( file_exists( $nginx_conf_path ) ) {
			unlink( $nginx_conf_path );
		}
	}

	/**
	 * Generate .htaccess rules for browser caching.
	 *
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_browser_cache_htaccess_rules( array $settings ): string {
		$ttl = 0;
		if ( isset( $settings['browser_cache_ttl'] ) ) {
			$ttl = absint( $settings['browser_cache_ttl'] );
		}

		$is_enabled = false;
		if ( isset( $settings['browser_cache_enabled'] ) ) {
			$is_enabled = (bool) $settings['browser_cache_enabled'];
		}

		if ( ! $is_enabled || $ttl <= 0 ) {
			return '';
		}

		$types = array( 'image/jpg', 'image/jpeg', 'image/gif', 'image/png', 'image/svg+xml', 'text/css', 'text/javascript', 'application/javascript', 'application/x-javascript', 'application/font-woff2', 'application/font-woff', 'application/vnd.ms-fontobject', 'font/ttf', 'font/otf', 'application/pdf' );

		$block  = "<IfModule mod_expires.c>\n";
		$block .= "    ExpiresActive On\n";
		foreach ( $types as $type ) {
			$block .= "    ExpiresByType {$type} \"access plus {$ttl} seconds\"\n";
		}
		$block .= "</IfModule>\n";

		return $block;
	}

	/**
	 * Generate nginx rules for browser caching.
	 *
	 * @param array $settings The plugin settings array.
	 * @return string
	 */
	public static function generate_browser_cache_nginx_rules( array $settings ): string {
		$ttl = 0;
		if ( isset( $settings['browser_cache_ttl'] ) ) {
			$ttl = absint( $settings['browser_cache_ttl'] );
		}

		$is_enabled = false;
		if ( isset( $settings['browser_cache_enabled'] ) ) {
			$is_enabled = (bool) $settings['browser_cache_enabled'];
		}

		if ( ! $is_enabled || $ttl <= 0 ) {
			return '';
		}

		$nginx_ttl = '';
		if ( 0 === $ttl % 31536000 ) {
			$nginx_ttl = ( $ttl / 31536000 ) . 'y';
		} elseif ( 0 === $ttl % 2592000 ) {
			$nginx_ttl = ( $ttl / 2592000 ) . 'M';
		} elseif ( 0 === $ttl % 604800 ) {
			$nginx_ttl = ( $ttl / 604800 ) . 'w';
		} elseif ( 0 === $ttl % 86400 ) {
			$nginx_ttl = ( $ttl / 86400 ) . 'd';
		} elseif ( 0 === $ttl % 3600 ) {
			$nginx_ttl = ( $ttl / 3600 ) . 'h';
		} elseif ( 0 === $ttl % 60 ) {
			$nginx_ttl = ( $ttl / 60 ) . 'm';
		} else {
			$nginx_ttl = $ttl . 's';
		}

		$block  = "location ~* \.(jpg|jpeg|gif|png|css|js|woff2?|ttf|otf|eot|svg|pdf)$ {\n";
		$block .= "    expires {$nginx_ttl};\n";
		$block .= "    add_header Cache-Control \"public\";\n";
		$block .= "}\n";
		return $block;
	}

	/**
	 * Generates the .htaccess rules to block direct web access to a directory.
	 *
	 * @return string The security rules.
	 */
	public static function get_security_htaccess_rules(): string {
		$rules  = "<IfModule mod_authz_core.c>\n";
		$rules .= "    Require all denied\n";
		$rules .= "</IfModule>\n";
		$rules .= "<IfModule !mod_authz_core.c>\n";
		$rules .= "    Deny from all\n";
		$rules .= "</IfModule>\n";
		return $rules;
	}

	/**
	 * Generates the Nginx rules to block direct web access to the private cache directory.
	 *
	 * @return string The security rules.
	 */
	public static function get_security_nginx_rules(): string {
		$relative_path = str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( CACHE_HIVE_ROOT_CACHE_DIR ) );

		$rules = "# BEGIN Cache Hive Security\n\n";

		$rules .= "# Rule 1: Allow specific asset types (.css, .js) from the private directory to be passed to the backend.\n";
		$rules .= "# This is necessary for logged-in user caching to function with styled content. The backend (WordPress)\n";
		$rules .= "# is responsible for authenticating the user before serving the file.\n";
		$rules .= "# IMPORTANT: For this to work, this location block must be processed before any generic static file location block.\n";
		$rules .= "location ~* ^/{$relative_path}/private/.*\\.(css|js)$ {\n";
		$rules .= "    # This rule assumes a reverse-proxy setup where requests are passed to a backend PHP server.\n";
		$rules .= "    # In a standard setup (like PHP-FPM), you would use 'try_files \$uri /index.php?\$args;'\n";
		$rules .= "    # The proxy_pass directive and headers should match the rest of your configuration.\n";
		$rules .= "    proxy_pass http://127.0.0.1:8080; # This target may need adjustment for other environments.\n";
		$rules .= "    proxy_set_header Host \$host;\n";
		$rules .= "    proxy_set_header X-Forwarded-Host \$host;\n";
		$rules .= "    proxy_set_header X-Real-IP \$remote_addr;\n";
		$rules .= "    proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;\n";
		$rules .= "    proxy_redirect off;\n";
		$rules .= "}\n\n";

		$rules .= "# Rule 2: Block all other direct access to the private cache directory.\n";
		$rules .= "# This acts as a default safety net, preventing direct download of sensitive .cache or .meta files.\n";
		$rules .= "location ~* /{$relative_path}/private/ {\n";
		$rules .= "    deny all;\n";
		$rules .= "}\n\n";

		$rules .= "# END Cache Hive Security\n";

		return $rules;
	}

	/**
	 * Generates .htaccess rewrite rules for next-gen images.
	 *
	 * @param array $settings The plugin settings.
	 * @return string The .htaccess rule block.
	 */
	public static function generate_image_rewrite_htaccess_rules( array $settings ): string {
		$delivery_method = $settings['image_delivery_method'] ?? 'picture';
		if ( 'rewrite' !== $delivery_method ) {
			return '';
		}

		$next_gen_format = $settings['image_next_gen_format'] ?? 'webp';
		$mime_type       = 'image/' . $next_gen_format;

		$rules  = "<IfModule mod_rewrite.c>\n";
		$rules .= "    RewriteEngine On\n\n";
		$rules .= '    # Serve ' . strtoupper( $next_gen_format ) . ' image if browser supports it and a ' . $next_gen_format . " version exists.\n";
		$rules .= '    RewriteCond %{HTTP_ACCEPT} ' . $mime_type . "\n";
		$rules .= '    RewriteCond %{REQUEST_FILENAME}.' . $next_gen_format . " -f\n";
		$rules .= '    RewriteRule (.+)\.(jpe?g|png)$ $1.$2.' . $next_gen_format . ' [T=' . $mime_type . ",E=__CH_REWRITE_MATCH:1,L]\n";
		$rules .= "</IfModule>\n\n";

		$rules .= "<IfModule mod_headers.c>\n";
		$rules .= "    # Add Vary header to inform caches to serve different versions based on browser's Accept header.\n";
		$rules .= "    Header append Vary Accept env=__CH_REWRITE_MATCH\n";
		$rules .= "</IfModule>\n";

		return $rules;
	}


	/**
	 * Generates Nginx rewrite rules for next-gen images.
	 *
	 * @param array $settings The plugin settings.
	 * @return string The Nginx rule block.
	 */
	public static function generate_image_rewrite_nginx_rules( array $settings ): string {
		if ( 'rewrite' !== ( $settings['image_delivery_method'] ?? 'picture' ) ) {
			return '';
		}
		// In Nginx, we can use a map to handle this cleanly.
		$rules  = "map \$http_accept \$webp_suffix {\n";
		$rules .= "    default \"\";\n";
		$rules .= "    ~*webp \$webp_suffix .webp;\n";
		$rules .= "}\n\n";
		$rules .= "location ~* \.(jpe?g|png|gif)$ {\n";
		$rules .= "    add_header Vary Accept;\n";
		$rules .= "    try_files \$uri\$webp_suffix \$uri =404;\n";
		$rules .= "}\n";
		return $rules;
	}


	/**
	 * Updates an .htaccess file with the provided rules and marker.
	 *
	 * @param string       $htaccess_path The full path to the .htaccess file.
	 * @param string       $marker The unique marker for the rules block.
	 * @param array|string $rules The rules to insert.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public static function update_htaccess( string $htaccess_path, string $marker, $rules ) {
		if ( ! is_writable( $htaccess_path ) && ( ! file_exists( $htaccess_path ) && ! is_writable( dirname( $htaccess_path ) ) ) ) {
			return new WP_Error( 'htaccess_not_writable', __( 'The .htaccess file is not writable.', 'cache-hive' ) );
		}

		if ( ! function_exists( 'insert_with_markers' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}

		if ( insert_with_markers( $htaccess_path, $marker, (array) $rules ) ) {
			return true;
		}

		return new WP_Error( 'htaccess_write_failed', __( 'Failed to write to .htaccess.', 'cache-hive' ) );
	}
}
