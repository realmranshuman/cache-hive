<?php
/**
 * REST API handler for Image Optimization settings.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Settings;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Batch_Processor;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Optimizer;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Rewrite;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Stats;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API handler for Image Optimization settings.
 *
 * @since 1.0.0
 */
class Cache_Hive_REST_Optimizers_Image {
	/**
	 * Get image optimization settings and server capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public static function get_settings( WP_REST_Request $request ) {
		$settings       = Cache_Hive_Settings::get_settings();
		$image_settings = array(
			'image_optimization_library'    => $settings['image_optimization_library'] ?? 'gd',
			'image_optimize_losslessly'     => $settings['image_optimize_losslessly'] ?? true,
			'image_optimize_original'       => $settings['image_optimize_original'] ?? true,
			'image_next_gen_format'         => $settings['image_next_gen_format'] ?? 'webp',
			'image_quality'                 => $settings['image_quality'] ?? 80,
			'image_delivery_method'         => $settings['image_delivery_method'] ?? 'rewrite',
			'image_remove_exif'             => $settings['image_remove_exif'] ?? true,
			'image_auto_resize'             => $settings['image_auto_resize'] ?? false,
			'image_max_width'               => $settings['image_max_width'] ?? 1920,
			'image_max_height'              => $settings['image_max_height'] ?? 1080,
			'image_batch_processing'        => $settings['image_batch_processing'] ?? false,
			'image_batch_size'              => $settings['image_batch_size'] ?? 10,
			'image_exclude_images'          => implode( "\n", $settings['image_exclude_images'] ?? array() ),
			'image_exclude_picture_rewrite' => implode( "\n", $settings['image_exclude_picture_rewrite'] ?? array() ),
			'image_selected_thumbnails'     => $settings['image_selected_thumbnails'] ?? array( 'thumbnail', 'medium' ),
			'image_disable_png_gif'         => $settings['image_disable_png_gif'] ?? true,
		);

		$capabilities = array(
			'gd_support'           => extension_loaded( 'gd' ),
			'gd_webp_support'      => function_exists( 'imagewebp' ),
			'gd_avif_support'      => function_exists( 'imageavif' ),
			'imagick_support'      => extension_loaded( 'imagick' ) && class_exists( 'Imagick' ),
			'imagick_version'      => phpversion( 'imagick' ),
			'is_imagick_old'       => Cache_Hive_Image_Optimizer::is_imagick_old(),
			'imagick_webp_support' => defined( 'IMAGICK_HAVE_WEBP' ) && IMAGICK_HAVE_WEBP,
			'imagick_avif_support' => defined( 'IMAGICK_HAVE_AVIF' ) && IMAGICK_HAVE_AVIF,
			'thumbnail_sizes'      => self::get_all_image_sizes_for_rest(),
		);

		$response_data = array(
			'settings'            => $image_settings,
			'server_capabilities' => $capabilities,
			'stats'               => Cache_Hive_Image_Stats::get_stats( true ),
		);

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Update image optimization settings.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public static function update_settings( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$input  = array(
			'image_optimization_library'    => sanitize_text_field( $params['image_optimization_library'] ?? 'gd' ),
			'image_optimize_losslessly'     => (bool) ( $params['image_optimize_losslessly'] ?? true ),
			'image_optimize_original'       => (bool) ( $params['image_optimize_original'] ?? true ),
			'image_next_gen_format'         => sanitize_text_field( $params['image_next_gen_format'] ?? 'webp' ),
			'image_quality'                 => (int) ( $params['image_quality'] ?? 80 ),
			'image_delivery_method'         => sanitize_text_field( $params['image_delivery_method'] ?? 'rewrite' ),
			'image_remove_exif'             => (bool) ( $params['image_remove_exif'] ?? true ),
			'image_auto_resize'             => (bool) ( $params['image_auto_resize'] ?? false ),
			'image_max_width'               => (int) ( $params['image_max_width'] ?? 1920 ),
			'image_max_height'              => (int) ( $params['image_max_height'] ?? 1080 ),
			'image_batch_processing'        => (bool) ( $params['image_batch_processing'] ?? false ),
			'image_batch_size'              => (int) ( $params['image_batch_size'] ?? 10 ),
			'image_exclude_images'          => sanitize_textarea_field( $params['image_exclude_images'] ?? '' ),
			'image_exclude_picture_rewrite' => sanitize_textarea_field( $params['image_exclude_picture_rewrite'] ?? '' ),
			'image_selected_thumbnails'     => is_array( $params['image_selected_thumbnails'] ?? null ) ? array_map( 'sanitize_text_field', $params['image_selected_thumbnails'] ) : array( 'thumbnail', 'medium' ),
			'image_disable_png_gif'         => (bool) ( $params['image_disable_png_gif'] ?? true ),
		);

		// Get old settings before they are updated to compare delivery methods.
		$old_settings        = Cache_Hive_Settings::get_settings();
		$old_delivery_method = $old_settings['image_delivery_method'] ?? 'picture';

		$sanitized = Cache_Hive_Settings::sanitize_settings( $input );

		$all_settings = Cache_Hive_Settings::get_settings();
		foreach ( $sanitized as $key => $value ) {
			$all_settings[ $key ] = $value;
		}

		update_option( 'cache_hive_settings', $all_settings, 'yes' );
		Cache_Hive_Lifecycle::create_config_file( $all_settings );
		Cache_Hive_Settings::invalidate_settings_snapshot();

		// Handle server rewrite rules based on delivery method change.
		$new_delivery_method = $all_settings['image_delivery_method'];
		if ( $new_delivery_method !== $old_delivery_method ) {
			if ( 'rewrite' === $new_delivery_method ) {
				Cache_Hive_Image_Rewrite::insert_rules();
			} else {
				// If the old method was rewrite and new is not, remove the rules.
				if ( 'rewrite' === $old_delivery_method ) {
					Cache_Hive_Image_Rewrite::remove_rules();
				}
			}
		}

		if ( ! empty( $all_settings['image_batch_processing'] ) ) {
			Cache_Hive_Image_Batch_Processor::schedule_event();
		} else {
			Cache_Hive_Image_Batch_Processor::clear_scheduled_event();
		}

		return self::get_settings( $request );
	}

	/**
	 * Deletes all image optimization data.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public static function delete_all_optimization_data( WP_REST_Request $request ) {
		$result = Cache_Hive_Image_Optimizer::delete_all_data();

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 500 );
		}

		return new WP_REST_Response( array( 'message' => 'All image optimization data has been successfully deleted.' ), 200 );
	}

	/**
	 * Handles sync actions like start, get status, and cancel.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_sync_actions( WP_REST_Request $request ) {
		$method = $request->get_method();

		if ( 'POST' === $method ) {
			// Only START the sync. Do NOT process the first batch here.
			$state = Cache_Hive_Image_Stats::start_manual_sync();
			return new WP_REST_Response( $state, 200 );
		}

		if ( 'GET' === $method ) {
			// Process the next batch and return the current status.
			$state = Cache_Hive_Image_Stats::get_sync_state();
			if ( $state && ! $state['is_finished'] ) {
				$state = Cache_Hive_Image_Stats::process_next_manual_batch();
			} elseif ( ! $state ) {
				// If no state exists, return the latest stats instead of an error.
				return new WP_REST_Response( array( 'stats' => Cache_Hive_Image_Stats::get_stats( true ) ), 200 );
			}
			return new WP_REST_Response( $state, 200 );
		}

		if ( 'DELETE' === $method ) {
			// Cancel a sync.
			Cache_Hive_Image_Stats::clear_sync_state();
			return new WP_REST_Response( array( 'message' => 'Sync cancelled.' ), 200 );
		}

		return new WP_REST_Response( array( 'message' => 'Invalid method.' ), 405 );
	}

	/**
	 * Checks if the image optimization stats are "dirty" (stale).
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function get_stats_status( WP_REST_Request $request ) {
		if ( \get_transient( Cache_Hive_Image_Stats::STATS_DIRTY_TRANSIENT ) ) {
			\delete_transient( Cache_Hive_Image_Stats::STATS_DIRTY_TRANSIENT );
			return new WP_REST_Response( array( 'dirty' => true ), 200 );
		}
		return new WP_REST_Response( array( 'dirty' => false ), 200 );
	}


	/**
	 * Helper to get all image sizes formatted for REST response.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	private static function get_all_image_sizes_for_rest() {
		global $_wp_additional_image_sizes;
		$sizes     = \get_intermediate_image_sizes();
		$all_sizes = array();

		foreach ( $sizes as $size ) {
			$width  = 0;
			$height = 0;
			if ( in_array( $size, array( 'thumbnail', 'medium', 'medium_large', 'large' ), true ) ) {
				$width  = intval( \get_option( "{$size}_size_w" ) );
				$height = intval( \get_option( "{$size}_size_h" ) );
			} elseif ( isset( $_wp_additional_image_sizes[ $size ] ) ) {
				$width  = $_wp_additional_image_sizes[ $size ]['width'];
				$height = $_wp_additional_image_sizes[ $size ]['height'];
			}
			$all_sizes[] = array(
				'id'   => $size,
				'name' => ucwords( str_replace( array( '-', '_' ), ' ', $size ) ),
				'size' => ( 0 === $width && 0 === $height ) ? 'N/A' : "{$width}x{$height}",
			);
		}
		// Add full size option.
		$all_sizes[] = array(
			'id'   => 'full',
			'name' => 'Full Size (Original)',
			'size' => 'Original',
		);
		return $all_sizes;
	}
}
