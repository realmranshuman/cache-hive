<?php
/**
 * Handles object cache settings and integration for Cache Hive.
 *
 * @package Cache_Hive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Hive_Object_Cache
 *
 * Handles the object cache drop-in management for Cache Hive.
 */
final class Cache_Hive_Object_Cache {

	/**
	 * Path to the object-cache.php drop-in file.
	 *
	 * @var string|null
	 */
	private static $dropin_path;
	/**
	 * Marker for the Cache Hive Object Cache Drop-in.
	 *
	 * @var string
	 */
	private const DROPIN_MARKER = 'Cache Hive Object Cache Drop-in v2';

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
		if ( ! empty( $settings['objectCacheEnabled'] ) ) {
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
		if ( ! is_writable( WP_CONTENT_DIR ) ) {
			return false;
		}
		if ( file_exists( self::$dropin_path ) && ! self::is_dropin_active() ) {
			@rename( self::$dropin_path, self::$dropin_path . '.ch-backup' );
		}
		$dropin_content = self::get_dropin_content( $settings );
		if ( empty( $dropin_content ) ) {
			return false;
		}
		$result = file_put_contents( self::$dropin_path, $dropin_content );
		if ( false === $result ) {
			return false;
		}

		// Invalidate OPcache for the drop-in file.
		if ( function_exists( 'opcache_invalidate' ) && ini_get( 'opcache.enable' ) ) {
			if ( ! opcache_invalidate( self::$dropin_path, true ) ) {
				error_log( '[Cache Hive] Failed to invalidate OPcache for: ' . self::$dropin_path . '. Maybe OPcache is disabled?' );
			}
		}

		if ( file_exists( self::$dropin_path ) && function_exists( 'wp_is_writable' ) && ! wp_is_writable( self::$dropin_path ) ) {
			if ( false === chmod( self::$dropin_path, 0644 ) ) {
				// Optionally log or handle chmod failure here.
			}
		}
		return self::is_dropin_active();
	}

	/**
	 * Disable the object-cache.php drop-in.
	 */
	public static function disable() {
		if ( self::is_dropin_active() ) {
			@unlink( self::$dropin_path );
			if ( file_exists( self::$dropin_path . '.ch-backup' ) ) {
				@rename( self::$dropin_path . '.ch-backup', self::$dropin_path );
			}
		}
	}

	/**
	 * Check if the Cache Hive object-cache.php drop-in is active.
	 *
	 * @return bool True if active, false otherwise.
	 */
	public static function is_dropin_active() {
		if ( ! file_exists( self::$dropin_path ) ) {
			return false;
		}
		$content = @file_get_contents( self::$dropin_path, false, null, 0, 100 );
		return $content && false !== strpos( $content, self::DROPIN_MARKER );
	}

