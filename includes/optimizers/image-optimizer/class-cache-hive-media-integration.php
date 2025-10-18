<?php
/**
 * Integrates image optimization features into the WordPress Media Library.
 *
 * @package Cache_Hive
 * @since 1.0.0
 */

namespace Cache_Hive\Includes\Optimizers\Image_Optimizer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Media Library columns, filters, and AJAX actions.
 */
final class Cache_Hive_Media_Integration {

	/**
	 * Constructor to add all hooks.
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
		\add_action( 'restrict_manage_posts', array( $this, 'add_filter_dropdown' ) );
		\add_action( 'pre_get_posts', array( $this, 'apply_media_filter' ) );
		\add_action( 'wp_ajax_cache_hive_optimize_image', array( $this, 'ajax_optimize_image' ) );
		\add_action( 'wp_ajax_cache_hive_restore_image', array( $this, 'ajax_restore_image' ) );
	}

	/**
	 * Adds the "Cache Hive" column to the Media Library list view.
	 *
	 * @since 1.0.0
	 * @param array $columns An array of existing columns.
	 * @return array The modified array of columns.
	 */
	public function add_media_column( array $columns ): array {
		$columns['cache_hive_optimization'] = esc_html__( 'Cache Hive', 'cache-hive' );
		return $columns;
	}

	/**
	 * Renders the content for the "Cache Hive" column.
	 *
	 * @since 1.0.0
	 * @param string $column_name The name of the column to render.
	 * @param int    $attachment_id The ID of the attachment.
	 */
	public function render_media_column( string $column_name, int $attachment_id ) {
		if ( 'cache_hive_optimization' === $column_name ) {
			echo wp_kses_post( $this->get_optimization_html( $attachment_id ) );
		}
	}

	/**
	 * Adds the optimization metabox to the attachment edit screen.
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
	 * Adds the optimization status filter dropdown to the Media Library.
	 *
	 * @since 1.0.0
	 */
	public function add_filter_dropdown() {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = \get_current_screen();
		if ( ! $screen || 'upload' !== $screen->base ) {
			return;
		}

		$filter = \filter_input( INPUT_GET, 'cache_hive_filter', FILTER_SANITIZE_SPECIAL_CHARS );
		?>
		<label for="cache_hive_filter" class="screen-reader-text"><?php \esc_html_e( 'Filter by Cache Hive status', 'cache-hive' ); ?></label>
		<select name="cache_hive_filter" id="cache_hive_filter">
			<option value=""><?php \esc_html_e( 'All Images (Cache Hive)', 'cache-hive' ); ?></option>
			<option value="optimized" <?php \selected( $filter, 'optimized' ); ?>><?php \esc_html_e( 'Optimized', 'cache-hive' ); ?></option>
			<option value="unoptimized" <?php \selected( $filter, 'unoptimized' ); ?>><?php \esc_html_e( 'Unoptimized', 'cache-hive' ); ?></option>
			<option value="failed" <?php \selected( $filter, 'failed' ); ?>><?php \esc_html_e( 'Failed', 'cache-hive' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Modifies the main query for the Media Library based on the selected filter.
	 *
	 * @since 1.0.0
	 * @param \WP_Query $query The WP_Query instance (passed by reference).
	 */
	public function apply_media_filter( \WP_Query $query ) {
		if ( ! \is_admin() || ! $query->is_main_query() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = \get_current_screen();
		if ( ! $screen || 'upload' !== $screen->base ) {
			return;
		}

		$filter = \filter_input( INPUT_GET, 'cache_hive_filter', FILTER_SANITIZE_SPECIAL_CHARS );
		if ( ! $filter ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );

		if ( 'unoptimized' === $filter ) {
			$meta_query['relation'] = 'OR';
			$meta_query[]           = array(
				'key'     => Cache_Hive_Image_Meta::META_KEY,
				'compare' => 'NOT EXISTS',
			);
			$meta_query[]           = array(
				'key'     => Cache_Hive_Image_Meta::META_KEY,
				'value'   => 's:6:"status";s:9:"optimized";',
				'compare' => 'NOT LIKE',
			);
		} elseif ( 'optimized' === $filter || 'failed' === $filter ) {
			$meta_query[] = array(
				'key'     => Cache_Hive_Image_Meta::META_KEY,
				'value'   => 's:6:"status";s:' . strlen( $filter ) . ':"' . esc_sql( $filter ) . '";',
				'compare' => 'LIKE',
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Generates the HTML for the optimization status and actions.
	 *
	 * @since 1.0.0
	 * @param int $attachment_id The ID of the attachment.
	 * @return string The generated HTML.
	 */
	private function get_optimization_html( int $attachment_id ): string {
		if ( ! \wp_attachment_is_image( $attachment_id ) ) {
			return \esc_html__( 'Not an image.', 'cache-hive' );
		}

		$meta = Cache_Hive_Image_Meta::get_meta( $attachment_id );
		\ob_start();
		?>
		<div class="cache-hive-media-actions" data-id="<?php echo \esc_attr( $attachment_id ); ?>">
			<?php if ( ! $meta || 'unoptimized' === $meta['status'] || 'failed' === $meta['status'] ) : ?>
				<button type="button" class="button button-secondary cache-hive-optimize-now"><?php \esc_html_e( 'Optimize Now', 'cache-hive' ); ?></button>
				<?php if ( 'failed' === ( $meta['status'] ?? '' ) && ! empty( $meta['error'] ) ) : ?>
					<p class="error-notice" title="<?php echo \esc_attr( $meta['error'] ); ?>"><?php \esc_html_e( 'Last attempt failed.', 'cache-hive' ); ?></p>
				<?php endif; ?>
			<?php elseif ( 'in-progress' === $meta['status'] ) : ?>
				<p class="in-progress-notice"><?php \esc_html_e( 'Optimizing...', 'cache-hive' ); ?></p>
			<?php elseif ( 'optimized' === $meta['status'] ) : ?>
				<div class="optimization-stats">
					<p>
						<strong><?php \esc_html_e( 'Savings:', 'cache-hive' ); ?></strong>
						<?php echo \esc_html( \size_format( $meta['savings'] ?? 0 ) ); ?>
						(<?php echo ( isset( $meta['original_size'] ) && $meta['original_size'] > 0 ) ? \esc_html( \round( ( ( $meta['savings'] ?? 0 ) / $meta['original_size'] ) * 100, 1 ) ) : 0; ?>%)
					</p>
					<button type="button" class="button button-secondary cache-hive-restore-image"><?php \esc_html_e( 'Restore Original', 'cache-hive' ); ?></button>
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
		if ( ! $attachment_id ) {
			\wp_send_json_error( array( 'message' => 'Invalid attachment ID.' ), 400 );
		}
		$result = Cache_Hive_Image_Optimizer::optimize_attachment( $attachment_id );
		if ( \is_wp_error( $result ) ) {
			\wp_send_json_error( array( 'message' => $result->get_error_message() ), 500 );
		}

		// STALE CACHE FIX: Explicitly clear the post meta cache for this attachment.
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
		if ( ! $attachment_id ) {
			\wp_send_json_error( array( 'message' => 'Invalid attachment ID.' ), 400 );
		}
		$was_optimized = Cache_Hive_Image_Meta::is_optimized( $attachment_id );
		Cache_Hive_Image_Optimizer::cleanup_on_delete( $attachment_id );
		if ( $was_optimized ) {
			Cache_Hive_Image_Stats::decrement_optimized_count();
		}

		\wp_cache_delete( $attachment_id, 'post_meta' );

		\wp_send_json_success( array( 'html' => $this->get_optimization_html( $attachment_id ) ) );
	}
}
