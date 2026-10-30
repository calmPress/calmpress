<?php
/**
 * SFTP connection failure.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Reports which stage of establishing an SFTP connection failed.
 *
 * @since 1.0.0
 */
class SFTP_Connection_Exception extends \RuntimeException implements Connection_Exception {

	/**
	 * The host and port did not establish an SSH connection.
	 *
	 * @since 1.0.0
	 */
	public const SSH_CONNECTION_FAILED = 1;

	/**
	 * The SSH server signature could not be verified.
	 *
	 * @since 1.0.0
	 */
	public const SSH_SIGNATURE_INVALID = 2;

	/**
	 * The supplied account could not authenticate.
	 *
	 * @since 1.0.0
	 */
	public const AUTHENTICATION_FAILED = 3;

	/**
	 * The account could not access the installation directory over SFTP.
	 *
	 * @since 1.0.0
	 */
	public const INSTALLATION_INACCESSIBLE = 4;

	/**
	 * Convert this SFTP failure to an error suitable for display.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error A translatable description of the failure.
	 *
	 */
	public function to_wp_error(): \WP_Error {
		return match ( $this->getCode() ) {
			self::SSH_CONNECTION_FAILED => new \WP_Error( 'sftp_ssh_connection_failed', __( 'Could not establish an SSH connection. Check the hostname and port.' ) ),
			self::SSH_SIGNATURE_INVALID => new \WP_Error( 'sftp_ssh_signature_invalid', __( 'Could not establish a secure connection to this server.' ) ),
			self::AUTHENTICATION_FAILED => new \WP_Error( 'sftp_authentication_failed', __( 'Could not sign in to the SFTP server. Check the username and password or private key.' ) ),
			self::INSTALLATION_INACCESSIBLE => new \WP_Error( 'sftp_installation_inaccessible', __( 'Could not access the calmPress root directory. Check the Base directory setting and SFTP server configuration.' ) ),
			default => $this->unknown_code_error(),
		};
	}

	/**
	 * Report an unknown failure code and provide a generic error.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error A generic SFTP connection error.
	 */
	private function unknown_code_error(): \WP_Error {
		trigger_error( 'Unknown SFTP connection failure code: ' . $this->getCode(), E_USER_WARNING );
		return new \WP_Error( 'sftp_connection_failed', __( 'Could not establish an SFTP connection.' ) );
	}
}
