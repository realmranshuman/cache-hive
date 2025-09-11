<?php
/**
 * Handles dynamic placeholder logic for logged-in user cache (admin bar, nonces).
 *
 * @package Cache_Hive
 * @since 1.1.0
 */

namespace Cache_Hive\Includes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages dynamic content placeholders and substitution for logged-in user cache.
 */
final class Cache_Hive_Logged_In_Cache {
	/**
	 * Placeholder for the admin bar output.
	 */
	const ADMIN_BAR_PLACEHOLDER = '__CH_ADMIN_BAR_PLACEHOLDER__';

	/**
	 * Placeholder for the WordPress nonce fields (generic for demonstration).
	 */
	const NONCE_PLACEHOLDER = '__CH_NONCE_PLACEHOLDER__';

	/**
	 * Insert placeholders for dynamic logged-in-only content before caching.
	 *
	 * @param string $html The full output buffer.
	 * @return string      The HTML with placeholders instead of dynamic regions.
	 */
	public static function replace_dynamic_elements_with_placeholders( $html ) {
		// Replace admin bar (simple div match) with placeholder.
		$html = preg_replace(
			'/<div id=("|\')wpadminbar("|\')[^>]*>[\s\S]*?<\/div>/i',
			'<div id="wpadminbar">' . self::ADMIN_BAR_PLACEHOLDER . '</div>',
			$html
		);
		// Replace nonces (input fields with name _wpnonce).
		$html = preg_replace(
			'/value=("|\')[a-f0-9]{10,}("|\') name=("|\')_wpnonce("|\')/i',
			'value="' . self::NONCE_PLACEHOLDER . '" name="_wpnonce"',
			$html
		);
		return $html;
	}

	/**
	 * Restore real dynamic content for the user when serving cached HTML.
	 *
	 * @param string $html The cached HTML containing placeholders.
	 * @return string      The HTML with user-specific admin bar and fresh nonce values.
	 */
	public static function inject_dynamic_elements_from_placeholders( $html ) {
		// Replace admin bar placeholder with the real admin bar (if possible and user is logged in).
		if ( is_user_logged_in() && strpos( $html, self::ADMIN_BAR_PLACEHOLDER ) !== false ) {
			ob_start();
			if ( function_exists( 'wp_admin_bar_render' ) ) {
				wp_admin_bar_render();
			}
			$admin_bar = ob_get_clean();
			$html      = str_replace( self::ADMIN_BAR_PLACEHOLDER, $admin_bar, $html );
		}
		// Replace nonce placeholder with a fresh nonce, if any present.
		if ( strpos( $html, self::NONCE_PLACEHOLDER ) !== false ) {
			// As we don't know the original action here, we use a general fallback or site-specific.
			// Developers may wish to hook/filter to customize this for known forms/ajax actions.
			$nonce = function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'default' ) : '';
			$html  = str_replace( self::NONCE_PLACEHOLDER, esc_attr( $nonce ), $html );
		}
		return $html;
	}
}
