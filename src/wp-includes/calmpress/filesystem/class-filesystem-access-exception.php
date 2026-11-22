<?php
/**
 * Base exception for filesystem access failures.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * A filesystem access failure with a message for the user.
 *
 * @since 1.0.0
 */
abstract class Filesystem_Access_Exception extends \RuntimeException {

	/**
	 * Failed to establish the connection needed for filesystem access.
	 *
	 * @since 1.0.0
	 */
	public const CONNECTION_FAILED = 1;

	/**
	 * Failed to authenticate using the supplied credentials.
	 *
	 * @since 1.0.0
	 */
	public const AUTHENTICATION_FAILED = 2;

	/**
	 * Failed to access the calmPress installation directory.
	 *
	 * @since 1.0.0
	 */
	public const INSTALLATION_INACCESSIBLE = 3;

	/**
	 * Create a WP_Error describing the failure for display.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error An error describing the failure.
	 */
	abstract public function to_wp_error(): \WP_Error;

	/**
	 * Issue a warning for an unrecognized exception code and return a general filesystem access error.
	 *
	 * @since 1.0.0
	 *
	 * @param string $protocol Access method name used in the warning and, in lowercase, as the error code prefix.
	 * @param string $error_message Translated message to display when the exception code is unrecognized.
	 *
	 * @return \WP_Error An error containing the supplied message.
	 */
	protected function unknown_code_error( string $protocol, string $error_message ): \WP_Error {
		trigger_error( sprintf( 'Unknown %s failure code: %d', $protocol, $this->getCode() ), E_USER_WARNING );

		return new \WP_Error( strtolower( $protocol ) . '_connection_failed', $error_message );
	}
}
