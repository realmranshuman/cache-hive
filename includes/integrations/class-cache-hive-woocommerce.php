<?php
/**
 * WooCommerce integration for Cache Hive.
 * Handles WooCommerce-specific cache logic: AJAX cart fragments, page exclusions, REST API, product purge, etc.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes\Integrations;

use Cache_Hive\Includes\Cache_Hive_Purge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Hive_WooCommerce
 *
 * Handles WooCommerce-specific cache logic for Cache Hive.
 */
final class Cache_Hive_WooCommerce {
	/**
	 * Initialize integration hooks.
	 */
	public static function init() {
		// 1. AJAX cart fragments: Serve cached empty cart if cart is empty.
		add_action( 'init', array( __CLASS__, 'maybe_serve_empty_cart_fragment' ), 0 );

		// 2. Dynamic page exclusion: Exclude cart, checkout, account pages.
		add_filter( 'cache_hive_exclude_uris', array( __CLASS__, 'exclude_wc_cart_checkout_account_uris' ) );

		// 3. REST API and query string exclusion.
		add_filter( 'cache_hive_exclude_uris', array( __CLASS__, 'exclude_wc_rest_api_uris' ) );
		add_filter( 'cache_hive_exclude_query_strings', array( __CLASS__, 'exclude_wc_geolocation_query' ) );

		// 4. Product cache purge: Purge cache when product/stock/attributes are updated.
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'purge_wc_product_cache' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'purge_wc_product_cache' ) );
		add_action( 'woocommerce_rest_insert_product_object', array( __CLASS__, 'purge_wc_product_cache' ) );
	}

	/**
	 * Detect WooCommerce AJAX cart fragment request and serve cached empty cart if cart is empty.
	 */
	public static function maybe_serve_empty_cart_fragment() {
		if ( ! self::is_wc_cart_fragment_ajax() ) {
			return;
		}
		$cart = get_transient( 'cache_hive_wc_empty_cart_fragment' );
		if ( false !== $cart ) {
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON response for WooCommerce AJAX
			echo wp_json_encode( json_decode( $cart, true ) );
			exit;
		}
		// If not cached, start output buffering to cache it.
		ob_start( array( __CLASS__, 'cache_empty_cart_fragment' ) );
	}

	/**
	 * Check if current request is WooCommerce AJAX cart fragment.
	 *
	 * @return bool True if AJAX cart fragment request and cart is empty.
	 */
	private static function is_wc_cart_fragment_ajax() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe read-only check for AJAX detection, not processing form data.
		if ( ! isset( $_GET['wc-ajax'] ) ) {
			return false; }
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Safe read-only check for AJAX detection, not processing form data.
		if ( 'get_refreshed_fragments' !== $_GET['wc-ajax'] ) {
			return false; }
		if ( ! empty( $_COOKIE['woocommerce_cart_hash'] ) ) {
			return false; }
		if ( ! empty( $_COOKIE['woocommerce_items_in_cart'] ) ) {
			return false; }
		return true;
	}

	/**
	 * Save the empty cart fragment to transient.
	 *
	 * @param string $content The cart fragment content.
	 * @return string The content.
	 */
	public static function cache_empty_cart_fragment( $content ) {
		set_transient( 'cache_hive_wc_empty_cart_fragment', $content, 7 * DAY_IN_SECONDS );
		return $content;
	}

	/**
	 * Add WooCommerce cart, checkout, account pages to exclusion list.
	 *
	 * @param array $uris Existing exclusion URIs.
	 * @return array Modified exclusion URIs.
	 */
	public static function exclude_wc_cart_checkout_account_uris( $uris ) {
		if ( function_exists( 'wc_get_page_id' ) ) {
			$pages = array( 'cart', 'checkout', 'myaccount' );
			foreach ( $pages as $page ) {
				$page_id = wc_get_page_id( $page );
				if ( $page_id && $page_id > 0 ) {
					$url = get_permalink( $page_id );
					if ( $url ) {
						$uris[] = self::uri_pattern_from_url( $url );
					}
				}
			}
		}
		return $uris;
	}

	/**
	 * Add WooCommerce REST API endpoints to exclusion list.
	 *
	 * @param array $uris Existing exclusion URIs.
	 * @return array Modified exclusion URIs.
	 */
	public static function exclude_wc_rest_api_uris( $uris ) {
		$uris[] = '/wc-api/v1';
		$uris[] = '/wc-api/v2';
		return $uris;
	}

	/**
	 * Add WooCommerce geolocation query string to exclusion list if needed.
	 *
	 * @param array $query_strings Existing exclusion query strings.
	 * @return array Modified exclusion query strings.
	 */
	public static function exclude_wc_geolocation_query( $query_strings ) {
		if ( 'geolocation_ajax' === get_option( 'woocommerce_default_customer_address' ) ) {
			$query_strings[] = 'v';
		}
		return $query_strings;
	}

	/**
	 * Purge cache for a WooCommerce product (by post ID).
	 *
	 * @param mixed $product Product object or ID.
	 */
	public static function purge_wc_product_cache( $product ) {
		$post_id = is_object( $product ) && isset( $product->get_id ) ? $product->get_id() : (int) $product;
		if ( $post_id ) {
			$url = get_permalink( $post_id );
			if ( $url ) {
				Cache_Hive_Purge::purge_url( $url );
			}
		}
	}

	/**
	 * Utility: Convert a URL to a URI pattern for exclusion (supports translated URLs).
	 *
	 * @param string $url The URL to convert.
	 * @return string URI pattern for exclusion.
	 */
	private static function uri_pattern_from_url( $url ) {
		$parts = wp_parse_url( $url );
		return isset( $parts['path'] ) ? $parts['path'] . '.*' : '';
	}
}

// Initialize integration if WooCommerce is active.
if ( defined( 'WC_VERSION' ) ) {
	Cache_Hive_WooCommerce::init();
}
