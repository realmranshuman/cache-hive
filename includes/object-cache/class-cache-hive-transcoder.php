<?php
/**
 * Handles secure data transcoding (serialization, compression, signing) for the object cache.
 *
 * @package Cache_Hive
 * @since 1.2.0
 */

namespace Cache_Hive\Includes\Object_Cache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages the secure encoding and decoding of cache data.
 */
final class Cache_Hive_Transcoder {

	/**
	 * The secret key used for HMAC signing.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * The configured serialization method.
	 *
	 * @var string
	 */
	private $serializer;

	/**
	 * The length of the signature hash (binary sha256 is 32 bytes).
	 *
	 * @var int
	 */
	private const SIGNATURE_LENGTH = 32;

	/**
	 * Constructor.
	 *
	 * @param array $config The object cache runtime configuration.
	 */
	public function __construct( array $config ) {
		$this->secret_key = self::get_secret_key();
		$this->serializer = ( 'igbinary' === ( $config['serializer'] ?? 'php' ) && function_exists( 'igbinary_serialize' ) ) ? 'igbinary' : 'php';
	}

	/**
	 * Encodes data for safe storage in the cache.
	 *
	 * @param mixed $data The raw PHP data.
	 * @return string The encoded, signed string.
	 */
	public function encode( $data ): string {
		// 1. Serialize the data.
		$serialized = 'igbinary' === $this->serializer ? igbinary_serialize( $data ) : serialize( $data );

		// 2. Create a signature (HMAC) of the raw serialized data.
		$signature = hash_hmac( 'sha256', $serialized, $this->secret_key, true ); // true for raw binary output.

		// 3. Prepend the signature to the data and return.
		return $signature . $serialized;
	}

	/**
	 * Decodes data retrieved from the cache, verifying its integrity.
	 *
	 * @param mixed $payload The raw payload from the cache backend.
	 * @return mixed The decoded PHP data, or false on verification failure.
	 */
	public function decode( $payload ) {
		if ( ! is_string( $payload ) || strlen( $payload ) < self::SIGNATURE_LENGTH ) {
			return false; // Invalid payload.
		}

		// 1. Extract the signature and the serialized data.
		$signature       = substr( $payload, 0, self::SIGNATURE_LENGTH );
		$serialized_data = substr( $payload, self::SIGNATURE_LENGTH );

		// 2. Recalculate the signature of the data.
		$expected_signature = hash_hmac( 'sha256', $serialized_data, $this->secret_key, true );

		// 3. CRITICAL: Compare signatures in a timing-attack-safe way.
		if ( ! hash_equals( $expected_signature, $signature ) ) {
			// Data has been tampered with or is corrupt. Do not deserialize.
			return false;
		}

		// 4. If signature is valid, deserialize the data.
		// Handle unserialize errors safely for corrupt data that somehow passed the HMAC check (highly unlikely).
		if ( 'igbinary' === $this->serializer ) {
			try {
				$value = igbinary_unserialize( $serialized_data );
			} catch ( Exception $e ) {
				$value = false;
			}
		} else {
			// Temporarily handle unserialize errors via custom error handler.
			$previous_handler = set_error_handler(
				static function () {
					// Intentionally empty to suppress unserialize warnings.
					return true;
				}
			);

			$value = unserialize( $serialized_data );

			if ( null !== $previous_handler ) {
				set_error_handler( $previous_handler );
			} else {
				restore_error_handler();
			}
		}

		// The unserialize function returns false on error.
		if ( false === $value && 'b:0;' !== $serialized_data ) {
			return false;
		}

		return $value;
	}

	/**
	 * Retrieves a secret key from WordPress salts for HMAC.
	 *
	 * @return string The secret key.
	 */
	private static function get_secret_key(): string {
		// Use dedicated constants if defined, otherwise fall back to standard WordPress salts.
		if ( defined( 'WP_CACHE_KEY' ) && '' !== WP_CACHE_KEY ) {
			return WP_CACHE_KEY;
		}
		if ( defined( 'WP_CACHE_SALT' ) && '' !== WP_CACHE_SALT ) {
			return WP_CACHE_SALT;
		}
		// Fallback to a combination of standard salts for resilience.
		if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
			return AUTH_KEY . SECURE_AUTH_KEY;
		}
		// Final, less secure fallback.
		return 'cache-hive-insecure-fallback-key-please-set-salts';
	}
}
