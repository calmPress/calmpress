<?php
/**
 * Tests the SFTP credentials API.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Filesystem_Access_Over_SFTP;
use calmpress\filesystem\SFTP_Credentials_Parameters;
use calmpress\filesystem\SFTP_Connection_Exception;
use phpseclib4\Crypt\EC;
use phpseclib4\Crypt\RSA;

/**
 * Tests SFTP credential validation, request parsing, and stream setup.
 *
 * @since 1.0.0
 */
class WP_Test_Filesystem_Access_Over_SFTP extends WP_UnitTestCase {

	/**
	 * Tests that stream_url_from_path maps a path with password credentials.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_with_password_credentials() {
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( ' localhost ', 22, ' user ', 'secret', ABSPATH, '', '' ) );

		$this->assertSame( 'calmpress-sftp://localhost:22/index.php', $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that an installation path cannot escape through parent segments.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_rejects_parent_segments() {
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ) );

		$this->expectException( DomainException::class );

		$credentials->stream_url_from_path( ABSPATH . '../outside.php' );
	}

	/**
	 * Tests that one authentication method is required.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_requires_authentication() {
		$this->expectException( DomainException::class );

		new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, '', '' ) );
	}

	/**
	 * Tests that providing a password alongside a private key is accepted.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_accepts_password_and_private_key() {
		$key = RSA::createKey( 1024 )->toString( 'PKCS8' );
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, $key, '' ) );

		$this->assertSame( 'calmpress-sftp://localhost:22/index.php', $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that the constructor accepts an encrypted private key with its passphrase.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_accepts_encrypted_private_key() {
		$key         = RSA::createKey( 1024 )->withPassword( 'key-passphrase' )->toString( 'PKCS8' );
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'key-passphrase' ) );

		$this->assertInstanceOf( Filesystem_Access_Over_SFTP::class, $credentials );
	}

	/**
	 * An empty private key produces a validation error.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider private_key_methods
	 *
	 * @param string $method Selected private-key authentication method.
	 */
	public function test_parse_parameters_reports_empty_private_key( string $method ) {
		$result = Filesystem_Access_Over_SFTP::parse_parameters(
			[
				Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
				Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
				Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
				Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => $method,
				Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '',
				Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
				Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
				Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => '',
			] );

		$this->assertNotEmpty( $result->get_error_messages( 'private_key_empty' ) );
	}

