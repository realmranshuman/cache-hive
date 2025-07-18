<?php
/**
 * Handles object cache settings and integration for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the object cache drop-in management for Cache Hive.
 */
final class Cache_Hive_Object_Cache {

	/**
	 * Path to the object-cache.php drop-in file.
	 *
	 * @var string|false
	 */
	private static $dropin_path;

	/**
	 * Marker for the Cache Hive Object Cache Drop-in.
	 *
	 * @var string
	 */
	private const DROPIN_MARKER = 'Cache Hive Object Cache Drop-in v1.0';

	/**
	 * Initialize the drop-in path.
	 */
	public static function init() {
		self::$dropin_path = defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/object-cache.php' : false;
	}

	/**
	 * Manage the object-cache.php drop-in based on settings.
	 *
	 * @param array|null $settings Optional settings array.
	 */
	public static function manage_dropin( $settings = null ) {
		if ( ! self::$dropin_path ) {
			return;
		}
		if ( is_null( $settings ) ) {
			$settings = Cache_Hive_Settings::get_settings( true );
		}
		if ( ! empty( $settings['object_cache_enabled'] ) ) {
			self::enable( $settings );
		} else {
			self::disable();
		}
	}

	/**
	 * Enable the object-cache.php drop-in.
	 *
	 * @param array $settings Settings array.
	 * @return bool True on success, false on failure.
	 */
	public static function enable( $settings ) {
		if ( ! self::$dropin_path || ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}

		if ( file_exists( self::$dropin_path ) && ! self::is_dropin_active() ) {
			@rename( self::$dropin_path, self::$dropin_path . '.ch-backup' );
		}

		$dropin_content = self::get_dropin_content( $settings );
		if ( empty( $dropin_content ) ) {
			return false;
		}

		$result = file_put_contents( self::$dropin_path, $dropin_content, LOCK_EX );
		if ( false === $result ) {
			return false;
		}

		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			opcache_invalidate( self::$dropin_path, true );
		}

		if ( function_exists( 'wp_is_writable' ) && ! wp_is_writable( self::$dropin_path ) ) {
			chmod( self::$dropin_path, 0644 );
		}

		return self::is_dropin_active();
	}

	/**
	 * Disable the object-cache.php drop-in.
	 *
	 * Deletes the Cache Hive object-cache.php drop-in if present,
	 * and restores any previous backup (object-cache.php.ch-backup) if it exists
	 * and is NOT a Cache Hive drop-in (i.e., from another plugin or WordPress).
	 * If both are ours, both are deleted and nothing is restored.
	 *
	 * @return void
	 */
	public static function disable() {
		if ( ! self::$dropin_path ) {
			return;
		}

		$dropin_is_ch = self::is_dropin_active();
		$backup_path  = self::$dropin_path . '.ch-backup';
		$backup_is_ch = false;

		if ( file_exists( $backup_path ) ) {
			// Check if the backup is also a Cache Hive drop-in.
			$backup_contents = @file_get_contents( $backup_path, false, null, 0, 128 );
			$backup_is_ch    = $backup_contents && str_contains( $backup_contents, self::DROPIN_MARKER );
		}

		// Remove the drop-in if it's ours.
		if ( $dropin_is_ch && file_exists( self::$dropin_path ) ) {
			@unlink( self::$dropin_path );
		}

		// Restore backup ONLY if not a Cache Hive drop-in.
		if ( file_exists( $backup_path ) ) {
			if ( ! $backup_is_ch ) {
				@rename( $backup_path, self::$dropin_path );
			} else {
				// Remove the backup if it was from Cache Hive.
				@unlink( $backup_path );
			}
		}
	}

	/**
	 * Check if the Cache Hive object-cache.php drop-in is active.
	 *
	 * @return bool True if active, false otherwise.
	 */
	public static function is_dropin_active() {
		if ( ! self::$dropin_path || ! file_exists( self::$dropin_path ) ) {
			return false;
		}
		$content = @file_get_contents( self::$dropin_path, false, null, 0, 128 );
		return $content && str_contains( $content, self::DROPIN_MARKER );
	}

	/**
	 * Generate the content for the object-cache.php drop-in.
	 *
	 * @param array $settings The final, authoritative settings array.
	 * @return string The drop-in PHP code.
	 */
	private static function get_dropin_content( $settings ) {
		$config_to_embed = Cache_Hive_Settings::get_object_cache_runtime_config( $settings );
		$generation_date = gmdate( 'Y-m-d H:i:s T' );
		$config_exported = var_export( $config_to_embed, true );
		$marker          = self::DROPIN_MARKER; // Use correct marker in heredoc.

		// This heredoc contains the entire object-cache.php file.
		// It is intentionally not namespaced and contains minified functions
		// to be a valid, self-contained WordPress drop-in.
		return <<<PHP
<?php
/**
 * {$marker}
 * Generated: {$generation_date}
 */
if ( defined( 'WP_CACHE_HIVE_OBJECT_CACHE_LOADED' ) ) { return; }

// Bootstrap Composer autoloader to make Predis/Credis available.
\$autoloader = WP_CONTENT_DIR . '/plugins/cache-hive/vendor/autoload.php';
if ( file_exists( \$autoloader ) ) {
	require_once \$autoloader;
}

// Standard WordPress object cache functions
function wp_cache_add(\$key, \$data, \$group = 'default', \$expire = 0) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->add(\$key, \$data, \$group, (int) \$expire); }
function wp_cache_close() { if (!isset(\$GLOBALS['wp_object_cache'])) { return true; } return \$GLOBALS['wp_object_cache']->close(); }
function wp_cache_decr(\$key, \$offset = 1, \$group = 'default') { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->decr(\$key, \$offset, \$group); }
function wp_cache_delete(\$key, \$group = 'default') { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->delete(\$key, \$group); }
function wp_cache_flush() { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->flush(); }
function wp_cache_get(\$key, \$group = 'default', \$force = false, &\$found = null) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->get(\$key, \$group, \$force, \$found); }
function wp_cache_get_multi(\$keys, \$group = 'default', \$force = false) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->get_multiple(\$keys, \$group); }
function wp_cache_incr(\$key, \$offset = 1, \$group = 'default') { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->incr(\$key, \$offset, \$group); }
function wp_cache_init() { if (!isset(\$GLOBALS['wp_object_cache'])) { \$GLOBALS['wp_object_cache'] = new WP_Object_Cache(); } else { \$GLOBALS['wp_object_cache']->reinitialize(); } }
function wp_cache_replace(\$key, \$data, \$group = 'default', \$expire = 0) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->replace(\$key, \$data, \$group, (int) \$expire); }
function wp_cache_set(\$key, \$data, \$group = 'default', \$expire = 0) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->set(\$key, \$data, \$group, (int) \$expire); }
function wp_cache_switch_to_blog(\$blog_id) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } \$GLOBALS['wp_object_cache']->switch_to_blog(\$blog_id); }
function wp_cache_add_global_groups(\$groups) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } \$GLOBALS['wp_object_cache']->add_global_groups(\$groups); }
function wp_cache_add_non_persistent_groups(\$groups) { }
function wp_cache_reset() { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } \$GLOBALS['wp_object_cache']->reset(); }
function wp_cache_get_info() { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->get_info(); }

