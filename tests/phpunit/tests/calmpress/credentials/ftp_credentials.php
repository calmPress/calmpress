<?php
/**
 * Unit tests covering FTP_Credentials functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\credentials\FTP_Credentials;

class WP_Test_FTP_Credentials extends WP_UnitTestCase {

	/**
	 * Test constructor trims values and stores them.
	 *
	 * @since 1.0.0
	 */
	function test_constructor_trims_values() {

		$cred = new FTP_Credentials( ' host', 210, ' user  ', 'password  ', ' ' . ABSPATH . ' ' );
		$this->AssertSame( 'host', $cred->host() );
		$this->AssertSame( 210, $cred->port() );
		$this->AssertSame( 'user', $cred->username() );
		$this->AssertSame( 'password', $cred->password() );
		$this->AssertSame( ABSPATH, $cred->base_dir() );
	}

	/**
	 * Test the stream_context method.
	 *
	 * @since 1.0.0
	 */
	function test_stream_context() {
		$context      = FTP_Credentials::stream_context();
		$context_opts = stream_context_get_options( $context );
		$this->assertTrue( isset( $context_opts['ftp'] ) );
		$this->assertTrue( isset( $context_opts['ftp']['overwrite'] ) );
		$this->assertTrue( $context_opts['ftp']['overwrite'] );
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
			[ 'localhost', 21, '', '', $def_base_dir, [] ],
			[ '127.0.0.1', 21, '', '', $def_base_dir, [] ],
			[ 'good.host', 21, '', '', $def_base_dir, [] ],
			[ 'bad host', 21, '', '', $def_base_dir, ['host'] ],
			[ 'good.host', 210, 'user', 'pass', $def_base_dir, [] ],
			[ 'good.host', 2121, 'user', '', $def_base_dir, [] ],
			[ 'good.host', 0, '', '', $def_base_dir, ['port'] ],
			[ 'good.host', 70000, '', '', $def_base_dir, ['port'] ],
			[ 'good.host', 2121, '', 'pass', $def_base_dir, ['username'] ],
			[ 'good.host', 2121, 'us', 'pass', 'fail', ['base_dir'] ],
			[ 'localhost', 0, '', 'pass', 'fail', ['base_dir', 'username', 'base_dir'] ],
			[ 'bad host', 0, '', 'pass', 'fail', ['base_dir', 'host', 'username', 'base_dir'] ],
		];
	}

	/**
	 * Test the credentials_from_request_vars method.
	 *
	 * @since 1.0.0
	 */
	function test_credentials_from_request_vars() {

		// Test validation is done, should get the 4 possible errors here
		$errors = FTP_Credentials::credentials_from_request_vars(
			wp_slash( [
				FTP_Credentials::HOST_FORM_NAME     => ' hot name',
				FTP_Credentials::PORT_FORM_NAME     => 0,
				FTP_Credentials::USERNAME_FORM_NAME => '',
				FTP_Credentials::PASSWORD_FORM_NAME => 'pass ',
				FTP_Credentials::BASEDIR_FORM_NAME  => 'fail',
			] )
		);

		$this->assertSame( 4, count( $errors ) );

		// Test validation is done, should get the 2 possible errors here
		$errors = FTP_Credentials::credentials_from_request_vars(
			wp_slash( [
				FTP_Credentials::HOST_FORM_NAME     => ' hot name',
				FTP_Credentials::PORT_FORM_NAME     => 20,
				FTP_Credentials::USERNAME_FORM_NAME => '',
				FTP_Credentials::PASSWORD_FORM_NAME => '',
				FTP_Credentials::BASEDIR_FORM_NAME  => 'fail',
			] )
		);

		$this->assertSame( 2, count( $errors ) );
		$this->assertTrue( isset( $errors['host'] ) );
		$this->assertTrue( isset( $errors['base_dir'] ) );

		// Test validation passes.
		$cred = FTP_Credentials::credentials_from_request_vars(
			wp_slash( [
				FTP_Credentials::HOST_FORM_NAME     => ' localhost ',
				FTP_Credentials::PORT_FORM_NAME     => ' 21',
				FTP_Credentials::USERNAME_FORM_NAME => 'us ',
				FTP_Credentials::PASSWORD_FORM_NAME => 'pass',
				FTP_Credentials::BASEDIR_FORM_NAME  => dirname( ABSPATH ),
			] )
		);

		$this->assertSame( 'localhost', $cred->host() );
		$this->assertSame( 21, $cred->port() );
		$this->assertSame( 'us', $cred->username() );
		$this->assertSame( 'pass', $cred->password() );
		$this->assertSame( dirname( ABSPATH ), $cred->base_dir() );

		$this->expectException( \DomainException::class );
		FTP_Credentials::credentials_from_request_vars( [] );
	}

	/**
	 * Test the validate method.
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider validation_data
	 */
	function test_validate(
		string $host,
		int $port,
		string $username,
		string $password, 
		string $basedir,
		array $epected_errors
		) {
		$errors = FTP_Credentials::validate( $host, $port, $username, $password, $basedir );

		// Make sure same number of erros.
		$this->assertSame( count( $epected_errors ), count( $errors ) );

		// To compare the error types we need to look at the keys of the error array.
		foreach ( $epected_errors as $key ) {
			$this->assertTrue( isset( $errors[ $key ] ) );
		}
	}

	/**
	 * Data provider for ftp_url_for_path testing.
	 * 
	 * @since 1.0.0
	 */
	function url_path_data() {
		return [
			// simple case.
			[ '127.0.0.1', 230, '', '', ABSPATH, ABSPATH . '.htaccess', 'ftp://127.0.0.1:230/.htaccess' ],
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
				'',
				'',
				ABSPATH,
				ABSPATH . 'wp-content\themes\index.php',
				'ftp://127.0.0.1:21/wp-content/themes/index.php',
			],
		];
	}

	/**
	 * Test the ftp_url_for_path method.
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider url_path_data
	 */
	function test_ftp_url_for_path(
		string $host,
		int $port,
		string $username,
		string $password, 
		string $basedir,
		string $path,
		string $epected_url
		) {

		$creds = new FTP_Credentials( $host, $port, $username, $password, $basedir );
		$this->assertSame( $epected_url, $creds->ftp_url_for_path( $path) );
	}

}
