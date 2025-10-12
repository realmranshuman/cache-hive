<?php
/**
 * Manages .htaccess rewrite rules for next-gen image delivery.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles generation and removal of rewrite rules.
 */
final class Cache_Hive_Image_Rewrite {

	/**
	 * Insert rewrite rules into the main .htaccess file.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function insert_rules(): bool {
		$htaccess_path = self::get_htaccess_path();
		if ( ! $htaccess_path || ! is_writable( $htaccess_path ) ) {
			return false;
		}

		$rules = self::get_rules();
		return insert_with_markers( $htaccess_path, 'Cache Hive Image Optimizer', $rules );
	}

	/**
	 * Remove rewrite rules from the main .htaccess file.
	 *
	 * @since 1.0.0
	 * @return bool True on success, false on failure.
	 */
	public static function remove_rules(): bool {
		$htaccess_path = self::get_htaccess_path();
		if ( ! $htaccess_path || ! is_writable( $htaccess_path ) ) {
			return false;
		}

		return insert_with_markers( $htaccess_path, 'Cache Hive Image Optimizer', array() );
	}

	/**
	 * Gets the path to the main .htaccess file.
	 *
	 * @since 1.0.0
	 * @return string|false The path to the file, or false if not found.
	 */
	private static function get_htaccess_path() {
		return ABSPATH . '.htaccess';
	}

	/**
	 * Generates the array of rewrite rules.
	 *
	 * @since 1.0.0
	 * @return array An array of rule strings.
	 */
	private static function get_rules(): array {
		$rules = array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'',
			'# Serve AVIF if browser accepts it and file exists.',
			'RewriteCond %{HTTP_ACCEPT} image/avif',
			'RewriteCond %{REQUEST_FILENAME}.avif -f',
			'RewriteRule ^(.*)$ $1.avif [T=image/avif,L]',
			'',
			'# Serve WebP if browser accepts it and file exists.',
			'RewriteCond %{HTTP_ACCEPT} image/webp',
			'RewriteCond %{REQUEST_FILENAME}.webp -f',
			'RewriteRule ^(.*)$ $1.webp [T=image/webp,L]',
			'</IfModule>',
			'',
			'<IfModule mod_headers.c>',
			'# Add Vary: Accept header to notify proxies about content negotiation.',
			'<FilesMatch "\.(jpe?g|png)$">',
			'Header append Vary Accept',
			'</FilesMatch>',
			'</IfModule>',
		);

		return $rules;
	}
}
