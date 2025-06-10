<?php
/**
 * Admin Menu Class
 *
 * Handles all admin menu registration and settings page rendering.
 *
 * @package CacheHive
 * @subpackage Admin
 */

namespace CacheHive\Includes\Admin;

use CacheHive\Includes\Settings;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Manages ALL admin menus, pages, sections, and fields for CacheHive.
 * This class is the single source of truth for the entire admin UI structure.
 */
class Admin_Menu {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
		$plugin_basename = plugin_basename( CACHEHIVE_PLUGIN_DIR . 'cache-hive.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_plugin_action_links' ) );
	}

	/**
	 * Register all admin menus and submenus.
	 */
	public function register_menus() {
		// Add main menu and submenus.
		add_menu_page(
			'CacheHive',
			'CacheHive',
			'manage_options',
			'cachehive',
			array( $this, 'render_dashboard_page' ),
			'data:image/svg+xml;base64,' . base64_encode( file_get_contents( CACHEHIVE_PLUGIN_DIR . 'assets/images/icon.svg' ) ),
			81
		);
		add_submenu_page(
			'cachehive',
			__( 'Dashboard', 'cache-hive' ),
			__( 'Dashboard', 'cache-hive' ),
			'manage_options',
			'cachehive',
			array( $this, 'render_dashboard_page' )
		);
		add_submenu_page(
			'cachehive',
			__( 'Cache Settings', 'cache-hive' ),
			__( 'Cache Settings', 'cache-hive' ),
			'manage_options',
			'cachehive-settings',
			array( $this, 'render_settings_page' )
		);
		add_submenu_page(
			'cachehive',
			__( 'CF Integration', 'cache-hive' ),
			__( 'CF Integration', 'cache-hive' ),
			'manage_options',
			'cachehive-cloudflare',
			array( $this, 'render_cloudflare_page' )
		);
	}

	/**
	 * Register all plugin settings, sections, and fields.
	 */
	public function register_plugin_settings() {
		register_setting( 'cachehive_settings_group', CACHEHIVE_SETTINGS_SLUG, array( $this->settings, 'sanitize' ) );

		// --- Fields for "Cache Settings" -> "Page Cache" Tab ---
		$page_cache_slug = 'cachehive_page_cache_settings';
		add_settings_section( 'cachehive_page_cache_section', null, null, $page_cache_slug );
		add_settings_field(
			'page_cache_enabled',
			__( 'Enable Caching', 'cache-hive' ),
			array( $this->settings, 'render_checkbox_field' ),
			$page_cache_slug,
			'cachehive_page_cache_section',
			array( 'label_for' => 'page_cache_enabled' )
		);
		add_settings_field(
			'mobile_cache_enabled',
			__( 'Separate Mobile Cache', 'cache-hive' ),
			array( $this->settings, 'render_checkbox_field' ),
			$page_cache_slug,
			'cachehive_page_cache_section',
			array( 'label_for' => 'mobile_cache_enabled' )
		);
		add_settings_field(
			'minify_html_enabled',
			__( 'Minify HTML', 'cache-hive' ),
			array( $this->settings, 'render_checkbox_field' ),
			$page_cache_slug,
			'cachehive_page_cache_section',
			array( 'label_for' => 'minify_html_enabled' )
		);
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
		$api_token_description = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" class="button button-secondary">%s</a><p class="description">%s</p><ul class="description" style="list-style:disc;margin-left:20px;"><li>%s</li></ul>',
			esc_url( $api_token_url ),
			__( 'Get Your API Token', 'cache-hive' ),
			__( 'This will pre-configure a token with all necessary permissions:', 'cache-hive' ),
			implode(
				'</li><li>',
				array(
					'Zone Permissions: Cache Purge, Zone Settings, etc.',
					'Account Permissions: Analytics, Workers, etc.',
				)
			)
		);
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

	/**
	 * Render the dashboard page.
	 */    public function render_dashboard_page() {
        require_once CACHEHIVE_PLUGIN_DIR . 'admin/views/dashboard-wrapper.php';
    }

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		require_once CACHEHIVE_PLUGIN_DIR . 'admin/views/settings-wrapper.php';
	}

	/**
	 * Render the Cloudflare integration page.
	 */
	public function render_cloudflare_page() {
		require_once CACHEHIVE_PLUGIN_DIR . 'admin/views/cloudflare-wrapper.php';
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Existing plugin action links.
	 * @return array Modified plugin action links.
	 */
	public function add_plugin_action_links( $links ) {
		$settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=cachehive-settings' ) ) . '">' .
			__( 'Settings', 'cache-hive' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
