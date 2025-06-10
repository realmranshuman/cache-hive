<?php
namespace CacheHive\Includes\Admin;

use CacheHive\Includes\Settings;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The main admin orchestrator class.
 * Initializes all admin-side components.
 */
class Admin {
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		$this->init();
	}

	public function init() {
		// Load the class that handles all menu and settings registration.
		new Admin_Menu( $this->settings );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
		add_action( 'admin_notices', array( $this, 'show_configuration_notices' ) );
	}

	public function show_configuration_notices() {
		$screen = get_current_screen();
		if ( ! $screen || ( 'plugins' !== $screen->id && strpos( $screen->id, 'cachehive' ) === false ) ) {
			return;
		}
		if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'CacheHive Action Required:', 'cache-hive' ) . '</strong> ' . sprintf(
				wp_kses(
					__( 'To enable page caching, please add the following line to your %2$s file: %1$s', 'cache-hive' ),
					array(
						'code'   => array(),
						'strong' => array(),
						'br'     => array(),
					)
				),
				'<br><code>define( \'WP_CACHE\', true );</code>',
				'<strong>wp-config.php</strong>'
			) . '</p></div>';
		}
		if ( ! file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'CacheHive Warning:', 'cache-hive' ) . '</strong> ' . esc_html__( 'The advanced-cache.php file is missing. Please try deactivating and reactivating the CacheHive plugin.', 'cache-hive' ) . '</p></div>';
		}
	}

	public function enqueue_styles_and_scripts( $hook ) {
		if ( strpos( $hook, 'cachehive' ) === false ) {
			return; }
		wp_enqueue_style( 'cachehive-admin-styles', CACHEHIVE_PLUGIN_URL . 'admin/css/cache-hive-admin.css', array(), CACHEHIVE_VERSION );
	}
}

/**
 * Manages ALL admin menus, pages, sections, and fields for CacheHive.
 * This class is the single source of truth for the entire admin UI structure.
 */
