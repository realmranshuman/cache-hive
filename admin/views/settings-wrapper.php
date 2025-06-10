<?php
/**
 * Admin settings wrapper template.
 *
 * @package CacheHive
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Verify nonce and check admin permissions.
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cache-hive' ) );
}

// Verify nonce for tab switching.
if ( isset( $_GET['_wpnonce'] ) && ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wpnonce'] ) ), 'cachehive_tab_nonce' ) ) {
	wp_die( esc_html__( 'Invalid nonce specified', 'cache-hive' ) );
}

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'page_cache';
?>
<div class="wrap cachehive-wrap">
	<h1><?php esc_html_e( 'Cache Settings', 'cache-hive' ); ?></h1>
	<?php settings_errors(); ?>

	<nav class="nav-tab-wrapper">
		<a href="?page=cachehive-settings&tab=page_cache" class="nav-tab <?php echo 'page_cache' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Page Cache', 'cache-hive' ); ?></a>
		<a href="?page=cachehive-settings&tab=exclusions" class="nav-tab <?php echo 'exclusions' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Exclusions', 'cache-hive' ); ?></a>
	</nav>

	<div class="cachehive-tab-content">
		<form method="post" action="options.php">
			<?php settings_fields( 'cachehive_settings_group' ); ?>
			<div class="postbox-container">
				<div id="poststuff">
					<div class="postbox">
						<div class="inside">
							<table class="form-table">
								<?php
								if ( 'page_cache' === $active_tab ) {
									do_settings_fields( 'cachehive_page_cache_settings', 'cachehive_page_cache_section' );
								} elseif ( 'exclusions' === $active_tab ) {
									do_settings_fields( 'cachehive_exclusions_settings', 'cachehive_exclusions_section' );
								}
								?>
							</table>
						</div>
					</div>
					<p class="submit">
						<?php submit_button( __( 'Save Changes', 'cache-hive' ), 'primary', 'submit', false ); ?>
						<span style="margin-left: 10px;"><?php submit_button( __( 'Save & Clear Site Cache', 'cache-hive' ), 'secondary', 'cachehive_clear_cache_submit', false ); ?></span>
					</p>
				</div>
			</div>
		</form>
	</div>
</div>
