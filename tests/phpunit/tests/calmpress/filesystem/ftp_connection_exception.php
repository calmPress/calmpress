<?php
/**
 * Tests for FTP_Connection_Exception.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Filesystem_Access_Exception;
use calmpress\filesystem\FTP_Connection_Exception;

/**
 * FTP connection exception behavior and user-facing errors.
 *
 * @since 1.0.0
 */
class WP_Test_FTP_Connection_Exception extends WP_UnitTestCase {

	/**
	 * FTP connection failures have distinct user-facing error codes.
	 *
	 * @since 1.0.0
	 */
	public function test_connection_exception_maps_failure_codes(): void {
		$expected = [
			FTP_Connection_Exception::CONNECTION_FAILED => 'ftp_connection_failed',
			FTP_Connection_Exception::PERMISSIONS_CHANGE_FAILED => 'ftp_permissions_change_failed',
			FTP_Connection_Exception::AUTHENTICATION_FAILED => 'ftp_authentication_failed',
			FTP_Connection_Exception::INSTALLATION_INACCESSIBLE => 'ftp_installation_inaccessible',
		];

		foreach ( $expected as $code => $error_code ) {
			$exception = new FTP_Connection_Exception( 'Technical failure.', $code );
			$this->assertInstanceOf( Filesystem_Access_Exception::class, $exception );
			$error = $exception->to_wp_error();
			$this->assertSame( $error_code, $error->get_error_code() );
			$this->assertNotSame( '', $error->get_error_message() );
		}
	}

	/**
	 * An unrecognized FTP failure code reports a warning and returns a generic error.
	 *
	 * @since 1.0.0
	 */
	public function test_connection_exception_reports_unknown_code(): void {
		$warning = null;
		set_error_handler( static function ( int $severity, string $message ) use ( &$warning ): bool {
			$warning = $message;
			return true;
		}, E_USER_WARNING );
		try {
			$error = ( new FTP_Connection_Exception( 'Technical failure.', 99 ) )->to_wp_error();
		} finally {
			restore_error_handler();
		}
		$this->assertSame( 'ftp_connection_failed', $error->get_error_code() );
		$this->assertSame( 'Unknown FTP failure code: 99', $warning );
	}
}
