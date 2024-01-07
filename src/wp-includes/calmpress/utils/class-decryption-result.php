<?php
/**
 * Implementation of sturcture which is used to represent decryption results.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\utils;

/**
 * Sturcture which is used to represent decryption results.
 *
 * @since 1.0.0
 */
class Decryption_Result {

	/**
	 * The value decrypted.
	 *
	 * @since 1.0.0
	 */
	public readonly int $value;

	/**
	 * The nonce which was decrypted.
	 *
	 * @since 1.0.0
	 */
	public readonly int $nonce;

	/**
	 * Construct the object.
	 *
	 * @since 1.0.0
	 *
	 * @param int $value The value to be set in the value property.
	 * @param int $nonce The value to be set in the nonce property.
	 */
	public function __construct( int $value, int $nonce ) {
		$this->value = $value;
		$this->nonce = $nonce;
	}
}