class Admin_Menu {
	private $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
		$plugin_basename = plugin_basename( CACHEHIVE_PLUGIN_DIR . 'cache-hive.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_plugin_action_links' ) );
	}

	public function register_menus() {
		add_menu_page( 'CacheHive', 'CacheHive', 'manage_options', 'cachehive', array( $this, 'render_dashboard_page' ), 'data:image/svg+xml;base64,' . base64_encode( file_get_contents( CACHEHIVE_PLUGIN_DIR . 'assets/images/icon.svg' ) ), 81 );
		add_submenu_page( 'cachehive', __( 'Dashboard', 'cache-hive' ), __( 'Dashboard', 'cache-hive' ), 'manage_options', 'cachehive', array( $this, 'render_dashboard_page' ) );
		add_submenu_page( 'cachehive', __( 'Cache Settings', 'cache-hive' ), __( 'Cache Settings', 'cache-hive' ), 'manage_options', 'cachehive-settings', array( $this, 'render_settings_page' ) );
		add_submenu_page( 'cachehive', __( 'CF Integration', 'cache-hive' ), __( 'CF Integration', 'cache-hive' ), 'manage_options', 'cachehive-cloudflare', array( $this, 'render_cloudflare_page' ) );
	}

	public function register_plugin_settings() {
		register_setting( 'cachehive_settings_group', CACHEHIVE_SETTINGS_SLUG, array( $this->settings, 'sanitize' ) );

		// --- Fields for "Cache Settings" -> "Page Cache" Tab ---
		$page_cache_slug = 'cachehive_page_cache_settings';
		add_settings_section( 'cachehive_page_cache_section', null, null, $page_cache_slug );
		add_settings_field( 'page_cache_enabled', __( 'Enable Caching', 'cache-hive' ), array( $this->settings, 'render_checkbox_field' ), $page_cache_slug, 'cachehive_page_cache_section', array( 'label_for' => 'page_cache_enabled' ) );
		add_settings_field( 'mobile_cache_enabled', __( 'Separate Mobile Cache', 'cache-hive' ), array( $this->settings, 'render_checkbox_field' ), $page_cache_slug, 'cachehive_page_cache_section', array( 'label_for' => 'mobile_cache_enabled' ) );
		add_settings_field( 'minify_html_enabled', __( 'Minify HTML', 'cache-hive' ), array( $this->settings, 'render_checkbox_field' ), $page_cache_slug, 'cachehive_page_cache_section', array( 'label_for' => 'minify_html_enabled' ) );
		add_settings_field(
			'cache_lifespan',
			__( 'Cache Lifespan', 'cache-hive' ),
			array( $this->settings, 'render_number_field' ),
			$page_cache_slug,
			'cachehive_page_cache_section',
			array(
				'label_for' => 'cache_lifespan',
				'suffix'    => 'hours',
			)
		);

		// --- Fields for "Cache Settings" -> "Exclusions" Tab ---
		$exclusions_slug = 'cachehive_exclusions_settings';
		add_settings_section( 'cachehive_exclusions_section', null, null, $exclusions_slug );
		add_settings_field(
			'excluded_url_paths',
			__( 'Excluded URL Paths', 'cache-hive' ),
			array( $this->settings, 'render_textarea_field' ),
			$exclusions_slug,
			'cachehive_exclusions_section',
			array(
				'label_for'   => 'excluded_url_paths',
				'description' => 'One path per line. Any URL containing these strings will not be cached.',
			)
		);
		add_settings_field(
			'excluded_cookies',
			__( 'Excluded Cookies', 'cache-hive' ),
			array( $this->settings, 'render_textarea_field' ),
			$exclusions_slug,
			'cachehive_exclusions_section',
			array(
				'label_for'   => 'excluded_cookies',
				'description' => 'One cookie name (or partial name) per line.',
			)
		);

		// --- Fields for "CF Integration" -> "Authentication" Tab ---
		$cf_auth_slug = 'cachehive_cloudflare_authentication';
		add_settings_section( 'cachehive_cloudflare_auth_section', 'API Credentials', null, $cf_auth_slug );
		$api_token_url         = 'https://dash.cloudflare.com/profile/api-tokens?permissionGroupKeys=%5B%7B%22key%22%3A%22access%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22access_acct%22%2C%22type%22%3A%22read%22%7D%2C%7B%22key%22%3A%22account_analytics%22%2C%22type%22%3A%22read%22%7D%2C%7B%22key%22%3A%22analytics%22%2C%22type%22%3A%22read%22%7D%2C%7B%22key%22%3A%22billing%22%2C%22type%22%3A%22read%22%7D%2C%7B%22key%22%3A%22request_tracer%22%2C%22type%22%3A%22read%22%7D%2C%7B%22key%22%3A%22intel%22%2C%22type%22%3A%22read%22%7D%2C%7B%22key%22%3A%22bot_management%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22cache%22%2C%22type%22%3A%22purge%22%7D%2C%7B%22key%22%3A%22cache_settings%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22challenge_widgets%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22firewall_services%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22page_rules%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22ssl_and_certificates%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22workers_r2%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22workers_scripts%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22zone%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22zone_settings%22%2C%22type%22%3A%22edit%22%7D%2C%7B%22key%22%3A%22zone_waf%22%2C%22type%22%3A%22edit%22%7D%5D&name=CacheHive%20WordPress%20API%20Token';
		$api_token_description = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer" class="button button-secondary">%s</a><p class="description">%s</p><ul class="description" style="list-style:disc;margin-left:20px;"><li>%s</li></ul>', esc_url( $api_token_url ), __( 'Get Your API Token', 'cache-hive' ), __( 'This will pre-configure a token with all necessary permissions:', 'cache-hive' ), implode( '</li><li>', array( 'Zone Permissions: Cache Purge, Zone Settings, etc.', 'Account Permissions: Analytics, Workers, etc.' ) ) );
		add_settings_field(
			'cloudflare_api_token',
			__( 'API Token', 'cache-hive' ),
			array( $this->settings, 'render_password_field' ),
			$cf_auth_slug,
			'cachehive_cloudflare_auth_section',
			array(
				'label_for'   => 'cloudflare_api_token',
				'description' => $api_token_description,
			)
		);
		add_settings_field(
			'cloudflare_zone_id',
			__( 'Zone ID', 'cache-hive' ),
			array( $this->settings, 'render_text_field' ),
			$cf_auth_slug,
			'cachehive_cloudflare_auth_section',
			array(
				'label_for'   => 'cloudflare_zone_id',
				'description' => 'Find this on the "Overview" page for your domain in the Cloudflare dashboard.',
			)
		);
	}

	public function render_dashboard_page() {
		require_once CACHEHIVE_PLUGIN_DIR . 'admin/views/tab-dashboard.php'; }
	public function render_settings_page() {
		require_once CACHEHIVE_PLUGIN_DIR . 'admin/views/settings-wrapper.php'; }
	public function render_cloudflare_page() {
		require_once CACHEHIVE_PLUGIN_DIR . 'admin/views/cloudflare-wrapper.php'; }
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=cachehive-settings' ) ) . '">' . __( 'Settings', 'cache-hive' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
