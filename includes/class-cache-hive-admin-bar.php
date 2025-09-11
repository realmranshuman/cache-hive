<?php
/**
 * Manages the Cache Hive admin bar menu and its interactive functionality.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes;

use WP_Admin_Bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Final class for managing the Cache Hive admin bar menu.
 *
 * This class is responsible for adding the main menu and all purge
 * options to the WordPress admin bar. It also injects the necessary
 * inline CSS and JavaScript to handle the REST API-based purging
 * without page reloads.
 */
final class Cache_Hive_Admin_Bar {

	/**
	 * Initializes the admin bar hooks.
	 *
	 * This method hooks into WordPress to add the menu and the necessary
	 * assets (inline CSS/JS) for users with 'manage_options' capability.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
		// Only add the menu for users who can manage options and when the admin bar is showing.
		if ( ! current_user_can( 'manage_options' ) || ! is_admin_bar_showing() ) {
			return;
		}

		add_action( 'admin_bar_menu', array( __CLASS__, 'add_admin_bar_menu' ), 100 );
		add_action( 'wp_footer', array( __CLASS__, 'add_inline_scripts_and_styles' ) );
		add_action( 'admin_footer', array( __CLASS__, 'add_inline_scripts_and_styles' ) );
	}

	/**
	 * Adds the Cache Hive menu to the WordPress admin bar.
	 *
	 * This is the main callback function for the 'admin_bar_menu' hook. It adds
	 * the top-level menu item and then calls a helper to add the submenu items.
	 *
	 * @since 1.0.0
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance, passed by the hook.
	 */
	public static function add_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
		// Do not add the menu on block-based theme editors to avoid UI conflicts.
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
				return;
			}
		}

		$main_menu_args = array(
			'id'    => 'cache-hive',
			'title' => '<span class="ab-icon dashicons-performance"></span><span class="ab-label">' . __( 'Cache Hive', 'cache-hive' ) . '</span>',
			'href'  => admin_url( 'admin.php?page=cache-hive' ),
		);

		$wp_admin_bar->add_node( $main_menu_args );

		// Add all the purge options as submenu items.
		self::add_purge_options( $wp_admin_bar );
	}

	/**
	 * Adds the cache purge options to the admin bar submenu.
	 *
	 * This private helper method creates and adds all the individual purge
	 * actions to the 'Cache Hive' top-level menu.
	 *
	 * @since 1.0.0
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	private static function add_purge_options( WP_Admin_Bar $wp_admin_bar ) {
		// The hourglass icon is always in the markup but hidden by default via CSS.
		$hourglass_icon = '<span class="cache-hive-hourglass">&#10711;</span>';

		// Option: Purge Current Page (Frontend only).
		if ( ! is_admin() ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'cache-hive-purge-current-page',
					'parent' => 'cache-hive',
					'title'  => __( 'Purge This Page', 'cache-hive' ) . $hourglass_icon,
					'href'   => '#',
					'meta'   => array(
						'class' => 'cache-hive-purge-action',
					),
				)
			);
		}

		// Option: Purge Disk Cache.
		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-disk-cache',
				'parent' => 'cache-hive',
				'title'  => __( 'Purge Disk Cache', 'cache-hive' ) . $hourglass_icon,
				'href'   => '#',
				'meta'   => array(
					'class' => 'cache-hive-purge-action',
				),
			)
		);

		// Option: Purge Object Cache (only shown if the feature is enabled).
		if ( Cache_Hive_Settings::get( 'object_cache_enabled', false ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'cache-hive-purge-object-cache',
					'parent' => 'cache-hive',
					'title'  => __( 'Purge Object Cache', 'cache-hive' ) . $hourglass_icon,
					'href'   => '#',
					'meta'   => array(
						'class' => 'cache-hive-purge-action',
					),
				)
			);
		}

		// Option: Purge All Cache.
		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-all',
				'parent' => 'cache-hive',
				'title'  => __( 'Purge All Caches', 'cache-hive' ) . $hourglass_icon,
				'href'   => '#',
				'meta'   => array(
					'class' => 'cache-hive-purge-action',
				),
			)
		);
	}

	/**
	 * Adds inline CSS and JavaScript for the admin bar purge actions.
	 *
	 * This method injects the necessary code into the footer of both admin
	 * and frontend pages to handle the interactive purging without requiring
	 * external asset files, keeping the plugin lightweight.
	 *
	 * @since 1.0.0
	 */
	public static function add_inline_scripts_and_styles() {
		// A nonce is crucial for securing the REST API endpoint.
		$nonce = wp_create_nonce( 'wp_rest' );
		?>
		<style>
			#wpadminbar .cache-hive-purge-action .cache-hive-hourglass {
				/* The icon is hidden by default. */
				display: none;
				color: #a0a5aa;
				transition: transform 0.3s ease;
				margin-left: 5px;
				vertical-align: middle;
				font-size: 16px;
				line-height: 1;
			}
			#wpadminbar li.cache-hive-purge-action.purging {
				pointer-events: none;
				opacity: 0.7;
			}
			#wpadminbar .cache-hive-purge-action.purging .cache-hive-hourglass {
				/* Display the icon only when the 'purging' class is active. */
				display: inline-block;
				animation: cache-hive-spin 1.5s linear infinite;
			}
			@keyframes cache-hive-spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}
		</style>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				const purgeLinks = document.querySelectorAll('#wp-admin-bar-cache-hive-default .cache-hive-purge-action > a');

				purgeLinks.forEach(function(link) {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();

						const listItem = this.parentElement;
						
						if (listItem.classList.contains('purging')) {
							return; 
						}

						const rawId = listItem.id;
						if (!rawId) {
							return;
						}
						
						const action = rawId.replace('wp-admin-bar-cache-hive-', '').replace(/-/g, '_');

						listItem.classList.add('purging');

						const apiEndpoint = '<?php echo esc_url_raw( rest_url( 'cache-hive/v1/actions/' ) ); ?>' + action;

						let postData = {};
						if (action === 'purge_current_page') {
							postData.url = window.location.href;
						}

						fetch(apiEndpoint, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': '<?php echo esc_js( $nonce ); ?>'
							},
							body: JSON.stringify(postData)
						})
						.then(response => {
							if (!response.ok) {
								throw new Error('Network response was not ok.');
							}
							return response.json();
						})
						.then(data => {
							console.log('Cache Hive:', data.message);
						})
						.catch(error => {
							console.error('Cache Hive Purge Error:', error);
						})
						.finally(() => {
							setTimeout(() => {
								listItem.classList.remove('purging');
							}, 500);
						});
					});
				});
			});
		</script>
		<?php
	}
}