	/**
	 * Generate the content for the object-cache.php drop-in.
	 *
	 * @param array $settings Settings array.
	 * @return string The drop-in PHP code.
	 */
	private static function get_dropin_content( $settings ) {
		$config = array(
			'client'          => $settings['client'] ?? 'phpredis',
			'host'            => $settings['objectCacheHost'] ?? '127.0.0.1',
			'port'            => $settings['objectCachePort'] ?? 6379,
			'scheme'          => $settings['scheme'] ?? 'tcp',
			'timeout'         => $settings['timeout'] ?? 2.0,
			'database'        => $settings['database'] ?? 0,
			'lifetime'        => $settings['objectCacheLifetime'] ?? 3600,
			'user'            => $settings['objectCacheUsername'] ?? '',
			'pass'            => $settings['objectCachePassword'] ?? '',
			'persistent'      => ! empty( $settings['persistent'] ),
			'tls_enabled'     => ! empty( $settings['tls_enabled'] ),
			'global_groups'   => $settings['objectCacheGlobalGroups'] ?? array(),
			'no_cache_groups' => $settings['objectCacheNoCacheGroups'] ?? array(),
			'prefetch'        => ! empty( $settings['prefetch'] ),
			'serializer'      => $settings['serializer'] ?? 'php',
			'compression'     => $settings['compression'] ?? 'none',
			'flush_async'     => ! empty( $settings['flush_async'] ),
			'objectCacheKey'  => $settings['objectCacheKey'] ?? '',
		);

		$generation_date = gmdate( 'Y-m-d H:i:s T' );
		$config_exported = var_export( $config, true );

		return <<<PHP
<?php
/**
 * Cache Hive Object Cache Drop-in v2
 * Generated: {$generation_date}
 */
if ( defined( 'WP_CACHE_HIVE_OBJECT_CACHE_LOADED' ) ) { return; }

// Bootstrap Composer autoloader to make Predis/Credis available.
\$autoloader = WP_CONTENT_DIR . '/plugins/cache-hive/vendor/autoload.php';
if ( file_exists( \$autoloader ) ) {
	require_once \$autoloader;
}

function wp_cache_add(\$key, \$data, \$group = 'default', \$expire = 0) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->add(\$key, \$data, \$group, (int) \$expire); }
function wp_cache_close() { if (!isset(\$GLOBALS['wp_object_cache'])) { return true; } return \$GLOBALS['wp_object_cache']->close(); }
function wp_cache_decr(\$key, \$offset = 1, \$group = 'default') { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->decr(\$key, \$offset, \$group); }
function wp_cache_delete(\$key, \$group = 'default') { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->delete(\$key, \$group); }
function wp_cache_flush() { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->flush(); }
function wp_cache_get(\$key, \$group = 'default', \$force = false, &\$found = null) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->get(\$key, \$group, \$force, \$found); }
function wp_cache_get_multi(\$keys, \$group = 'default', \$force = false) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->get_multiple(\$keys, \$group); }
function wp_cache_incr(\$key, \$offset = 1, \$group = 'default') { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->incr(\$key, \$offset, \$group); }
function wp_cache_init() { if (isset(\$GLOBALS['wp_object_cache'])) return; \$GLOBALS['wp_object_cache'] = new WP_Object_Cache(); }
function wp_cache_replace(\$key, \$data, \$group = 'default', \$expire = 0) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->replace(\$key, \$data, \$group, (int) \$expire); }
function wp_cache_set(\$key, \$data, \$group = 'default', \$expire = 0) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->set(\$key, \$data, \$group, (int) \$expire); }
function wp_cache_switch_to_blog(\$blog_id) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } \$GLOBALS['wp_object_cache']->switch_to_blog(\$blog_id); }
function wp_cache_add_global_groups(\$groups) { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } \$GLOBALS['wp_object_cache']->add_global_groups(\$groups); }
function wp_cache_add_non_persistent_groups(\$groups) { }
function wp_cache_reset() { }
function wp_cache_get_info() { if (!isset(\$GLOBALS['wp_object_cache'])) { wp_cache_init(); } return \$GLOBALS['wp_object_cache']->get_info(); }

final class WP_Object_Cache {
    private \$backend; private \$config; private \$blog_prefix; private \$multisite; private \$cache = []; private \$prefetched = false;
    public function __construct() {
        \$this->config = {$config_exported};
        \$plugin_path = WP_CONTENT_DIR . "/plugins/cache-hive/";
        \$files = [ 'includes/object-cache/interface-backend.php', 'includes/object-cache/class-cache-hive-redis-phpredis-backend.php', 'includes/object-cache/class-cache-hive-redis-predis-backend.php', 'includes/object-cache/class-cache-hive-redis-credis-backend.php', 'includes/object-cache/class-cache-hive-memcached-backend.php', 'includes/object-cache/class-cache-hive-array-backend.php', 'includes/object-cache/class-cache-hive-object-cache-factory.php' ];
        foreach (\$files as \$file) { if (file_exists(\$plugin_path . \$file)) { require_once \$plugin_path . \$file; } }
        if (class_exists('Cache_Hive_Object_Cache_Factory')) { \$this->backend = Cache_Hive_Object_Cache_Factory::create(\$this->config); } else { \$this->backend = new Cache_Hive_Array_Backend(\$this->config); }
        \$this->multisite = is_multisite(); \$this->blog_prefix = \$this->multisite ? get_current_blog_id() . ':' : '';
        if (\$this->config['prefetch'] && (is_admin() || defined('WP_CLI'))) { \$this->prefetch_options(); }
    }
    private function get_key(\$key, \$group) { if (empty(\$group)) { \$group = 'default'; } \$salt = \$this->config['objectCacheKey'] ?? ''; \$prefix = in_array(\$group, \$this->config['global_groups'], true) ? '' : \$this->blog_prefix; return (\$salt ? \$salt . ':' : '') . "{\$prefix}{\$group}:{\$key}"; }
    private function is_non_persistent_group(\$group) { if (empty(\$group) || empty(\$this->config['no_cache_groups'])) return false; foreach (\$this->config['no_cache_groups'] as \$no_cache_group) { if (str_starts_with(\$group, \$no_cache_group)) return true; } return false; }
    private function prefetch_options() { if (\$this->prefetched) return; \$alloptions_key = \$this->get_key('alloptions', 'options'); \$alloptions = \$this->backend->get(\$alloptions_key, \$found); if (\$found && is_array(\$alloptions)) { foreach (\$alloptions as \$name => \$value) { \$this->cache[\$this->get_key(\$name, 'options')] = \$value; } \$this->prefetched = true; } }
    
