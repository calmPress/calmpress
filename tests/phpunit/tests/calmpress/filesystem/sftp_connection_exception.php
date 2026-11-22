<?php
/**
 * Tests for SFTP_Connection_Exception.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Filesystem_Access_Exception;
use calmpress\filesystem\SFTP_Connection_Exception;

/**
 * SFTP connection exception behavior and user-facing errors.
 *
 * @since 1.0.0
 */
class WP_Test_SFTP_Connection_Exception extends WP_UnitTestCase {

	/**
	 * Connection failure codes produce distinct form errors for the user.
	 *
	 * @since 1.0.0
	 */
	public function test_connection_exception_to_wp_error_maps_failure_codes(): void {
		$expected = [
			SFTP_Connection_Exception::SSH_CONNECTION_FAILED => 'sftp_ssh_connection_failed',
			SFTP_Connection_Exception::SSH_SIGNATURE_INVALID => 'sftp_ssh_signature_invalid',
			SFTP_Connection_Exception::AUTHENTICATION_FAILED => 'sftp_authentication_failed',
			SFTP_Connection_Exception::INSTALLATION_INACCESSIBLE => 'sftp_installation_inaccessible',
			SFTP_Connection_Exception::PRIVATE_KEY_INVALID => 'private_key_invalid',
		];

		foreach ( $expected as $code => $error_code ) {
			$exception = new SFTP_Connection_Exception( 'Technical failure.', $code );
			$this->assertInstanceOf( Filesystem_Access_Exception::class, $exception );
			$error = $exception->to_wp_error();
			$this->assertSame( $error_code, $error->get_error_code() );
			$this->assertNotSame( '', $error->get_error_message() );
		}
	}

	/**
	 * An unrecognized SFTP failure code reports a warning and returns a generic error.
	 *
	 * @since 1.0.0
	 */
	public function test_connection_exception_to_wp_error_reports_unknown_code(): void {
		$warning = null;
		set_error_handler( static function ( int $severity, string $message ) use ( &$warning ): bool {
			$warning = $message;
			return true;
		}, E_USER_WARNING );
		try {
			$error = ( new SFTP_Connection_Exception( 'Technical failure.', 99 ) )->to_wp_error();
		} finally {
			restore_error_handler();
		}
		$this->assertSame( 'sftp_connection_failed', $error->get_error_code() );
		$this->assertSame( 'Unknown SFTP failure code: 99', $warning );
	}
}
