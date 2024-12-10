<?php
/**
 * Implementation of a represntation of a public key used in webauthn
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\webauthn;

use function calmpress\utils\base64URL_decode;

/**
 * A representation of a public key used in webauthn.
 * 
 * Main usage is for sanitization, validation and type check for all
 * the places in which public key is used. .
 *
 * @since 1.0.0
 */
class Public_Key {

	/**
	 * The string representation of the public key in a base65URL format
	 *
	 * @since 1.0.0
	 */
	public readonly string $base64URL;

	/**
	 * Create an object representing the public key.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string  $public_key The string containing a string represantation of
	 *                            the public key. It is expected to be base64URL encoded.
	 * 
	 * @throws \RuntimeException If the $public_key is not a valid public key.
	 */
	public function __construct( string $public_key ) {
		// Ensure minimal sanity
		$decoded = base64URL_decode( $public_key );

		if ( ! $decoded ) {
			// failed base64 decoding
			throw new \RuntimeException( 'base64URL decoding had failed: ' . $public_key );
		}

		$this->base64URL = $public_key;
	}
}