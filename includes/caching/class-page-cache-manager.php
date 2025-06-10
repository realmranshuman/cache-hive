<?php
/**
 * Page Cache Manager Class
 *
 * Handles the creation and management of static page cache files.
 * This class is responsible for output buffering, cache file generation,
 * and cache invalidation scheduling.
 *
 * @package CacheHive
 * @subpackage Caching
 */

namespace CacheHive\Includes\Caching;

use CacheHive\Includes\Settings;
use CacheHive\Includes\Utilities\Cache_Invalidator;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manages the creation of static page cache files.
 */
class Page_Cache_Manager {

	const CACHE_DIR = WP_CONTENT_DIR . '/cache/cache-hive/';
	const CRON_HOOK = 'cachehive_purge_expired_cache';

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Cache invalidator instance.
	 *
	 * @var Cache_Invalidator
	 */
	private $invalidator;

	/**
	 * Constructor.
	 *
	 * @param Settings          $settings    Settings instance.
	 * @param Cache_Invalidator $invalidator Cache invalidator instance.
	 */
	public function __construct( Settings $settings, Cache_Invalidator $invalidator ) {
		$this->settings    = $settings;
		$this->invalidator = $invalidator;
		$this->init();
	}

	/**
	 * Initializes hooks for page caching.
	 */
	public function init() {
		// template_redirect is a great hook to decide if we should cache.
		add_action( 'template_redirect', array( $this, 'maybe_start_buffering' ) );

		// Cron for clearing expired cache.
		add_action( self::CRON_HOOK, array( $this->invalidator, 'clear_expired_cache' ) );
		$this->schedule_cache_expiry_event();
	}

	/**
	 * Starts the output buffer if the current request is cacheable.
	 */
	public function maybe_start_buffering() {
		if ( $this->is_cacheable_request() ) {
			ob_start( array( $this, 'write_cache_file' ) );
		}
	}

