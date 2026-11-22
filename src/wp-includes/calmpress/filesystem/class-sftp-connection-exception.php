<?php
/**
 * SFTP filesystem access failure.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * An SFTP filesystem access failure with a user-facing error description.
 *
 * @since 1.0.0
 */
class SFTP_Connection_Exception extends Filesystem_Access_Exception {

	/**
	 * The host and port did not establish an SSH connection.
	 *
	 * @since 1.0.0
	 */
	public const SSH_CONNECTION_FAILED = self::CONNECTION_FAILED;

	/**
	 * The SSH server signature could not be verified.
	 *
	 * @since 1.0.0
	 */
	public const SSH_SIGNATURE_INVALID = 4;

	/**
	 * Failed to parse the private key or decrypt it with the supplied passphrase.
	 *
	 * @since 1.0.0
	 */
	public const PRIVATE_KEY_INVALID = 5;

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
			self::PRIVATE_KEY_INVALID => new \WP_Error( 'private_key_invalid', __( 'The private key is invalid or the passphrase is incorrect. Check both and try again.' ) ),
			default => $this->unknown_code_error( 'SFTP', __( 'Could not establish an SFTP connection.' ) ),
		};
	}

}
