<?php
/**
 * Integrates image optimization features into the WordPress Media Library.
 *
 * This class is responsible for rendering the UI components (custom column, metabox)
 * and handling the backend AJAX requests for image optimization.
 *
 * @package   Cache_Hive
 * @since     1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

// Note: You may need to adjust the namespace for Cache_Hive_Image_Meta if it's different.
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Meta;

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
			// The get_optimization_html() method returns a sanitized string.
			echo wp_kses_post( $this->get_optimization_html( $attachment_id, '' ) );
		}
	}

	/**
	 * Adds the optimization metabox to the attachment edit screen's submitbox.
	 *
	 * @since 1.0.0
	 */
	public function render_submitbox_metabox() {
		global $post;
		if ( $post && \wp_attachment_is_image( $post->ID ) ) {
			echo '<div class="misc-pub-section misc-pub-cache-hive">';
			echo '<h4>' . \esc_html__( 'Cache Hive', 'cache-hive' ) . '</h4>';
			// The get_optimization_html() method returns a sanitized string.
			echo wp_kses_post( $this->get_optimization_html( $post->ID, '' ) );
			echo '</div>';
		}
	}

	/**
	 * Generates the HTML for the optimization status and action buttons.
	 *
	 * @since 1.1.0
	 * @access private
	 *
	 * @param int    $attachment_id The ID of the attachment.
	 * @param string $single_format Optional. If provided, returns HTML for only this format.
	 * @return string The generated and sanitized HTML.
	 */
	private function get_optimization_html( int $attachment_id, string $single_format = '' ): string {
		if ( ! \wp_attachment_is_image( $attachment_id ) ) {
			return \esc_html__( 'Not an image.', 'cache-hive' );
		}

		$meta    = Cache_Hive_Image_Meta::get_meta( $attachment_id );
		$formats = $single_format ? array( $single_format ) : array( 'webp', 'avif' );

		\ob_start();

		// The main wrapper is only added when rendering ALL formats initially.
		if ( ! $single_format ) {
			echo '<div class="cache-hive-optimization-wrapper" data-id="' . \esc_attr( $attachment_id ) . '">';
		}

		foreach ( $formats as $format ) {
			$format_meta = $meta[ $format ] ?? array();
			$status      = $format_meta['status'] ?? 'pending';
			$savings     = $format_meta['savings'] ?? 0;
			$error       = $format_meta['error'] ?? '';

			// This container is for a single format and will be targeted by JS for replacement.
			echo '<div class="cache-hive-media-actions" data-format="' . \esc_attr( $format ) . '" style="padding:10px 12px; border:1px solid #ddd; border-radius:8px; margin-bottom:10px; background:#fafafa;">';

			echo '<h4 style="margin:0 0 8px; font-weight:600; font-size:13px; text-transform:uppercase;">' . \esc_html( strtoupper( $format ) ) . '</h4>';

			if ( in_array( $status, array( 'pending', 'failed', 'unoptimized' ), true ) ) {
				echo '<button type="button" class="button button-secondary cache-hive-optimize-now">' . \esc_html__( 'Optimize Now', 'cache-hive' ) . '</button>';
				if ( 'failed' === $status && ! empty( $error ) ) {
					echo '<p class="error-notice" style="color:#b32d2e; margin-top:6px;" title="' . \esc_attr( $error ) . '">' . \esc_html__( 'Last attempt failed.', 'cache-hive' ) . '</p>';
				}
			} elseif ( 'in-progress' === $status ) {
				echo '<p class="in-progress-notice" style="color:#646970;">' . \esc_html__( 'Optimizing...', 'cache-hive' ) . '</p>';
			} elseif ( 'optimized' === $status ) {
				$original_size = $meta['original_size'] ?? 0;
				$percentage    = ( $original_size > 0 ) ? \round( ( $savings / $original_size ) * 100, 1 ) : 0;

				echo '<div class="optimization-stats">';
				echo '<p style="margin:0 0 6px;"><strong>' . \esc_html__( 'Savings:', 'cache-hive' ) . '</strong> ' . \esc_html( \size_format( $savings ) ) . ' (' . \esc_html( $percentage ) . '%)</p>';
				echo '<button type="button" class="button button-secondary cache-hive-restore-image">' . \esc_html__( 'Restore Original', 'cache-hive' ) . '</button>';
				echo '</div>';
			}
			echo '</div>'; // End .cache-hive-media-actions.
		}

		if ( ! $single_format ) {
			echo '</div>'; // End .cache-hive-optimization-wrapper.
		}

		return \ob_get_clean();
	}

	/**
	 * AJAX handler to trigger optimization for a single image format.
	 *
	 * @since 1.0.0
	 */
	public function ajax_optimize_image() {
		\check_ajax_referer( 'cache-hive-admin-nonce', 'nonce' );
		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? \absint( $_POST['attachment_id'] ) : 0;
		$format        = isset( $_POST['format'] ) ? \sanitize_key( $_POST['format'] ) : '';

		if ( ! $attachment_id || ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			\wp_send_json_error( array( 'message' => 'Invalid attachment ID or format.' ), 400 );
		}

		Cache_Hive_Image_Optimizer::optimize_attachment( $attachment_id, $format );
		\wp_cache_delete( $attachment_id, 'post_meta' );

		// Return only the HTML for the updated format.
		\wp_send_json_success( array( 'html' => $this->get_optimization_html( $attachment_id, $format ) ) );
	}

	/**
	 * AJAX handler to restore an image to its original state for a specific format.
	 *
	 * @since 1.0.0
	 */
	public function ajax_restore_image() {
		\check_ajax_referer( 'cache-hive-admin-nonce', 'nonce' );
		if ( ! \current_user_can( 'upload_files' ) ) {
			\wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}
		$attachment_id = isset( $_POST['attachment_id'] ) ? \absint( $_POST['attachment_id'] ) : 0;
		$format        = isset( $_POST['format'] ) ? \sanitize_key( $_POST['format'] ) : '';

		if ( ! $attachment_id || ! in_array( $format, array( 'webp', 'avif' ), true ) ) {
			\wp_send_json_error( array( 'message' => 'Invalid attachment ID or format.' ), 400 );
		}

		Cache_Hive_Image_Optimizer::revert_attachment_format( $attachment_id, $format );
		\wp_cache_delete( $attachment_id, 'post_meta' );

		// Return only the HTML for the updated format.
		\wp_send_json_success( array( 'html' => $this->get_optimization_html( $attachment_id, $format ) ) );
	}
}
