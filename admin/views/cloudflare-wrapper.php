<?php
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<div class="wrap cachehive-wrap">
	<h1><span class="dashicons-before dashicons-cloudflare" style="font-size: 1.5em; vertical-align: middle; margin-right: 5px;"></span><?php esc_html_e( 'CF Integration', 'cache-hive' ); ?></h1>
	<?php settings_errors(); ?>

	<nav class="nav-tab-wrapper">
		<a href="?page=cachehive-cloudflare&sub-tab=authentication" class="nav-tab nav-tab-active"><?php esc_html_e( 'Authentication', 'cache-hive' ); ?></a>
	</nav>

	<div class="cachehive-tab-content">
		<form method="post" action="options.php">
			<?php settings_fields( 'cachehive_settings_group' ); ?>
			<div class="postbox-container">
				<div id="poststuff">
					<div class="postbox">
						<h2 class="hndle"><span><?php esc_html_e( 'API Credentials', 'cache-hive' ); ?></span></h2>
						<div class="inside">
							<table class="form-table">
								<?php do_settings_fields( 'cachehive_cloudflare_authentication', 'cachehive_cloudflare_auth_section' ); ?>
							</table>
						</div>
					</div>
					<p class="submit">
						<?php submit_button( __( 'Save Credentials', 'cache-hive' ), 'primary', 'submit', false ); ?>
					</p>
				</div>
			</div>
		</form>
	</div>
</div>