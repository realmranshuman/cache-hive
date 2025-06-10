<?php
// if uninstall.php is not called by WordPress, die.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Delete options.
delete_option( 'cachehive' );
delete_site_option( 'cachehive' );

// Clear any scheduled cron jobs.
wp_clear_scheduled_hook( 'cachehive_purge_expired_cache' );

// Note: We are intentionally not deleting the cache directory itself,
// as the user might want to keep it or it might contain files from other plugins.
// A more aggressive cleanup could be added here if desired.