	/**
	 * Determines if the current request should be cached.
	 * This is the refactored version of the old plugin's `bypass_cache` and `is_excluded` logic.
	 *
	 * @return bool True if the request is cacheable, false otherwise.
	 */
	private function is_cacheable_request() {
		// Basic checks first (these don't need the exclusion file).
		if ( is_user_logged_in() || is_admin() || is_search() || is_404() || is_feed() || is_trackback() || is_robots() || is_preview() || is_embed() || defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || defined( 'REST_REQUEST' ) || post_password_required() || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) || ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) ) {
			return false;
		}

		// --- Check against the compiled exclusion file ---
		$exclusion_file = WP_CONTENT_DIR . '/cache/cache-hive/config/exclusions-local.php';
		$is_excluded    = false;
		if ( is_readable( $exclusion_file ) ) {
			$is_excluded = include $exclusion_file;
		}

		if ( $is_excluded ) {
			return false;
		}

		// If all checks pass, the page is cacheable.
		return apply_filters( 'cachehive_is_cacheable_request', true );
	}

	/**
	 * Callback for ob_start. Writes the buffer content to a file.
	 *
	 * @param string $buffer The HTML content from the output buffer.
	 * @return string The buffer, which is then sent to the browser.
	 */
	public function write_cache_file( $buffer ) {
		if ( strlen( $buffer ) < 255 || ! $this->is_valid_html( $buffer ) ) {
			return $buffer;
		}

		// Perform minification if enabled.
		if ( $this->settings->get_option( 'minify_html_enabled' ) ) {
			$buffer = $this->minify_html( $buffer );
		}

		$filepath = $this->get_cache_filepath();

		if ( $filepath ) {
			$directory = dirname( $filepath );
			if ( ! is_dir( $directory ) ) {
				wp_mkdir_p( $directory );
			}

			$signature = sprintf(
				'<!-- CacheHive Page Cache @ %s on %s (%s) -->',
				gmdate( 'H:i:s' ),
				gmdate( 'Y-m-d' ),
				$this->is_mobile_request() ? 'mobile' : 'desktop'
			);

			file_put_contents( $filepath, $buffer . "\n" . $signature, LOCK_EX );
		}

		return $buffer;
	}

	/**
	 * Generates the full path for the cache file.
	 * This logic must EXACTLY match the logic in advanced-cache.php.
	 *
	 * @return string|false The absolute path to the cache file, or false on invalid URI.
	 */
	private function get_cache_filepath() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		if ( strpos( $request_uri, '..' ) !== false ) {
			return false;
		}

		// Use the override constant if it exists, otherwise use the server variable.
		$host = '';
		if ( defined( 'CACHEHIVE_HOST' ) ) {
			$host = CACHEHIVE_HOST;
		} elseif ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$host = strtok( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ), ':' );
		}

		if ( empty( $host ) ) {
			return false;
		}

		$path = self::CACHE_DIR . $host;

		// Add mobile sub-directory if mobile caching is enabled and it's a mobile request.
		if ( $this->settings->get_option( 'mobile_cache_enabled' ) && $this->is_mobile_request() ) {
			$path .= '/mobile';
		}

		$path .= $request_uri;

		if ( '/' === substr( $request_uri, -1 ) ) {
			$filepath = $path . 'index.html';
		} else {
			$filepath = $path;
		}

		return $filepath;
	}

	/**
	 * Simple mobile detection based on User-Agent.
	 *
	 * @return bool True if the request is likely from a mobile device.
	 */
	private function is_mobile_request() {
		if ( ! isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return false;
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		// This uses the same regex as the deprecated wp_is_mobile() for consistency.
		$mobile_pattern = '/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|rim)|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i';
		return (bool) preg_match( $mobile_pattern, $user_agent );
	}

	/**
	 * Minifies the given HTML buffer.
	 *
	 * @param string $buffer The raw HTML content.
	 * @return string The minified HTML content.
	 */
	private function minify_html( $buffer ) {
		$minify_inline_css = (bool) $this->settings->get_option( 'minify_inline_css' );
		$minify_inline_js  = (bool) $this->settings->get_option( 'minify_inline_js' );

		// Basic HTML minification: remove comments and excessive whitespace.
		$buffer = preg_replace( '/<!--(.|\s)*?-->/', '', $buffer );
		$buffer = preg_replace( '/\s+/', ' ', $buffer );

		// Minify inline CSS if enabled for better performance.
		if ( $minify_inline_css ) {
			$buffer = preg_replace_callback(
				'#<style(.*?)>(.*?)</style>#is',
				function ( $matches ) {
					$css = $matches[2];
					// Remove CSS comments for size reduction.
					$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
					// Remove space after colons for compression.
					$css = str_replace( ': ', ':', $css );
					// Remove whitespace for minimal file size.
					$css = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ), '', $css );
					return "<style{$matches[1]}>{$css}</style>";
				},
				$buffer
			);
		}

		// Minify inline JS if enabled.
		if ( $minify_inline_js ) {
			$buffer = preg_replace_callback(
				'#<script(.*?)>(.*?)</script>#is',
				function ( $matches ) {
					// Don't minify scripts with a type attribute like application/ld+json.
					if ( strpos( $matches[1], 'type' ) !== false && strpos( $matches[1], 'javascript' ) === false ) {
						return $matches[0];
					}
					// Basic JS minification: remove comments and newlines. A proper minifier is better but more complex.
					$js = $matches[2];
					$js = preg_replace( '/\s*\/\/[^\n]*/', '', $js ); // single line comments.
					$js = preg_replace( '/\s*\/\*(.|\s)*?\*\//', '', $js ); // multi-line comments.
					$js = preg_replace( '/\s+/', ' ', $js );
					return "<script{$matches[1]}>" . trim( $js ) . '</script>';
				},
				$buffer
			);
		}

		return trim( $buffer );
	}

	/**
	 * Basic check to see if the content is likely HTML.
	 *
	 * @param string $content The content buffer.
	 * @return bool
	 */
	private function is_valid_html( $content ) {
		return strpos( $content, '</html>' ) !== false;
	}

	/**
	 * Schedules the cron event for clearing expired cache if not already scheduled.
	 */
	private function schedule_cache_expiry_event() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}
}
