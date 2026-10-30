<?php
/**
 * FTP connection failure.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Reports which stage of establishing an FTP connection failed.
 *
 * @since 1.0.0
 */
class FTP_Connection_Exception extends \RuntimeException implements Connection_Exception {

	/**
	 * The host and port did not accept a connection.
	 *
	 * @since 1.0.0
	 */
	public const CONNECTION_FAILED = 1;

	/**
	 * An unexpected error occurred during the FTP greeting or command exchange.
	 *
	 * @since 1.0.0
	 */
	public const COMMUNICATION_FAILED = 2;

	/**
	 * The FTP server rejected the account credentials.
	 *
	 * @since 1.0.0
	 */
	public const AUTHENTICATION_FAILED = 3;

	/**
	 * The account could not access the installation directory over FTP.
	 *
	 * @since 1.0.0
	 */
	public const INSTALLATION_INACCESSIBLE = 4;

	/**
	 * Convert this FTP failure to an error suitable for display.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error A translatable description of the failure.
	 */
	public function to_wp_error(): \WP_Error {
		return match ( $this->getCode() ) {
			self::CONNECTION_FAILED => new \WP_Error( 'ftp_connection_failed', __( 'Could not reach the FTP server. Check the hostname and port.' ) ),
			self::COMMUNICATION_FAILED => new \WP_Error( 'ftp_communication_failed', __( 'An unexpected error occurred while communicating with the FTP server.' ) ),
			self::AUTHENTICATION_FAILED => new \WP_Error( 'ftp_authentication_failed', __( 'Could not sign in to the FTP server. Check the username and password.' ) ),
			self::INSTALLATION_INACCESSIBLE => new \WP_Error( 'ftp_installation_inaccessible', __( 'Could not access the calmPress root directory. Check the Base directory setting and FTP server configuration.' ) ),
			default => $this->unknown_code_error(),
		};
	}

	/**
	 * Report an unknown failure code and provide a generic error.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error A generic FTP connection error.
	 */
	private function unknown_code_error(): \WP_Error {
		trigger_error( 'Unknown FTP connection failure code: ' . $this->getCode(), E_USER_WARNING );
		return new \WP_Error( 'ftp_connection_failed', __( 'Could not establish an FTP connection.' ) );
	}
}
