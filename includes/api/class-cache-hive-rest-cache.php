<?php
/**
 * Cache settings REST API logic for Cache Hive.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Settings;
use Cache_Hive\Includes\Helpers\Cache_Hive_Server_Rules_Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles REST API endpoints for general cache settings.
 */
class Cache_Hive_REST_Cache {

	/**
	 * Retrieves the general cache settings.
	 *
	 * @return WP_REST_Response The response object.
	 */
	public static function get_settings() {
		$settings       = Cache_Hive_Settings::get_settings();
		$server         = Cache_Hive_Server_Rules_Helper::get_server_software();
		$is_apache_like = in_array( $server, array( 'apache', 'litespeed' ), true );

		$response_data = array(
			'enable_cache'                    => (bool) ( $settings['enable_cache'] ?? false ),
			'cache_logged_users'              => (bool) ( $settings['cache_logged_users'] ?? false ),
			'cache_commenters'                => (bool) ( $settings['cache_commenters'] ?? false ),
			'cache_rest_api'                  => (bool) ( $settings['cache_rest_api'] ?? false ),
			'cache_mobile'                    => (bool) ( $settings['cache_mobile'] ?? false ),
			'mobile_user_agents'              => $settings['mobile_user_agents'] ?? array(),
			'is_network_admin'                => is_multisite() && is_network_admin(),
			'is_apache_like'                  => $is_apache_like,
			// CORRECT: Check for the wp-config.php constant, not a database setting.
			'is_logged_in_cache_override_set' => defined( 'CACHE_HIVE_ALLOW_LOGGED_IN_CACHE_ON_NGINX' ) && CACHE_HIVE_ALLOW_LOGGED_IN_CACHE_ON_NGINX,
		);
		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Updates the general cache settings with security checks for logged-in user caching.
	 *
	 * @param WP_REST_Request $request The request object containing the new settings.
	 * @return WP_REST_Response|WP_Error The response object with the updated settings or an error.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$old_settings     = Cache_Hive_Settings::get_settings( true );
		$params           = $request->get_json_params();
		$new_settings     = Cache_Hive_Settings::sanitize_settings( $params );
		$is_network_admin = is_multisite() && is_network_admin();
		$server           = Cache_Hive_Server_Rules_Helper::get_server_software();
		$is_apache_like   = in_array( $server, array( 'apache', 'litespeed' ), true );

		// If user is trying to enable logged-in user caching.
		if ( ! empty( $new_settings['cache_logged_users'] ) ) {
			// On a non-Apache server.
			if ( ! $is_apache_like ) {
				// CORRECT: Check if the wp-config.php override is set.
				if ( ! ( defined( 'CACHE_HIVE_ALLOW_LOGGED_IN_CACHE_ON_NGINX' ) && CACHE_HIVE_ALLOW_LOGGED_IN_CACHE_ON_NGINX ) ) {
					$new_settings['cache_logged_users'] = false; // Force disable if override is not set.
				} else {
					// Override is set, so we MUST verify security.
					$verification = self::run_private_cache_verification();
					if ( ! $verification['verified'] ) {
						// Verification failed, so force disable the setting and return an error.
						$new_settings['cache_logged_users'] = false;
						self::save_settings( $new_settings, $is_network_admin, $old_settings ); // Save with the setting disabled.
						return new WP_Error(
							'cache_insecure',
							$verification['message'],
							array( 'status' => 400 )
						);
					}
				}
			}
		}

		self::save_settings( $new_settings, $is_network_admin, $old_settings );

		return self::get_settings();
	}

	/**
	 * Helper function to save settings and manage lifecycle events.
	 *
	 * @param array $settings_to_save The settings array to save.
	 * @param bool  $is_network_admin Whether the current user is a network admin.
	 * @param array $old_settings The settings before the update.
	 */
	private static function save_settings( array $settings_to_save, bool $is_network_admin, array $old_settings ) {
		if ( $is_network_admin ) {
			update_site_option( 'cache_hive_settings', $settings_to_save );
		} else {
			update_option( 'cache_hive_settings', $settings_to_save, 'yes' );
		}

		Cache_Hive_Lifecycle::create_config_file( $settings_to_save );
		Cache_Hive_Settings::invalidate_settings_snapshot();

		$old_enabled = ! empty( $old_settings['enable_cache'] );
		$new_enabled = ! empty( $settings_to_save['enable_cache'] );

		if ( $new_enabled !== $old_enabled ) {
			if ( $new_enabled ) {
				Cache_Hive_Lifecycle::setup_environment();
			} else {
				Cache_Hive_Lifecycle::cleanup_environment();
			}
		}
	}

	/**
	 * Internal method to run the private cache security verification.
	 *
	 * @return array A result array with 'verified' and 'message' keys.
	 */
	private static function run_private_cache_verification(): array {
		$test_file_name = 'ch_security_test_' . wp_generate_password( 12, false ) . '.txt';
		$test_dir       = CACHE_HIVE_PRIVATE_CACHE_DIR;
		$test_file_path = $test_dir . '/' . $test_file_name;
		$test_file_url  = str_replace( WP_CONTENT_DIR, content_url(), $test_dir ) . '/' . $test_file_name;

		$result = array(
			'verified' => false,
			'message'  => __( 'An unknown verification error occurred.', 'cache-hive' ),
		);

		// 1. Ensure the directory exists.
		if ( ! is_dir( $test_dir ) ) {
			// Try to create it recursively.
			if ( ! wp_mkdir_p( $test_dir ) ) {
				$result['message'] = __( 'Verification failed. The private cache directory could not be created. Please check file permissions.', 'cache-hive' );
				return $result;
			}
		}

		// 2. Check if the directory is writable.
		if ( ! is_writable( $test_dir ) ) {
			$result['message'] = __( 'Verification failed. The private cache directory is not writable. Please check file permissions.', 'cache-hive' );
			return $result;
		}

		// 3. Attempt to write the test file.
		$write_success = file_put_contents( $test_file_path, 'SECURITY_TEST' );

		if ( false !== $write_success ) {
			// 4. File was written, now perform the HTTP check.
			$response      = wp_remote_get(
				$test_file_url,
				array(
					'timeout'   => 10,
					'sslverify' => false,
				)
			);
			$response_body = wp_remote_retrieve_body( $response );

			if ( is_wp_error( $response ) ) {
				// This could happen if loopback requests are disabled.
				$result['verified'] = false;
				// translators: %s: The error message from WordPress.
				$result['message'] = sprintf( __( 'Security Verification Failed: A loopback request to the test file failed. Error: %s', 'cache-hive' ), $response->get_error_message() );
			} elseif ( 'SECURITY_TEST' === $response_body ) {
				// If we got the content back, it's publicly accessible. This is a failure.
				$result['verified'] = false;
				$result['message']  = __( 'Security Verification Failed: The private cache directory is publicly accessible. Logged-in user caching has been disabled. Please correct your Nginx configuration and try again.', 'cache-hive' );
			} else {
				// The request was handled by something else (like WordPress index.php), which is the correct and secure behavior.
				$result['verified'] = true;
				$result['message']  = 'OK';
			}

			// 5. Clean up the test file.
			if ( file_exists( $test_file_path ) ) {
				unlink( $test_file_path );
			}
		} else {
			// 6. File writing failed.
			$result['message'] = __( 'Verification failed. Could not create a test file in the private cache directory. Please check file permissions.', 'cache-hive' );
		}

		return $result;
	}
}
