<?php
/**
 * REST API handler for Image Optimization settings.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\API;

use Cache_Hive\Includes\Cache_Hive_Lifecycle;
use Cache_Hive\Includes\Cache_Hive_Settings;
use Cache_Hive\Includes\Helpers\Cache_Hive_Server_Rules_Helper;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Batch_Processor;
use Cache_Hive\Includes\Optimizers\Image_Optimizer\Cache_Hive_Image_Optimizer;
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
			'image_cron_optimization'       => $settings['image_cron_optimization'] ?? false,
			'image_exclude_images'          => implode( "\n", $settings['image_exclude_images'] ?? array() ),
			'image_exclude_picture_rewrite' => implode( "\n", $settings['image_exclude_picture_rewrite'] ?? array() ),
			'image_selected_thumbnails'     => $settings['image_selected_thumbnails'] ?? array( 'thumbnail', 'medium' ),
			'image_disable_png_gif'         => $settings['image_disable_png_gif'] ?? true,
		);

		$capabilities = Cache_Hive_Image_Optimizer::get_server_capabilities();

		$response_data = array(
			'settings'            => $image_settings,
			'server_capabilities' => $capabilities,
			'stats'               => Cache_Hive_Image_Stats::get_stats( true ),
			'sync_state'          => Cache_Hive_Image_Stats::get_sync_state(),
			'is_network_admin'    => is_multisite() && is_network_admin(),
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
			'image_cron_optimization'       => (bool) ( $params['image_cron_optimization'] ?? false ),
			'image_exclude_images'          => sanitize_textarea_field( $params['image_exclude_images'] ?? '' ),
			'image_exclude_picture_rewrite' => sanitize_textarea_field( $params['image_exclude_picture_rewrite'] ?? '' ),
			'image_selected_thumbnails'     => is_array( $params['image_selected_thumbnails'] ?? null ) ? array_map( 'sanitize_text_field', $params['image_selected_thumbnails'] ) : array( 'thumbnail', 'medium' ),
			'image_disable_png_gif'         => (bool) ( $params['image_disable_png_gif'] ?? true ),
		);

		$old_settings        = Cache_Hive_Settings::get_settings();
		$old_delivery_method = $old_settings['image_delivery_method'] ?? 'picture';
		$sanitized           = Cache_Hive_Settings::sanitize_settings( $input );
		$all_settings        = Cache_Hive_Settings::get_settings();
		$is_network_admin    = is_multisite() && is_network_admin();

		foreach ( $sanitized as $key => $value ) {
			$all_settings[ $key ] = $value;
		}

		if ( $is_network_admin ) {
			update_site_option( 'cache_hive_settings', $all_settings );
		} else {
			update_option( 'cache_hive_settings', $all_settings, 'yes' );
		}

		Cache_Hive_Lifecycle::create_config_file( $all_settings );
		Cache_Hive_Settings::invalidate_settings_snapshot();

		$new_delivery_method = $all_settings['image_delivery_method'];
		if ( $new_delivery_method !== $old_delivery_method ) {
			$server = Cache_Hive_Server_Rules_Helper::get_server_software();
			if ( in_array( $server, array( 'apache', 'litespeed' ), true ) ) {
				Cache_Hive_Server_Rules_Helper::update_root_htaccess();
			} elseif ( 'nginx' === $server ) {
				// The change to or from 'rewrite' affects the nginx file.
				Cache_Hive_Server_Rules_Helper::update_nginx_file();
			}
		}

		if ( ! empty( $all_settings['image_cron_optimization'] ) ) {
			Cache_Hive_Image_Batch_Processor::schedule_event();
		} else {
			Cache_Hive_Image_Batch_Processor::clear_scheduled_event();
		}

		return self::get_settings( $request );
	}

	/**
	 * Deletes all image optimization data, optionally for a specific format.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response The REST response object.
	 */
	public static function delete_all_optimization_data( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		$format = isset( $params['format'] ) ? sanitize_key( $params['format'] ) : null;

		// Validate the format parameter to be safe.
		if ( null !== $format && 'webp' !== $format && 'avif' !== $format ) {
			return new WP_REST_Response( array( 'message' => 'Invalid format specified.' ), 400 );
		}

		$result = Cache_Hive_Image_Optimizer::delete_all_data( $format );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'message' => $result->get_error_message() ), 500 );
		}

		return new WP_REST_Response(
			array(
				'message' => __( 'Image optimization data has been successfully reverted.', 'cache-hive' ),
				'stats'   => $result,
			),
			200
		);
	}

	/**
	 * Handles manual sync actions for the browser-driven queue.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public static function handle_sync_actions( WP_REST_Request $request ) {
		$method = $request->get_method();

		if ( 'POST' === $method ) {
			$params = $request->get_json_params();
			$format = isset( $params['format'] ) ? sanitize_key( $params['format'] ) : 'webp';
			if ( 'webp' !== $format && 'avif' !== $format ) {
				return new WP_REST_Response( array( 'message' => 'Invalid format for sync.' ), 400 );
			}
			$state = Cache_Hive_Image_Stats::start_manual_sync( $format );
			return new WP_REST_Response( $state, 200 );
		}

		if ( 'GET' === $method ) {
			$state = Cache_Hive_Image_Stats::process_next_manual_item();
			return new WP_REST_Response( $state, 200 );
		}

		if ( 'DELETE' === $method ) {
			Cache_Hive_Image_Stats::clear_sync_state();
			return new WP_REST_Response( array( 'message' => 'Sync cancelled.' ), 200 );
		}

		return new WP_REST_Response( array( 'message' => 'Invalid method.' ), 405 );
	}
}
