<?php
/**
 * Admin Class
 *
 * Main admin orchestrator class that initializes all admin-side components.
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
 * The main admin orchestrator class.
 * Initializes all admin-side components.
 */
class Admin {

	/**
	 * Settings instance.
	 *
	 * @var Settings Plugin settings instance.
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings instance.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		$this->init();
	}

	/**
	 * Initialize admin hooks and dependencies.
	 */
	public function init() {
		// Load the class that handles all menu and settings registration.
		new Admin_Menu( $this->settings );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles_and_scripts' ) );
		add_action( 'admin_notices', array( $this, 'show_configuration_notices' ) );
	}

	/**
	 * Display configuration notices in the admin area.
	 */
	public function show_configuration_notices() {
		$screen = get_current_screen();
		if ( ! $screen || ( 'plugins' !== $screen->id && strpos( $screen->id, 'cachehive' ) === false ) ) {
			return;
		}
		if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
			// translators: %1$s: code snippet, %2$s: config file name.
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'CacheHive Action Required:', 'cache-hive' ) . '</strong> ' . sprintf(
				wp_kses(
					/* translators: %1$s: code snippet to add, %2$s: configuration file name */
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
			// translators: This is a warning message shown when the advanced-cache.php file is missing.
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'CacheHive Warning:', 'cache-hive' ) . '</strong> ' . esc_html__( 'The advanced-cache.php file is missing. Please try deactivating and reactivating the CacheHive plugin.', 'cache-hive' ) . '</p></div>';
		}
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_styles_and_scripts( $hook ) {
		if ( strpos( $hook, 'cachehive' ) === false ) {
			return;
		}
		wp_enqueue_style( 'cachehive-admin-styles', CACHEHIVE_PLUGIN_URL . 'admin/css/cache-hive-admin.css', array(), CACHEHIVE_VERSION );
	}
}
