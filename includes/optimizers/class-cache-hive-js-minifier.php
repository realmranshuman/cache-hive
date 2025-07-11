<?php
/**
 * A robust JS Minifier for Cache Hive that extends the base library
 * to fix bugs and implement safer optimization strategies.
 *
 * @package Cache_Hive
 */

namespace Cache_Hive\Includes;

use Cache_Hive\Vendor\MatthiasMullie\Minify\JS as BaseJS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extends the Matthias Mullie JS Minifier to provide a safe, custom execution flow.
 */
class Cache_Hive_JS_Minifier extends BaseJS {

	/**
	 * A robust minifier method that pre-processes JS to apply safety fixes
	 * before handing it to the standard minifier functions.
	 *
	 * This method can safely access the protected methods of the parent class.
	 *
	 * @param string $content The raw JavaScript string.
	 * @return string The minified JavaScript string.
	 */
	public function minify_content( $content ) {
		// Set the raw content for processing.
		$this->add( $content );

		// Step 1: Extract strings, comments, and regular expressions to protect them.
		$this->extractStrings( '\'"`' );
		$this->stripComments();
		$this->extractRegex();
		$content = $this->replace( $content );

		// Step 2: Apply semicolon safety fixes on the sanitized code.
		$content = preg_replace( '/(for\((?:[^;\{]*|[^;\{]*function[^;\{]*(\{([^\{\}]*(?-2))*[^\{\}]*\})?[^;\{]*);[^;\{]*;[^;\{]*\));(\}|$)/s', '\\1;;\\4', $content );
		$content = preg_replace( '/(for\([^;\{]*;(?:[^;\{]*|[^;\{]*function[^;\{]*(\{([^\{\}]*(?-2))*[^\{\}]*\})?[^;\{]*);[^;\{]*\));(\}|$)/s', '\\1;;\\4', $content );
		$content = preg_replace( '/(for\([^;\{]*;[^;\{]*;(?:[^;\{]*|[^;\{]*function[^;\{]*(\{([^\{\}]*(?-2))*[^\{\}]*\})?[^;\{]*)\));(\}|$)/s', '\\1;;\\4', $content );
		$content = preg_replace( '/(\bif\s*\([^{;]*\));\}/s', '\\1;;}', $content );

		// Step 3: Perform the remaining safe optimizations.
		$content = $this->propertyNotation( $content );
		$content = $this->shortenBools( $content );
		$content = $this->stripWhitespace( $content );

		// Step 4: Restore the protected strings and regexes.
		$content = $this->restoreExtractedData( $content );

		return $content;
	}
}
