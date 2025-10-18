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
 */
final class Cache_Hive_Admin_Bar {

	/**
	 * Initializes the admin bar hooks.
	 *
	 * @since 1.0.0
	 */
	public static function init() {
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
	 * @since 1.0.0
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance, passed by the hook.
	 */
	public static function add_admin_bar_menu( WP_Admin_Bar $wp_admin_bar ) {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( is_object( $screen ) && method_exists( $screen, 'is_block_editor' ) && $screen->is_block_editor() ) {
				return;
			}
		}

		$main_menu_args = array(
			'id'    => 'cache-hive',
			'title' => '<span class="ab-icon dashicons-performance"></span><span class="ab-label">' . esc_html__( 'Cache Hive', 'cache-hive' ) . '</span>',
			'href'  => admin_url( 'admin.php?page=cache-hive' ),
		);

		$wp_admin_bar->add_node( $main_menu_args );

		self::add_purge_options( $wp_admin_bar );
	}

	/**
	 * Adds the cache purge options to the admin bar submenu.
	 *
	 * @since 1.0.0
	 * @param WP_Admin_Bar $wp_admin_bar The WP_Admin_Bar instance.
	 */
	private static function add_purge_options( WP_Admin_Bar $wp_admin_bar ) {
		$hourglass_icon = '<span class="cache-hive-hourglass">&#10711;</span>';

		if ( ! is_admin() ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'cache-hive-purge-current-page',
					'parent' => 'cache-hive',
					'title'  => esc_html__( 'Purge This Page', 'cache-hive' ) . $hourglass_icon,
					'href'   => '#',
					'meta'   => array(
						'class' => 'cache-hive-purge-action',
					),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-disk-cache',
				'parent' => 'cache-hive',
				'title'  => esc_html__( 'Purge Disk Cache', 'cache-hive' ) . $hourglass_icon,
				'href'   => '#',
				'meta'   => array(
					'class' => 'cache-hive-purge-action',
				),
			)
		);

		if ( Cache_Hive_Settings::get( 'object_cache_enabled', false ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'     => 'cache-hive-purge-object-cache',
					'parent' => 'cache-hive',
					'title'  => esc_html__( 'Purge Object Cache', 'cache-hive' ) . $hourglass_icon,
					'href'   => '#',
					'meta'   => array(
						'class' => 'cache-hive-purge-action',
					),
				)
			);
		}

		$wp_admin_bar->add_node(
			array(
				'id'     => 'cache-hive-purge-all',
				'parent' => 'cache-hive',
				'title'  => esc_html__( 'Purge All Caches', 'cache-hive' ) . $hourglass_icon,
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
	 * @since 1.0.0
	 */
	public static function add_inline_scripts_and_styles() {
		$nonce = wp_create_nonce( 'wp_rest' );
		?>
		<style>
			#wpadminbar .cache-hive-purge-action .cache-hive-hourglass {display: none;color: #a0a5aa;transition: transform 0.3s ease;margin-left: 5px;vertical-align: middle;font-size: 16px;line-height: 1;}
			#wpadminbar li.cache-hive-purge-action.purging {pointer-events: none;opacity: 0.7;}
			#wpadminbar .cache-hive-purge-action.purging .cache-hive-hourglass {display: inline-block;animation: cache-hive-spin 1.5s linear infinite;}
			@keyframes cache-hive-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
		</style>
		<script type="text/javascript">
			document.addEventListener('DOMContentLoaded', function() {
				const purgeLinks = document.querySelectorAll('#wp-admin-bar-cache-hive-default .cache-hive-purge-action > a');
				purgeLinks.forEach(function(link) {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						e.stopPropagation();
						const listItem = this.parentElement;
						if (listItem.classList.contains('purging')) { return; }
						const rawId = listItem.id;
						if (!rawId) { return; }
						const action = rawId.replace('wp-admin-bar-cache-hive-', '').replace(/-/g, '_');
						listItem.classList.add('purging');
						const apiEndpoint = '<?php echo esc_url_raw( rest_url( 'cache-hive/v1/actions/' ) ); ?>' + action;
						let postData = {};
						if (action === 'purge_current_page') {
							postData.url = window.location.href;
						}
						fetch(apiEndpoint, {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': '<?php echo esc_js( $nonce ); ?>' },
							body: JSON.stringify(postData)
						})
						.then(response => { if (!response.ok) { throw new Error('Network response was not ok.'); } return response.json(); })
						.then(data => { console.log('Cache Hive:', data.message); })
						.catch(error => { console.error('Cache Hive Purge Error:', error); })
						.finally(() => { setTimeout(() => { listItem.classList.remove('purging'); }, 500); });
					});
				});
			});
		</script>
		<?php
	}
}