final class WP_Object_Cache {
    private \$backend; private \$config; private \$blog_prefix; private \$multisite; private \$cache = []; private \$prefetched = false;
    public function __construct() { \$this->reinitialize(); }
    public function reinitialize() {
        \$this->config = {$config_exported};
        \$this->reset();
        \$plugin_path = WP_CONTENT_DIR . "/plugins/cache-hive/";
        \$files_to_load = [ 'includes/object-cache/interface-backend.php', 'includes/object-cache/class-cache-hive-redis-phpredis-backend.php', 'includes/object-cache/class-cache-hive-redis-predis-backend.php', 'includes/object-cache/class-cache-hive-redis-credis-backend.php', 'includes/object-cache/class-cache-hive-memcached-backend.php', 'includes/object-cache/class-cache-hive-array-backend.php', 'includes/object-cache/class-cache-hive-object-cache-factory.php' ];
        foreach (\$files_to_load as \$file) { if (file_exists(\$plugin_path . \$file)) { require_once \$plugin_path . \$file; } }
		if (class_exists('Cache_Hive\\Includes\\Object_Cache\\Cache_Hive_Object_Cache_Factory')) { \$this->backend = \\Cache_Hive\\Includes\\Object_Cache\\Cache_Hive_Object_Cache_Factory::create(\$this->config); }
		else { \$this->backend = new \\Cache_Hive\\Includes\\Object_Cache\\Cache_Hive_Array_Backend(\$this->config); }
        \$this->multisite = is_multisite();
        \$this->blog_prefix = \$this->multisite ? get_current_blog_id() . ':' : '';
        if (\$this->config['prefetch'] && (is_admin() || defined('WP_CLI'))) { \$this->prefetch_options(); }
    }
    private function get_key(\$key, \$group) { if (empty(\$group)) { \$group = 'default'; } \$salt = \$this->config['key_prefix'] ?? ''; \$global_groups = \$this->config['global_groups'] ?? []; \$prefix = in_array(\$group, \$global_groups, true) ? '' : \$this->blog_prefix; return (\$salt ? \$salt . ':' : '') . \$prefix . "{\$group}:{\$key}"; }
    private function is_non_persistent_group(\$group) { \$no_cache_groups = \$this->config['no_cache_groups'] ?? []; if (empty(\$group) || empty(\$no_cache_groups)) return false; foreach (\$no_cache_groups as \$no_cache_group) { if (str_starts_with(\$group, \$no_cache_group)) return true; } return false; }
    private function prefetch_options() { if (\$this->prefetched) return; \$alloptions_key = \$this->get_key('alloptions', 'options'); \$alloptions = \$this->backend->get(\$alloptions_key, \$found); if (\$found && is_array(\$alloptions)) { foreach (\$alloptions as \$name => \$value) { \$this->cache[\$this->get_key(\$name, 'options')] = \$value; } \$this->prefetched = true; } }
    public function set(\$key, \$data, \$group = 'default', \$expire = 0) { \$cache_key = \$this->get_key(\$key, \$group); if (\$this->is_non_persistent_group(\$group)) { \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; return true; } if (\$this->backend->set(\$cache_key, \$data, \$expire > 0 ? \$expire : \$this->config['lifetime'])) { \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; return true; } return false; }
    public function add(\$key, \$data, \$group = 'default', \$expire = 0) { \$cache_key = \$this->get_key(\$key, \$group); if (isset(\$this->cache[\$cache_key])) return false; if (\$this->is_non_persistent_group(\$group)) { \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; return true; } if (\$this->backend->add(\$cache_key, \$data, \$expire > 0 ? \$expire : \$this->config['lifetime'])) { \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; return true; } return false; }
    public function replace(\$key, \$data, \$group = 'default', \$expire = 0) { \$cache_key = \$this->get_key(\$key, \$group); if (!isset(\$this->cache[\$cache_key])) return false; if (\$this->is_non_persistent_group(\$group)) { \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; return true; } if (\$this->backend->replace(\$cache_key, \$data, \$expire > 0 ? \$expire : \$this->config['lifetime'])) { \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; return true; } return false; }
    public function get(\$key, \$group = 'default', \$force = false, &\$found = null) { \$cache_key = \$this->get_key(\$key, \$group); if (!\$force && isset(\$this->cache[\$cache_key])) { \$found = true; return is_object(\$this->cache[\$cache_key]) ? clone \$this->cache[\$cache_key] : \$this->cache[\$cache_key]; } if (\$this->is_non_persistent_group(\$group)) { \$found = false; return false; } \$value = \$this->backend->get(\$cache_key, \$found); if ((\$key === 'alloptions') && \$found && !is_array(\$value)) { \$found = false; return false; } if (\$found) { \$this->cache[\$cache_key] = \$value; } return is_object(\$value) ? clone \$value : \$value; }
    public function get_multiple(\$keys, \$group = 'default') { if (\$this->is_non_persistent_group(\$group)) return []; \$prefixed_keys = []; \$cached_results = []; foreach((array) \$keys as \$key) { \$cache_key = \$this->get_key(\$key, \$group); if (isset(\$this->cache[\$cache_key])) { \$cached_results[\$key] = \$this->cache[\$cache_key]; } else { \$prefixed_keys[\$cache_key] = \$key; } } if (empty(\$prefixed_keys)) { return \$cached_results; } \$values = \$this->backend->get_multiple(array_keys(\$prefixed_keys)); \$result = []; foreach (\$values as \$prefixed_key => \$value) { if (isset(\$prefixed_keys[\$prefixed_key])) { \$original_key = \$prefixed_keys[\$prefixed_key]; \$result[\$original_key] = \$value; \$this->cache[\$prefixed_key] = \$value; } } return array_merge(\$cached_results, \$result); }
    public function delete(\$key, \$group = 'default') { \$cache_key = \$this->get_key(\$key, \$group); unset(\$this->cache[\$cache_key]); if (\$this->is_non_persistent_group(\$group)) return true; return \$this->backend->delete(\$cache_key); }
    public function incr(\$key, \$offset = 1, \$group = 'default') { \$cache_key = \$this->get_key(\$key, \$group); unset(\$this->cache[\$cache_key]); if (\$this->is_non_persistent_group(\$group)) { return false; } \$new_value = \$this->backend->increment(\$cache_key, \$offset); if (false !== \$new_value) { \$this->cache[\$cache_key] = \$new_value; } return \$new_value; }
    public function decr(\$key, \$offset = 1, \$group = 'default') { \$cache_key = \$this->get_key(\$key, \$group); unset(\$this->cache[\$cache_key]); if (\$this->is_non_persistent_group(\$group)) { return false; } \$new_value = \$this->backend->decrement(\$cache_key, \$offset); if (false !== \$new_value) { \$this->cache[\$cache_key] = \$new_value; } return \$new_value; }
    public function reset() { \$this->cache = []; \$this->prefetched = false; }
    public function flush() { \$this->reset(); return \$this->backend->flush(\$this->config['flush_async']); }
    public function close() { return \$this->backend->close(); }
    public function get_info() { return \$this->backend->get_info(); }
    public function switch_to_blog(\$blog_id) { if (!\$this->multisite) return; \$this->blog_prefix = (int) \$blog_id . ':'; \$this->reset(); if (\$this->config['prefetch'] && (is_admin() || defined('WP_CLI'))) { \$this->prefetch_options(); } }
    public function add_global_groups(\$groups) { \$this->config['global_groups'] = array_unique(array_merge(\$this->config['global_groups'], (array) \$groups)); }
    public function __destruct() { \$this->close(); }
}

define('WP_CACHE_HIVE_OBJECT_CACHE_LOADED', true);
PHP;
	}
}
