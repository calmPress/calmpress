<?php
/**
 * Unit tests covering Filesystem_Access_Over_FTP functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Filesystem_Access_Over_FTP;
use calmpress\filesystem\FTP_Credentials_Parameters;

/**
 * Tests the FTP credentials API.
 *
 * @since 1.0.0
 */
class WP_Test_Filesystem_Access_Over_FTP extends WP_UnitTestCase {

	/**
	 * Tests that constructor normalization is reflected in the stream URL.
	 *
	 * @since 1.0.0
	 */
	function test_constructor_trims_values() {

		$cred = new Filesystem_Access_Over_FTP( new FTP_Credentials_Parameters( ' host', 210, ' user  ', 'password  ', ' ' . ABSPATH . ' ' ) );
		$this->assertSame( 'ftp://user:password@host:210/index.php', $cred->stream_url_from_path( ABSPATH . 'index.php' ) );
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

		$errors = Filesystem_Access_Over_FTP::parse_parameters(
			[
				Filesystem_Access_Over_FTP::HOST_FORM_NAME     => '',
				Filesystem_Access_Over_FTP::PORT_FORM_NAME     => '0',
				Filesystem_Access_Over_FTP::USERNAME_FORM_NAME => '',
				Filesystem_Access_Over_FTP::PASSWORD_FORM_NAME => 'pass ',
				Filesystem_Access_Over_FTP::BASEDIR_FORM_NAME  => 'fail',
			] );

		$this->assertSame( 4, count( $errors->get_error_codes() ) );

		$errors = Filesystem_Access_Over_FTP::parse_parameters(
			[
				Filesystem_Access_Over_FTP::HOST_FORM_NAME     => '',
				Filesystem_Access_Over_FTP::PORT_FORM_NAME     => '20',
				Filesystem_Access_Over_FTP::USERNAME_FORM_NAME => '',
				Filesystem_Access_Over_FTP::PASSWORD_FORM_NAME => '',
				Filesystem_Access_Over_FTP::BASEDIR_FORM_NAME  => 'fail',
			] );

		$this->assertSame( 4, count( $errors->get_error_codes() ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'host' ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'base_dir_not_directory' ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'username' ) );
		$this->assertNotEmpty( $errors->get_error_messages( 'password' ) );

		$errors = Filesystem_Access_Over_FTP::parse_parameters( [
			Filesystem_Access_Over_FTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_FTP::PORT_FORM_NAME => '21abc',
			Filesystem_Access_Over_FTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_FTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_FTP::BASEDIR_FORM_NAME => ABSPATH,
		] );
		$this->assertNotEmpty( $errors->get_error_messages( 'port' ) );
	}

