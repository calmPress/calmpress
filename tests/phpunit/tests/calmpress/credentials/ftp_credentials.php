<?php
/**
 * Unit tests covering FTP_Credentials functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\credentials\FTP_Credentials;
use calmpress\credentials\FTP_Credentials_Parameters;

/**
 * Tests the FTP credentials API.
 *
 * @since 1.0.0
 */
class WP_Test_FTP_Credentials extends WP_UnitTestCase {

	/**
	 * Tests that constructor normalization is reflected in the stream URL.
	 *
	 * @since 1.0.0
	 */
	function test_constructor_trims_values() {

		$cred = new FTP_Credentials( new FTP_Credentials_Parameters( ' host', 210, ' user  ', 'password  ', ' ' . ABSPATH . ' ' ) );
		$this->assertSame( 'ftp://user:password@host:210/index.php', $cred->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * FTP connection failures have distinct user-facing error codes.
	 *
	 * @since 1.0.0
	 */
	public function test_connection_exception_maps_failure_codes(): void {
		$expected = [
			\calmpress\credentials\FTP_Connection_Exception::CONNECTION_FAILED => 'ftp_connection_failed',
			\calmpress\credentials\FTP_Connection_Exception::COMMUNICATION_FAILED => 'ftp_communication_failed',
			\calmpress\credentials\FTP_Connection_Exception::AUTHENTICATION_FAILED => 'ftp_authentication_failed',
			\calmpress\credentials\FTP_Connection_Exception::INSTALLATION_INACCESSIBLE => 'ftp_installation_inaccessible',
		];

		foreach ( $expected as $code => $error_code ) {
			$error = ( new \calmpress\credentials\FTP_Connection_Exception( 'Technical failure.', $code ) )->to_wp_error();
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
			$error = ( new \calmpress\credentials\FTP_Connection_Exception( 'Technical failure.', 99 ) )->to_wp_error();
		} finally {
			restore_error_handler();
		}
		$this->assertSame( 'ftp_connection_failed', $error->get_error_code() );
		$this->assertSame( 'Unknown FTP connection failure code: 99', $warning );
	}

	/**
	 * An FTP multiline response is read through its terminating status line.
	 *
	 * @since 1.0.0
	 */
	public function test_ftp_reply_reads_multiline_response(): void {
		$connection = fopen( 'php://memory', 'r+' );
		fwrite( $connection, "220-Welcome\r\nNotice\r\n220 Ready\r\n" );
		rewind( $connection );

		try {
			$reply = new ReflectionMethod( FTP_Credentials::class, 'ftp_reply' );
			$this->assertSame( 220, $reply->invoke( null, $connection ) );
		} finally {
			fclose( $connection );
		}
	}

	/**
	 * Validation data provider.
	 * 
	 * @since 1.0.0
	 */
	function validation_data() {
		// To get base dir working on both windows and linux just use
		// the directory one level up when testing should pass.
		$def_base_dir = dirname( ABSPATH );

		return [
			[ 'localhost', 21, 'user', 'pass', $def_base_dir, [] ],
			[ '127.0.0.1', 21, 'user', 'pass', $def_base_dir, [] ],
			[ 'good.host', 21, 'user', 'pass', $def_base_dir, [] ],
			[ 'my_server', 21, 'user', 'pass', $def_base_dir, [] ],
			[ '', 21, 'user', 'pass', $def_base_dir, ['host'] ],
			[ 'good.host', 210, 'user', 'pass', $def_base_dir, [] ],
			[ 'good.host', 2121, 'user', '', $def_base_dir, ['password'] ],
			[ 'good.host', 0, 'user', 'pass', $def_base_dir, ['port'] ],
			[ 'good.host', 70000, 'user', 'pass', $def_base_dir, ['port'] ],
			[ 'good.host', 2121, '', 'pass', $def_base_dir, ['username'] ],
			[ 'good.host', 2121, 'us', 'pass', 'fail', ['base_dir'] ],
			[ 'localhost', 0, '', 'pass', 'fail', ['base_dir', 'username', 'base_dir'] ],
			[ '', 0, '', 'pass', 'fail', ['base_dir', 'host', 'username', 'base_dir'] ],
		];
	}

	/**
	 * Tests field-specific errors from FTP parameter validation.
	 *
	 * @since 1.0.0
	 */
	function test_parse_parameters_reports_invalid_fields(): void {

		$errors = FTP_Credentials::parse_parameters(
			wp_slash( [
				FTP_Credentials::HOST_FORM_NAME     => '',
				FTP_Credentials::PORT_FORM_NAME     => '0',
				FTP_Credentials::USERNAME_FORM_NAME => '',
				FTP_Credentials::PASSWORD_FORM_NAME => 'pass ',
				FTP_Credentials::BASEDIR_FORM_NAME  => 'fail',
			] ), []
		);

		$this->assertSame( 4, count( $errors->get_error_codes() ) );

		$errors = FTP_Credentials::parse_parameters(
			wp_slash( [
				FTP_Credentials::HOST_FORM_NAME     => '',
				FTP_Credentials::PORT_FORM_NAME     => '20',
				FTP_Credentials::USERNAME_FORM_NAME => '',
				FTP_Credentials::PASSWORD_FORM_NAME => '',
				FTP_Credentials::BASEDIR_FORM_NAME  => 'fail',
			] ), []
		);

		$this->assertSame( 4, count( $errors->get_error_codes() ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'host' ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'base_dir' ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'username' ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'password' ) );

		$errors = FTP_Credentials::parse_parameters( wp_slash( [
			FTP_Credentials::HOST_FORM_NAME => 'localhost',
			FTP_Credentials::PORT_FORM_NAME => '21abc',
			FTP_Credentials::USERNAME_FORM_NAME => 'user',
			FTP_Credentials::PASSWORD_FORM_NAME => 'secret',
			FTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
		] ), [] );
		$this->assertNotEmpty( $errors->get_error_messages( 'port' ) );
	}

	/**
	 * Tests that the constructor rejects invalid typed credential values.
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider validation_data
	 */
	function test_constructor_validation(
		string $host,
		int $port,
		string $username,
		string $password, 
		string $basedir,
		array $epected_errors
		) {
		if ( $epected_errors ) {
			$this->expectException( \DomainException::class );
		}
		$credentials = new FTP_Credentials( new FTP_Credentials_Parameters( $host, $port, $username, $password, $basedir ) );
		if ( ! $epected_errors ) {
			$this->assertInstanceOf( FTP_Credentials::class, $credentials );
		}
	}

	/**
	 * Data provider for stream_url_from_path testing.
	 * 
	 * @since 1.0.0
	 */
	function url_path_data() {
		return [
			// simple case.
			[ '127.0.0.1', 230, 'user', 'pass', ABSPATH, ABSPATH . '.htaccess', 'ftp://user:pass@127.0.0.1:230/.htaccess' ],
			[ 'name@other', 21, 'user', 'pass', ABSPATH, ABSPATH . 'index.php', 'ftp://user:pass@name%40other:21/index.php' ],
			// username and password that needs to be encoded + directory.
			[ 
				'127.0.0.1', 
				21,
				'us:e@r',
				'pa ss',
				ABSPATH,
				ABSPATH . 'wp-content/test.txt',
				'ftp://us%3Ae%40r:pa%20ss@127.0.0.1:21/wp-content/test.txt',
			],
			// Windows paths need to be converted to unix style.
			[ 
				'127.0.0.1', 
				21,
				'user',
				'pass',
				ABSPATH,
				ABSPATH . 'wp-content\themes\index.php',
				'ftp://user:pass@127.0.0.1:21/wp-content/themes/index.php',
			],
			// The account can also cover wp-config.php above the installation.
			[ '127.0.0.1', 21, 'user', 'pass', dirname( rtrim( ABSPATH, '/\\' ) ), dirname( rtrim( ABSPATH, '/\\' ) ) . '/wp-config.php', 'ftp://user:pass@127.0.0.1:21/wp-config.php' ],
		];
	}

	/**
	 * Test the stream_url_from_path method.
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider url_path_data
	 */
	function test_stream_url_from_path(
		string $host,
		int $port,
		string $username,
		string $password, 
		string $basedir,
		string $path,
		string $epected_url
		) {

		$creds = new FTP_Credentials( new FTP_Credentials_Parameters( $host, $port, $username, $password, $basedir ) );
		$this->assertSame( $epected_url, $creds->stream_url_from_path( $path) );
	}

	/**
	 * Data provider for test stream_url_from_path path related exceptions.
	 * 
	 * @since 1.0.0
	 */
	function ftp_url_path_exceptions_data() {
		return [ 
			[ 'notunderrrot' ],
			[ ABSPATH . '../' ],
			[ dirname( rtrim( ABSPATH, '/\\' ) ) . '-other/file' ],
		];
	}

	/**
	 * Test the exceptions raised by the stream_url_from_path method for bad paths.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider ftp_url_path_exceptions_data
	 */
	function test_stream_url_from_path_exceptions( $path ) {

		$creds = new FTP_Credentials( new FTP_Credentials_Parameters( 'local', 21, 'user', 'pass', dirname( ABSPATH ) ) );
		$this->expectException( \DomainException::class );
		$creds->stream_url_from_path( $path );
	}

	/**
	 * Valid FTP form fields produce parsed constructor values.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_returns_values_when_valid(): void {
		$this->assertEquals( new FTP_Credentials_Parameters( 'localhost', 21, 'user', 'secret', ABSPATH ), FTP_Credentials::parse_parameters( wp_slash( [
			FTP_Credentials::HOST_FORM_NAME => 'localhost',
			FTP_Credentials::PORT_FORM_NAME => '21',
			FTP_Credentials::USERNAME_FORM_NAME => 'user',
			FTP_Credentials::PASSWORD_FORM_NAME => 'secret',
			FTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
		] ), [] ) );
	}
}