    public function set(\$key, \$data, \$group = 'default', \$expire = 0) { \$cache_key = \$this->get_key(\$key, \$group); \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; if (\$this->is_non_persistent_group(\$group)) return true; return \$this->backend->set(\$cache_key, \$data, \$expire > 0 ? \$expire : \$this->config['lifetime']); }
    public function add(\$key, \$data, \$group = 'default', \$expire = 0) { \$cache_key = \$this->get_key(\$key, \$group); if (isset(\$this->cache[\$cache_key])) return false; \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; if (\$this->is_non_persistent_group(\$group)) return true; return \$this->backend->add(\$cache_key, \$data, \$expire > 0 ? \$expire : \$this->config['lifetime']); }
    public function replace(\$key, \$data, \$group = 'default', \$expire = 0) { \$cache_key = \$this->get_key(\$key, \$group); if (!isset(\$this->cache[\$cache_key])) return false; \$this->cache[\$cache_key] = is_object(\$data) ? clone \$data : \$data; if (\$this->is_non_persistent_group(\$group)) return true; return \$this->backend->replace(\$cache_key, \$data, \$expire > 0 ? \$expire : \$this->config['lifetime']); }
    public function get(\$key, \$group = 'default', \$force = false, &\$found = null) { \$cache_key = \$this->get_key(\$key, \$group); if (!\$force && isset(\$this->cache[\$cache_key])) { \$found = true; return is_object(\$this->cache[\$cache_key]) ? clone \$this->cache[\$cache_key] : \$this->cache[\$cache_key]; } if (\$this->is_non_persistent_group(\$group)) { \$found = false; return false; } \$value = \$this->backend->get(\$cache_key, \$found); if ((\$key === 'alloptions') && \$found && !is_array(\$value)) { \$found = false; return false; } if (\$found) { \$this->cache[\$cache_key] = \$value; } return is_object(\$value) ? clone \$value : \$value; }
    public function get_multiple(\$keys, \$group = 'default') { if (\$this->is_non_persistent_group(\$group)) return []; \$prefixed_keys = []; foreach((array) \$keys as \$key) { \$prefixed_keys[\$this->get_key(\$key, \$group)] = \$key; } \$values = \$this->backend->get_multiple(array_keys(\$prefixed_keys)); \$result = []; foreach (\$values as \$prefixed_key => \$value) { if (isset(\$prefixed_keys[\$prefixed_key])) { \$result[\$prefixed_keys[\$prefixed_key]] = \$value; } } return \$result; }
    public function delete(\$key, \$group = 'default') { \$cache_key = \$this->get_key(\$key, \$group); unset(\$this->cache[\$cache_key]); if (\$this->is_non_persistent_group(\$group)) return true; return \$this->backend->delete(\$cache_key); }
    
    public function incr(\$key, \$offset = 1, \$group = 'default') {
        \$cache_key = \$this->get_key(\$key, \$group);
        if (\$this->is_non_persistent_group(\$group)) {
            if (!isset(\$this->cache[\$cache_key])) {
                \$this->cache[\$cache_key] = 0;
            }
            \$this->cache[\$cache_key] += \$offset;
            return \$this->cache[\$cache_key];
        }
        unset(\$this->cache[\$cache_key]);
        return \$this->backend->increment(\$cache_key, \$offset);
    }
    public function decr(\$key, \$offset = 1, \$group = 'default') {
        \$cache_key = \$this->get_key(\$key, \$group);
        if (\$this->is_non_persistent_group(\$group)) {
            if (!isset(\$this->cache[\$cache_key])) {
                \$this->cache[\$cache_key] = 0;
            }
            \$this->cache[\$cache_key] -= \$offset;
            return \$this->cache[\$cache_key];
        }
        unset(\$this->cache[\$cache_key]);
        return \$this->backend->decrement(\$cache_key, \$offset);
    }
    
    public function flush() { \$this->cache = []; return \$this->backend->flush(\$this->config['flush_async']); }
    public function close() { return \$this->backend->close(); }
    public function get_info() { return \$this->backend->get_info(); }
    public function switch_to_blog(\$blog_id) { if (!\$this->multisite) return; \$this->blog_prefix = (int) \$blog_id . ':'; \$this->cache = []; \$this->prefetched = false; if (\$this->config['prefetch'] && (is_admin() || defined('WP_CLI'))) { \$this->prefetch_options(); } }
    public function add_global_groups(\$groups) { \$this->config['global_groups'] = array_unique(array_merge(\$this->config['global_groups'], (array) \$groups)); }
    public function __destruct() { \$this->close(); }
}
define('WP_CACHE_HIVE_OBJECT_CACHE_LOADED', true);
PHP;
	}
}
