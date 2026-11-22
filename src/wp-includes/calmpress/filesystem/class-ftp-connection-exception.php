<?php
/**
 * FTP connection failure.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * An FTP connection failure with a user-facing error description.
 *
 * @since 1.0.0
 */
class FTP_Connection_Exception extends Filesystem_Access_Exception {

	/**
	 * Failed to change file or directory permissions.
	 *
	 * @since 1.0.0
	 */
	public const PERMISSIONS_CHANGE_FAILED = 4;

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
			self::PERMISSIONS_CHANGE_FAILED => new \WP_Error( 'ftp_permissions_change_failed', __( 'Failed to change permissions through FTP.' ) ),
			self::AUTHENTICATION_FAILED => new \WP_Error( 'ftp_authentication_failed', __( 'Could not sign in to the FTP server. Check the username and password.' ) ),
			self::INSTALLATION_INACCESSIBLE => new \WP_Error( 'ftp_installation_inaccessible', __( 'Could not access the calmPress root directory. Check the Base directory setting and FTP server configuration.' ) ),
			default => $this->unknown_code_error( 'FTP', __( 'Could not establish an FTP connection.' ) ),
		};
	}

}
