<?php
/**
 * Integrates image optimization features into the WordPress Media Library.
 *
 * This class is responsible for rendering the UI components (custom column, metabox)
 * and handling the backend AJAX requests for image optimization. It does NOT
 * enqueue its own scripts; that is handled by the main plugin file.
 *
 * @package   Cache_Hive
 * @since     1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

use Cache_Hive\Includes\Cache_Hive_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Media Library columns and AJAX actions.
 */
final class Cache_Hive_Media_Integration {

	/**
	 * Constructor to add all WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		if ( ! \is_admin() ) {
			return;
		}
		// NOTE: The 'admin_enqueue_scripts' hook has been removed from this class.
		\add_filter( 'manage_media_columns', array( $this, 'add_media_column' ) );
		\add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		\add_action( 'attachment_submitbox_misc_actions', array( $this, 'render_submitbox_metabox' ) );
		\add_action( 'wp_ajax_cache_hive_optimize_image', array( $this, 'ajax_optimize_image' ) );
		\add_action( 'wp_ajax_cache_hive_restore_image', array( $this, 'ajax_restore_image' ) );
	}

	/**
	 * Adds the "Cache Hive" column to the Media Library list view.
	 *
	 * @since 1.0.0
	 * @param  array $columns An array of existing columns.
	 * @return array The modified array of columns.
	 */
	public function add_media_column( array $columns ): array {
		$columns['cache_hive_optimization'] = esc_html__( 'Cache Hive', 'cache-hive' );
		return $columns;
	}

	/**
	 * Renders the content for the custom "Cache Hive" column.
	 *
	 * @since 1.0.0
	 * @param string $column_name   The name of the column to render.
	 * @param int    $attachment_id The ID of the attachment.
	 */
	public function render_media_column( string $column_name, int $attachment_id ) {
		if ( 'cache_hive_optimization' === $column_name ) {
			echo wp_kses_post( $this->get_optimization_html( $attachment_id ) );
		}
	}

	/**
	 * Adds the optimization metabox to the attachment edit screen's submitbox.
	 *
	 * @since 1.0.0
	 */
	public function render_submitbox_metabox() {
		global $post;
		echo '<div class="misc-pub-section misc-pub-cache-hive" data-id="' . \esc_attr( $post->ID ) . '">';
		echo '<h4>' . \esc_html__( 'Cache Hive', 'cache-hive' ) . '</h4>';
		echo \wp_kses_post( $this->get_optimization_html( $post->ID ) );
		echo '</div>';
	}

	/**
	 * Generates the HTML for the optimization status and action buttons.
	 *
	 * @since 1.0.0
	 * @access private
	 * @param  int $attachment_id The ID of the attachment.
	 * @return string The generated HTML.
	 */
	private function get_optimization_html( int $attachment_id ): string {
		if ( ! \wp_attachment_is_image( $attachment_id ) ) {
			return \esc_html__( 'Not an image.', 'cache-hive' );
		}
		$settings      = Cache_Hive_Settings::get_settings();
		$target_format = $settings['image_next_gen_format'] ?? 'webp';
		$meta          = Cache_Hive_Image_Meta::get_meta( $attachment_id );
		$format_meta   = $meta[ $target_format ] ?? array();
		$status        = $format_meta['status'] ?? 'pending';
		$savings       = $format_meta['savings'] ?? 0;
		$error         = $format_meta['error'] ?? '';

		\ob_start();
		?>
		<div class="cache-hive-media-actions" data-id="<?php echo \esc_attr( $attachment_id ); ?>">
			<?php if ( 'pending' === $status || 'failed' === $status || 'unoptimized' === $status ) : ?>
				<button type="button" class="button button-secondary cache-hive-optimize-now" data-format="<?php echo esc_attr( $target_format ); ?>"><?php \esc_html_e( 'Optimize Now', 'cache-hive' ); ?></button>
				<?php if ( 'failed' === $status && ! empty( $error ) ) : ?>
					<p class="error-notice" title="<?php echo \esc_attr( $error ); ?>"><?php \esc_html_e( 'Last attempt failed.', 'cache-hive' ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'in-progress' === $status ) : ?>
				<p class="in-progress-notice"><?php \esc_html_e( 'Optimizing...', 'cache-hive' ); ?></p>
			<?php elseif ( 'optimized' === $status ) : ?>
				<div class="optimization-stats">
					<p>
						<strong><?php \esc_html_e( 'Savings:', 'cache-hive' ); ?></strong>
						<?php echo \esc_html( \size_format( $savings ) ); ?>
						(<?php echo ( isset( $meta['original_size'] ) && $meta['original_size'] > 0 ) ? \esc_html( \round( ( $savings / $meta['original_size'] ) * 100, 1 ) ) : 0; ?>%)
					</p>
					<button type="button" class="button button-secondary cache-hive-restore-image" data-format="<?php echo esc_attr( $target_format ); ?>"><?php \esc_html_e( 'Restore Original', 'cache-hive' ); ?></button>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return \ob_get_clean();
	}

	/**
	 * AJAX handler to trigger optimization for a single image.
	 *
	 * @since 1.0.0
	 */
	public function ajax_optimize_image() {
		\check_ajax_referer( 'cache-hive-admin-nonce', 'nonce' );
		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? \absint( $_POST['attachment_id'] ) : 0;
		$format        = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : '';

		if ( ! $attachment_id || ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			\wp_send_json_error( array( 'message' => 'Invalid attachment ID or format was provided.' ), 400 );
		}

		$result = Cache_Hive_Image_Optimizer::optimize_attachment( $attachment_id, $format );
		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}
		\wp_cache_delete( $attachment_id, 'post_meta' );
		\wp_send_json_success( array( 'html' => $this->get_optimization_html( $attachment_id ) ) );
	}

	/**
	 * AJAX handler to restore an image to its original state.
	 *
	 * @since 1.0.0
	 */
	public function ajax_restore_image() {
		\check_ajax_referer( 'cache-hive-admin-nonce', 'nonce' );
		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? \absint( $_POST['attachment_id'] ) : 0;
		$format        = isset( $_POST['format'] ) ? sanitize_key( $_POST['format'] ) : '';

		if ( ! $attachment_id || ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			\wp_send_json_error( array( 'message' => 'Invalid attachment ID or format was provided.' ), 400 );
		}

		Cache_Hive_Image_Optimizer::revert_attachment_format( $attachment_id, $format );
		\wp_cache_delete( $attachment_id, 'post_meta' );
		\wp_send_json_success( array( 'html' => $this->get_optimization_html( $attachment_id ) ) );
	}
}