	/**
	 * Base directory validation identifies each failure separately.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider invalid_base_directories
	 *
	 * @param string $base_dir Submitted base directory.
	 * @param string $error_code Expected validation failure code.
	 */
	public function test_parse_parameters_distinguishes_base_directory_failures( string $base_dir, string $error_code ): void {
		$result = Filesystem_Access_Over_FTP::parse_parameters( [
			Filesystem_Access_Over_FTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_FTP::PORT_FORM_NAME => '21',
			Filesystem_Access_Over_FTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_FTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_FTP::BASEDIR_FORM_NAME => $base_dir,
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( [ $error_code ], $result->get_error_codes() );
		$this->assertNotEmpty( $result->get_error_message() );

		if ( '' !== trim( $base_dir ) ) {
			$this->assertStringContainsString( trim( $base_dir ), $result->get_error_message() );
		}

	}

	/**
	 * Supply empty, inaccessible, and installation-excluding base directories.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] Submitted base directory and expected failure code.
	 */
	public static function invalid_base_directories(): array {
		return [
			'empty' => [ '', 'base_dir_empty' ],
			'whitespace' => [ '   ', 'base_dir_empty' ],
			'nonexistent directory' => [ __FILE__ . '/missing', 'base_dir_not_directory' ],
			'file' => [ __FILE__, 'base_dir_not_directory' ],
			'excludes installation' => [ __DIR__, 'base_dir_excludes_installation' ],
		];
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
		$credentials = new Filesystem_Access_Over_FTP( new FTP_Credentials_Parameters( $host, $port, $username, $password, $basedir ) );
		if ( ! $epected_errors ) {
			$this->assertInstanceOf( Filesystem_Access_Over_FTP::class, $credentials );
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
			[ '127.0.0.1', 21, 'user', 'pass', ABSPATH, rtrim( ABSPATH, '/\\' ), 'ftp://user:pass@127.0.0.1:21/' ],
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

		$creds = new Filesystem_Access_Over_FTP( new FTP_Credentials_Parameters( $host, $port, $username, $password, $basedir ) );
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

		$creds = new Filesystem_Access_Over_FTP( new FTP_Credentials_Parameters( 'local', 21, 'user', 'pass', dirname( ABSPATH ) ) );
		$this->expectException( \DomainException::class );
		$creds->stream_url_from_path( $path );
	}

	/**
	 * Valid FTP parameters produce a credentials parameters object.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_returns_values_when_valid(): void {
		$this->assertEquals( new FTP_Credentials_Parameters( 'localhost', 21, 'user', 'secret', ABSPATH ), Filesystem_Access_Over_FTP::parse_parameters( [
			Filesystem_Access_Over_FTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_FTP::PORT_FORM_NAME => '21',
			Filesystem_Access_Over_FTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_FTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_FTP::BASEDIR_FORM_NAME => ABSPATH,
		] ) );
	}

	/**
	 * Filesystem_Access reject access to a directory outside the account's base directory.
	 *
	 * @since 1.0.0
	 */
	public function test_writable_directory_access_checks_directory_without_protocol_selector(): void {
		$values = [
			'calm_ftp_host'     => 'localhost',
			'calm_ftp_port'     => '22',
			'calm_ftp_base'     => ABSPATH,
			'calm_ftp_username' => 'owner',
			'calm_ftp_password' => 'secret',
		];
		$directory = dirname( rtrim( ABSPATH, '/\\' ) );
		$result = Filesystem_Access_Over_FTP::writable_directory_access( $values, $directory );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'connection_path_invalid', $result->get_error_code() );
		$this->assertSame( [ 'directory' => $directory ], $result->get_error_data() );
	}


	/**
	 * Stream context creation returns configuration without opening an FTP connection.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_context_returns_configuration_without_connecting(): void {
		$credentials = new Filesystem_Access_Over_FTP( new FTP_Credentials_Parameters( 'unreachable.invalid', 21, 'user', 'secret', ABSPATH ) );

		$this->assertSame( [ 'ftp' => [ 'overwrite' => true ] ], stream_context_get_options( $credentials->stream_context() ) );
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
			$reply = new ReflectionMethod( Filesystem_Access_Over_FTP::class, 'ftp_reply' );
			$this->assertSame( 220, $reply->invoke( null, $connection, \calmpress\filesystem\FTP_Connection_Exception::AUTHENTICATION_FAILED ) );
		} finally {
			fclose( $connection );
		}
	}

	/**
	 * Invalid FTP responses retain the failed operation's code and the technical reason.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider failed_ftp_responses
	 *
	 * @param string $response Response bytes read from the control stream.
	 * @param int $failure_code Code identifying the operation in progress.
	 * @param string $reason Expected technical failure message.
	 */
	public function test_ftp_reply_reports_failed_operation( string $response, int $failure_code, string $reason ): void {
		$connection = fopen( 'php://memory', 'r+' );
		fwrite( $connection, $response );
		rewind( $connection );

		try {
			$this->expectException( \calmpress\filesystem\FTP_Connection_Exception::class );
			$this->expectExceptionCode( $failure_code );
			$this->expectExceptionMessage( $reason );
			$reply = new ReflectionMethod( Filesystem_Access_Over_FTP::class, 'ftp_reply' );
			$reply->invoke( null, $connection, $failure_code );
		} finally {
			fclose( $connection );
		}
	}

	/**
	 * Supply malformed and incomplete response data for different operations.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] Response bytes, operation code, and technical failure message.
	 */
	public static function failed_ftp_responses(): array {
		return [
			'login' => [ 'invalid response', \calmpress\filesystem\FTP_Connection_Exception::AUTHENTICATION_FAILED, 'Invalid FTP response.' ],
			'permission change' => [ "200-Incomplete\r\n", \calmpress\filesystem\FTP_Connection_Exception::PERMISSIONS_CHANGE_FAILED, 'Incomplete FTP response.' ],
		];
	}

	/**
	 * Failed command writes report the operation being attempted.
	 *
	 * @since 1.0.0
	 */
	public function test_ftp_command_reports_failed_operation(): void {
		$connection = fopen( __FILE__, 'rb' );

		try {
			$this->expectException( \calmpress\filesystem\FTP_Connection_Exception::class );
			$this->expectExceptionCode( \calmpress\filesystem\FTP_Connection_Exception::AUTHENTICATION_FAILED );
			$this->expectExceptionMessage( 'Failed transmitting FTP command.' );
			$command = new ReflectionMethod( Filesystem_Access_Over_FTP::class, 'ftp_command' );
			$command->invoke( null, $connection, 'USER test', \calmpress\filesystem\FTP_Connection_Exception::AUTHENTICATION_FAILED );
		} finally {
			fclose( $connection );
		}
	}

	/**
	 * Submitted settings report an inaccessible installation before attempting a write probe.
	 *
	 * @since 1.0.0
	 */
	public function test_writable_directory_access_reports_inaccessible_installation(): void {
		$result = Test_Inaccessible_FTP_Installation::writable_directory_access( [
			Filesystem_Access_Over_FTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_FTP::PORT_FORM_NAME => '21',
			Filesystem_Access_Over_FTP::USERNAME_FORM_NAME => 'owner',
			Filesystem_Access_Over_FTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_FTP::BASEDIR_FORM_NAME => ABSPATH,
		], ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'ftp_installation_inaccessible', $result->get_error_code() );
	}

}

/**
 * FTP access with a rejected installation directory and a forbidden write probe.
 *
 * @since 1.0.0
 */
class Test_Inaccessible_FTP_Installation extends Filesystem_Access_Over_FTP {

	/**
	 * Report that the installation directory cannot be accessed.
	 *
	 * @since 1.0.0
	 *
	 * @throws \calmpress\filesystem\FTP_Connection_Exception For the inaccessible installation.
	 */
	protected function throw_if_remote_root_directory_is_unreachable(): void {
		throw new \calmpress\filesystem\FTP_Connection_Exception( 'FTP installation directory inaccessible.', \calmpress\filesystem\FTP_Connection_Exception::INSTALLATION_INACCESSIBLE );
	}

	/**
	 * Reject any write probe attempted after failed installation validation.
	 *
	 * @since 1.0.0
	 *
	 * @throws \LogicException If the write probe begins.
	 */
	public function stream_context() {
		throw new \LogicException( 'A write probe must not follow failed installation validation.' );
	}
}
