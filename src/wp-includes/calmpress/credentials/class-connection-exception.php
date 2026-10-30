<?php
/**
 * Interface for connection failures from connection credentials.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * A connection failure that can provide a user-facing error.
 *
 * @since 1.0.0
 */
interface Connection_Exception {

	/**
	 * Convert the connection failure to an error suitable for display.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error A translatable description of the failure.
	 */
	public function to_wp_error(): \WP_Error;
}
