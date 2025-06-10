<?php
/**
 * Settings Management Class
 *
 * Handles all plugin settings operations including reading, writing,
 * and rendering settings fields.
 *
 * @package CacheHive
 */

namespace CacheHive\Includes;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Settings
 *
 * Manages plugin settings and renders settings fields.
 */
class Settings {

	/**
	 * Holds the plugin options.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Constructor.
	 *
	 * Initializes the settings by loading options from WordPress.
	 */
	public function __construct() {
		$this->options = get_option( CACHEHIVE_SETTINGS_SLUG, $this->get_default_settings() );
	}

	/**
	 * Get a specific option value.
	 *
	 * @param string $key     Option key to retrieve.
	 * @param mixed  $fallback Fallback value if option is not set.
	 * @return mixed Option value or fallback.
	 */
	public function get_option( $key, $fallback = null ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $fallback;
	}

	/**
	 * Get default settings.
	 *
	 * @return array Default settings array.
	 */
	public function get_default_settings() {
		$default_paths   = "/wp-admin/\n/wp-login.php\n/wp-cron.php\n/xmlrpc.php\n/wp-json/\n/my-account/";
		$default_cookies = "comment_author\nwordpress_logged_in\nwp-postpass";

		return array(
			// Page Cache settings.
			'page_cache_enabled'   => 0,
			'cache_lifespan'       => 10,
			'mobile_cache_enabled' => 0,
			'minify_html_enabled'  => 0,
			// Exclusion settings.
			'excluded_url_paths'   => $default_paths,
			'excluded_cookies'     => $default_cookies,
			// Cloudflare settings.
			'cloudflare_api_token' => '',
			'cloudflare_zone_id'   => '',
		);
	}

	/**
	 * Sanitize and validate settings input.
	 *
	 * @param array $input Raw input array.
	 * @return array Sanitized output array.
	 */
	public function sanitize( $input ) {
		$current_options  = (array) get_option( CACHEHIVE_SETTINGS_SLUG, $this->get_default_settings() );
		$sanitized_output = $current_options;

		if ( isset( $_POST['cachehive_clear_cache_submit'] )
			&& isset( $_POST['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'cachehive_settings' )
		) {
			do_action( 'cachehive_manual_clear_cache_request' );
			add_settings_error( 'cachehive-notices', 'cache-cleared', __( 'Site cache has been cleared.', 'cache-hive' ), 'updated' );
		}

		foreach ( $this->get_default_settings() as $key => $default_value ) {
			if ( ! isset( $input[ $key ] ) ) {
				if ( is_int( $default_value ) ) {
					$sanitized_output[ $key ] = 0; }
				continue;
			}
			if ( 'excluded_url_paths' === $key || 'excluded_cookies' === $key ) {
				$sanitized_output[ $key ] = implode( "\n", array_map( 'sanitize_text_field', explode( "\n", $input[ $key ] ) ) );
			} elseif ( is_string( $default_value ) ) {
				$sanitized_output[ $key ] = sanitize_text_field( trim( $input[ $key ] ) );
			} else {
				$sanitized_output[ $key ] = absint( $input[ $key ] );
			}
		}

		$this->compile_local_exclusions( $sanitized_output );
		return $sanitized_output;
	}

	/**
	 * Compile local exclusions into a PHP file.
	 *
	 * @param array $settings Current settings array.
	 */
	private function compile_local_exclusions( $settings ) {
		$config_dir = WP_CONTENT_DIR . '/cache/cache-hive/config';
		if ( ! is_dir( $config_dir ) ) {
			wp_mkdir_p( $config_dir ); }
		$config_file      = $config_dir . '/exclusions-local.php';
		$excluded_paths   = array_filter( array_map( 'trim', explode( "\n", $settings['excluded_url_paths'] ?? '' ) ) );
		$excluded_cookies = array_filter( array_map( 'trim', explode( "\n", $settings['excluded_cookies'] ?? '' ) ) );
		$php_code         = "<?php\n// CacheHive Local Exclusions - Auto-generated\n\n";
		$conditions       = array();
		if ( ! empty( $excluded_paths ) ) {
			$path_conditions = array();
			foreach ( $excluded_paths as $path ) {
				$path_conditions[] = "strpos(\$_SERVER['REQUEST_URI'], '" . addslashes( $path ) . "') !== false";
			}
			$conditions[] = '( ' . implode( ' || ', $path_conditions ) . ' )';
		}
		if ( ! empty( $excluded_cookies ) ) {
			$cookie_conditions = array();
			foreach ( $excluded_cookies as $cookie_name ) {
				$cookie_conditions[] = "strpos(\$cookie_header, '" . addslashes( $cookie_name ) . "') !== false";
			}
			$php_code    .= "\$cookie_header = isset(\$_SERVER['HTTP_COOKIE']) ? \$_SERVER['HTTP_COOKIE'] : '';\n";
			$conditions[] = '( ' . implode( ' || ', $cookie_conditions ) . ' )';
		}
		$php_code .= 'if ( ' . ( empty( $conditions ) ? 'false' : implode( ' || ', $conditions ) ) . " ) { return true; }\n\nreturn false;\n";
		file_put_contents( $config_file, $php_code, LOCK_EX );
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_checkbox_field( $args ) {
		$option_name = $args['label_for'];
		$value       = $this->get_option( $option_name, 0 );
		echo '<label><input type="checkbox" id="' . esc_attr( $option_name ) . '" name="' . esc_attr( CACHEHIVE_SETTINGS_SLUG . '[' . $option_name . ']' ) . '" value="1" ' . checked( 1, $value, false ) . ' /> ';
		echo ' ' . esc_html( $args['description'] ?? '' ) . '</label>';
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_number_field( $args ) {
		$option_name = $args['label_for'];
		$value       = $this->get_option( $option_name, 0 );
		$suffix      = isset( $args['suffix'] ) ? ' ' . esc_html( $args['suffix'] ) : '';
		echo '<input type="number" id="' . esc_attr( $option_name ) . '" name="' . esc_attr( CACHEHIVE_SETTINGS_SLUG . '[' . $option_name . ']' ) . '" value="' . esc_attr( $value ) . '" class="small-text" /> ' . wp_kses_post( $suffix );
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render a textarea field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_textarea_field( $args ) {
		$option_name = $args['label_for'];
		$value       = $this->get_option( $option_name, '' );
		echo '<textarea id="' . esc_attr( $option_name ) . '" name="' . esc_attr( CACHEHIVE_SETTINGS_SLUG . '[' . $option_name . ']' ) . '" rows="6" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render a text field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_text_field( $args ) {
		$option_name = $args['label_for'];
		$value       = $this->get_option( $option_name, '' );
		echo '<input type="text" id="' . esc_attr( $option_name ) . '" name="' . esc_attr( CACHEHIVE_SETTINGS_SLUG . '[' . $option_name . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Render a password field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_password_field( $args ) {
		$option_name = $args['label_for'];
		$value       = $this->get_option( $option_name, '' );
		echo '<input type="password" id="' . esc_attr( $option_name ) . '" name="' . esc_attr( CACHEHIVE_SETTINGS_SLUG . '[' . $option_name . ']' ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
		}
	}
}