	/**
	 * Enforce the private-key byte limit for both private-key authentication choices.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider private_key_size_cases
	 *
	 * @param string $method Selected authentication method.
	 * @param int $bytes Private-key content length.
	 * @param bool $accepted Whether the content fits within the size limit.
	 */
	public function test_parse_parameters_enforces_private_key_size( string $method, int $bytes, bool $accepted ): void {
		$result = Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => $method,
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
			Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => str_repeat( 'a', $bytes ),
		] );

		if ( $accepted ) {
			$this->assertInstanceOf( SFTP_Credentials_Parameters::class, $result );
			$this->assertSame( $bytes, strlen( $result->private_key_content ) );
		} else {
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertContains( 'private_key_too_large', $result->get_error_codes() );
			$this->assertStringContainsString( '64 KB', $result->get_error_message( 'private_key_too_large' ) );
		}
	}

	/**
	 * Supply private-key sizes at and above the maximum accepted byte length.
	 *
	 * @since 1.0.0
	 *
	 * @return array Authentication methods and size-boundary cases.
	 */
	public static function private_key_size_cases(): array {
		return [
			'file at limit' => [ 'key_file', 65536, true ],
			'file over limit' => [ 'key_file', 65537, false ],
			'text at limit' => [ 'key_text', 65536, true ],
			'text over limit' => [ 'key_text', 65537, false ],
		];
	}

	/**
	 * Base-directory failures identify the specific reason the directory is unusable.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider invalid_base_directories
	 *
	 * @param string $directory Submitted base directory.
	 * @param string $error_code Expected failure code.
	 */
	public function test_parse_parameters_identifies_base_directory_failure( string $directory, string $error_code ): void {
		$result = Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'password',
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => $directory,
			Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => '',
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( [ $error_code ], $result->get_error_codes() );
	}

	/**
	 * Supply empty, nonexistent, and installation-excluding base directories.
	 *
	 * @since 1.0.0
	 *
	 * @return array Base directories and their specific error codes.
	 */
	public static function invalid_base_directories(): array {
		return [
			'empty' => [ '', 'base_dir_empty' ],
			'not a directory' => [ ABSPATH . 'nonexistent-sftp-base', 'base_dir_not_directory' ],
			'excludes installation' => [ ABSPATH . 'wp-includes', 'base_dir_excludes_installation' ],
		];
	}

	/**
	 * Supply the private-key authentication choices.
	 *
	 * @since 1.0.0
	 *
	 * @return array Private-key authentication methods.
	 */
	public static function private_key_methods(): array {
		return [
			[ 'key_file' ],
			[ 'key_text' ],
		];
	}

	/**
	 * A pasted private key is parsed with its passphrase while inactive credentials are ignored.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_accepts_pasted_private_key() {
		$key = RSA::createKey( 1024 )->withPassword( 'key-passphrase' )->toString( 'PKCS8' );
		$parsed = Filesystem_Access_Over_SFTP::parse_parameters(
			[
				Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'key_text',
				Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
				Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
				Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
				Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
				Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => 'inactive-password',
				Filesystem_Access_Over_SFTP::PRIVATE_KEY_FORM_NAME => 'inactive-file',
				Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => $key,
				Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => ' key-passphrase ',
			] );

		$this->assertInstanceOf( SFTP_Credentials_Parameters::class, $parsed );
		$this->assertSame( $key, $parsed->private_key_content );
		$this->assertSame( 'key-passphrase', $parsed->passphrase );
		$this->assertSame( '', $parsed->password );
	}

	/**
	 * Tests that an encrypted OpenSSH key can be parsed and used to construct credentials.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_accepts_encrypted_openssh_key() {
		$key = RSA::createKey( 1024 )->withPassword( 'key-passphrase' )->toString( 'OpenSSH', [ 'rounds' => 1 ] );
		$parsed = Filesystem_Access_Over_SFTP::parse_parameters(
			[
				Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'key_text',
				Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
				Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
				Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
				Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
				Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '',
				Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => $key,
				Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => 'key-passphrase',
			] );

		$this->assertInstanceOf( SFTP_Credentials_Parameters::class, $parsed );
		$this->assertInstanceOf( Filesystem_Access_Over_SFTP::class, new Filesystem_Access_Over_SFTP( $parsed ) );
	}

	/**
	 * Tests that the constructor rejects an encrypted OpenSSH key with the wrong passphrase.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_rejects_wrong_openssh_key_passphrase() {
		$key = RSA::createKey( 1024 )->withPassword( 'correct' )->toString( 'OpenSSH', [ 'rounds' => 1 ] );

		$this->expectException( SFTP_Connection_Exception::class );
		$this->expectExceptionCode( SFTP_Connection_Exception::PRIVATE_KEY_INVALID );

		new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'wrong' ) );
	}

	/**
	 * The constructor accepts encrypted OpenSSH Ed25519 and ECDSA keys.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_accepts_encrypted_openssh_ec_keys() {
		foreach ( [ 'Ed25519', 'secp256r1' ] as $curve ) {
			$key = EC::createKey( $curve )->withPassword( 'correct' )->toString( 'OpenSSH', [ 'rounds' => 1 ] );
			$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'correct' ) );

			$this->assertInstanceOf( Filesystem_Access_Over_SFTP::class, $credentials );
		}
	}

	/**
	 * The constructor rejects an encrypted OpenSSH Ed25519 key with a wrong passphrase.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_rejects_wrong_openssh_ed25519_passphrase() {
		$key = EC::createKey( 'Ed25519' )->withPassword( 'correct' )->toString( 'OpenSSH', [ 'rounds' => 1 ] );

		$this->expectException( SFTP_Connection_Exception::class );
		$this->expectExceptionCode( SFTP_Connection_Exception::PRIVATE_KEY_INVALID );

		new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'wrong' ) );
	}

	/**
	 * Parsing key authentication fields leaves private key validation to construction.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_defers_private_key_validation() {
		$parsed = Filesystem_Access_Over_SFTP::parse_parameters(
			[
				Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'key_text',
				Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
				Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
				Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
				Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
				Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '',
				Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => 'not a private key',
				Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
			] );

		$this->assertInstanceOf( SFTP_Credentials_Parameters::class, $parsed );
		$this->assertSame( 'not a private key', $parsed->private_key_content );
	}

	/**
	 * Tests that IPv6 hosts are bracketed in stream URLs.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_brackets_ipv6_host() {
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( '::1', 22, 'user', 'secret', ABSPATH, '', '' ) );

		$this->assertSame( 'calmpress-sftp://[::1]:22/index.php', $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that an incorrect private key passphrase is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_rejects_wrong_private_key_passphrase() {
		$key = RSA::createKey( 1024 )->withPassword( 'correct' )->toString( 'PKCS8' );

		$this->expectException( SFTP_Connection_Exception::class );
		$this->expectExceptionCode( SFTP_Connection_Exception::PRIVATE_KEY_INVALID );

		new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'incorrect' ) );
	}

	/**
	 * Tests that submitted SFTP parameters report each invalid connection field.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_reports_invalid_fields() {
		$errors = Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::HOST_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '0',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'password',
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
		] );

		$this->assertSame( [ 'authentication', 'host', 'port', 'username' ], $errors->get_error_codes() );
		$this->assertSame( 'User name cannot be empty.', $errors->get_error_message( 'username' ) );
		$this->assertSame( 'Password cannot be empty.', $errors->get_error_message( 'authentication' ) );
	}

	/**
	 * Tests mapping from a filesystem root to the remote account root.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_with_filesystem_root_base_directory() {
		$base        = (string) realpath( '/' );
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );
		$path        = wp_normalize_path( ABSPATH . 'index.php' );
		$relative    = ltrim( substr( $path, strlen( rtrim( wp_normalize_path( $base ), '/' ) ) ), '/' );

		$this->assertSame( 'calmpress-sftp://localhost:22/' . $relative, $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that an account rooted above the installation can address wp-config.php there.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_accepts_parent_configuration_file() {
		$base = dirname( rtrim( ABSPATH, '/\\' ) );
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );

		$this->assertSame( 'calmpress-sftp://localhost:22/wp-config.php', $credentials->stream_url_from_path( $base . '/wp-config.php' ) );
	}

	/**
	 * Tests that a sibling path with the same prefix is outside the account base directory.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_rejects_sibling_directory() {
		$base = dirname( rtrim( ABSPATH, '/\\' ) );
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );
		$this->expectException( DomainException::class );

		$credentials->stream_url_from_path( $base . '-other/file' );
	}

	/**
	 * Tests that parent-directory traversal cannot escape the account base directory.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_rejects_parent_directory_traversal() {
		$base = dirname( rtrim( ABSPATH, '/\\' ) );
		$credentials = new Filesystem_Access_Over_SFTP( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );
		$this->expectException( DomainException::class );

		$credentials->stream_url_from_path( $base . '/site/../wp-config.php' );
	}

	/**
	 * Valid SFTP form fields produce parsed constructor values.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_returns_values_when_valid(): void {
		$this->assertEquals( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ), Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'password',
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
		] ) );
	}

	/**
	 * A password containing only whitespace is rejected before constructing credentials.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_rejects_whitespace_password(): void {
		$result = Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'password',
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '  ',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
		] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertNotEmpty( $result->get_error_messages( 'authentication' ) );
		$this->assertSame( 'Password cannot be empty.', $result->get_error_message( 'authentication' ) );
	}

	/**
	 * Missing required SFTP form fields indicate an invalid call to the parser.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_throws_for_missing_field(): void {
		$this->expectException( LogicException::class );

		Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => 'password',
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => 'secret',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
		] );
	}

	/**
	 * An unsupported SFTP authentication method indicates an invalid parser call.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider unsupported_authentication_methods
	 *
	 * @param string $method Unsupported submitted authentication method.
	 */
	public function test_parse_parameters_throws_for_unknown_authentication_method( string $method ): void {
		$this->expectException( LogicException::class );

		Filesystem_Access_Over_SFTP::parse_parameters( [
			Filesystem_Access_Over_SFTP::HOST_FORM_NAME => 'localhost',
			Filesystem_Access_Over_SFTP::PORT_FORM_NAME => '22',
			Filesystem_Access_Over_SFTP::USERNAME_FORM_NAME => 'user',
			Filesystem_Access_Over_SFTP::AUTH_METHOD_FORM_NAME => $method,
			Filesystem_Access_Over_SFTP::PASSWORD_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::BASEDIR_FORM_NAME => ABSPATH,
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PRIVATE_KEY_TEXT_FORM_NAME => '',
			Filesystem_Access_Over_SFTP::PASSPHRASE_FORM_NAME => '',
		] );
	}

	/**
	 * Supply unsupported authentication methods.
	 *
	 * @since 1.0.0
	 *
	 * @return array Unsupported submitted authentication methods.
	 */
	public static function unsupported_authentication_methods(): array {
		return [
			'unknown method' => [ 'unknown' ],
		];
	}

	/**
	 * Filesystem_Access reject access to a directory outside the account's base directory.
	 *
	 * @since 1.0.0
	 */
	public function test_writable_directory_access_checks_directory_without_protocol_selector(): void {
		$values = [
			'calm_sftp_host'     => 'localhost',
			'calm_sftp_port'     => '22',
			'calm_sftp_base'     => ABSPATH,
			'calm_sftp_username' => 'owner',
			'calm_sftp_password' => 'secret',
			'calm_sftp_auth_method'     => 'password',
			'calm_sftp_private_key_text' => '',
			'calm_sftp_passphrase'      => '',
		];
		$directory = dirname( rtrim( ABSPATH, '/\\' ) );
		$result = Filesystem_Access_Over_SFTP::writable_directory_access( $values, $directory );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'connection_path_invalid', $result->get_error_code() );
		$this->assertSame( [ 'directory' => $directory ], $result->get_error_data() );
	}


	/**
	 * Permission changes pass the mapped path and permission bits to the SFTP session.
	 *
	 * @since 1.0.0
	 */
	public function test_change_permissions_uses_mapped_path(): void {
		$session = $this->createMock( \phpseclib4\Net\SFTP::class );
		$session->expects( $this->never() )->method( 'is_dir' );
		$session->expects( $this->once() )->method( 'chmod' )->with( '/wp-content#test', 0755 );
		$credentials = $this->getMockBuilder( Filesystem_Access_Over_SFTP::class )
			->setConstructorArgs( [ new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ) ] )
			->onlyMethods( [ 'session' ] )->getMock();
		$credentials->method( 'session' )->willReturn( $session );

		$this->assertTrue( $credentials->change_permissions( ABSPATH . 'wp-content#test', 0755 ) );
	}

	/**
	 * Permission failures reported by the SFTP library propagate to the caller.
	 *
	 * @since 1.0.0
	 */
	public function test_change_permissions_propagates_server_failure(): void {
		$session = $this->createMock( \phpseclib4\Net\SFTP::class );
		$session->method( 'chmod' )->willThrowException( new \phpseclib4\Exception\FileSystemException( 'Permission denied.' ) );
		$credentials = $this->getMockBuilder( Filesystem_Access_Over_SFTP::class )
			->setConstructorArgs( [ new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ) ] )
			->onlyMethods( [ 'session' ] )->getMock();
		$credentials->method( 'session' )->willReturn( $session );

		$this->expectException( \phpseclib4\Exception\FileSystemException::class );
		$this->expectExceptionMessage( 'Permission denied.' );

		$credentials->change_permissions( ABSPATH . 'wp-content', 0777 );
	}

	/**
	 * Installation validation checks the mapped directory through the SFTP session.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider installation_directory_access_results
	 *
	 * @param bool $accessible Whether the remote installation directory can be accessed.
	 */
	public function test_throw_if_remote_root_directory_is_unreachable_checks_remote_directory( bool $accessible ): void {
		$session = $this->createMock( \phpseclib4\Net\SFTP::class );
		$session->expects( $this->once() )->method( 'is_dir' )->with( '/' )->willReturn( $accessible );
		$access = $this->getMockBuilder( Filesystem_Access_Over_SFTP::class )
			->setConstructorArgs( [ new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ) ] )
			->onlyMethods( [ 'session' ] )->getMock();
		$access->method( 'session' )->willReturn( $session );

		if ( ! $accessible ) {
			$this->expectException( \calmpress\filesystem\SFTP_Connection_Exception::class );
			$this->expectExceptionCode( \calmpress\filesystem\SFTP_Connection_Exception::INSTALLATION_INACCESSIBLE );
		}

		$validation = new ReflectionMethod( Filesystem_Access_Over_SFTP::class, 'throw_if_remote_root_directory_is_unreachable' );
		$validation->invoke( $access );
	}

	/**
	 * Supply accessible and inaccessible remote installation directories.
	 *
	 * @since 1.0.0
	 *
	 * @return array[] Directory access results.
	 */
	public static function installation_directory_access_results(): array {
		return [
			'accessible' => [ true ],
			'inaccessible' => [ false ],
		];
	}

}
