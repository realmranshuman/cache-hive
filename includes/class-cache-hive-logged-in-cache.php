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
	const ADMIN_BAR_PLACEHOLDER = '<!-- CH_ADMIN_BAR_PLACEHOLDER -->';

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

		// ROBUSTNESS FIX: Replace nonces with action-specific placeholders.
		// This finds nonce input fields and replaces them with a comment containing the nonce action.
		$html = preg_replace_callback(
			'/<input(?:[^>]*)name=(["\'])([^"\']+?_wpnonce)\1(?:[^>]*)value=(["\'])([a-f0-9]{10,})\3(?:[^>]*)\/?>/i',
			function ( $matches ) {
				$nonce_name = $matches[2]; // e.g., _wpnonce
				// Try to extract a specific action from the full field name or id if available.
				// This makes the nonce regeneration much more accurate.
				$action = '-1'; // Default WordPress action.
				if ( preg_match( '/_wpnonce(?:_|-)(.+)/', $nonce_name, $action_match ) ) {
					$action = $action_match[1];
				} elseif ( preg_match( '/id=(["\'])([^"\']+)\1/', $matches[0], $id_match ) ) {
					// Fallback to checking the ID attribute.
					if ( preg_match( '/_wpnonce(?:_|-)(.+)/', $id_match[2], $action_match_id ) ) {
						$action = $action_match_id[1];
					}
				}
				return '<!--CH_NONCE_ACTION:' . esc_attr( $action ) . '-->';
			},
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
		// This function may be called from advanced-cache.php before WordPress functions are loaded.
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			return $html;
		}

		// Replace admin bar placeholder with the real admin bar (if possible and user is logged in).
		if ( is_user_logged_in() && strpos( $html, self::ADMIN_BAR_PLACEHOLDER ) !== false ) {
			ob_start();
			if ( function_exists( 'wp_admin_bar_render' ) ) {
				wp_admin_bar_render();
			}
			$admin_bar = ob_get_clean();
			$html      = str_replace( self::ADMIN_BAR_PLACEHOLDER, $admin_bar, $html );
		}

		// ROBUSTNESS FIX: Regenerate nonces using the specific action saved in the placeholder.
		if ( strpos( $html, '<!--CH_NONCE_ACTION:' ) !== false && function_exists( 'wp_create_nonce' ) ) {
			$html = preg_replace_callback(
				'/<!--CH_NONCE_ACTION:([^>]+?)-->/',
				function ( $matches ) {
					$action     = html_entity_decode( $matches[1] );
					$nonce_val  = wp_create_nonce( $action );
					$field_name = '_wpnonce';
					if ( '-1' !== $action ) {
						$field_name = '_wpnonce-' . $action;
					}
					// Reconstruct a standard hidden nonce field.
					return '<input type="hidden" id="' . esc_attr( $field_name ) . '" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( $nonce_val ) . '" />';
				},
				$html
			);
		}

		return $html;
	}
}
