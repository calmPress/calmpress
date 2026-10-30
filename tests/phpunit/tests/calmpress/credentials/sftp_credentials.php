<?php
/**
 * Tests the SFTP credentials API.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\credentials\SFTP_Credentials;
use calmpress\credentials\SFTP_Credentials_Parameters;
use calmpress\credentials\SFTP_Connection_Exception;
use phpseclib4\Crypt\RSA;

/**
 * Tests SFTP credential validation, request parsing, and stream setup.
 *
 * @since 1.0.0
 */
class WP_Test_SFTP_Credentials extends WP_UnitTestCase {

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
		];

		foreach ( $expected as $code => $error_code ) {
			$error = ( new SFTP_Connection_Exception( 'Technical failure.', $code ) )->to_wp_error();
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
		$this->assertSame( 'Unknown SFTP connection failure code: 99', $warning );
	}

	/**
	 * Tests that stream_url_from_path maps a path with password credentials.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_with_password_credentials() {
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( ' localhost ', 22, ' user ', 'secret', ABSPATH, '', '' ) );

		$this->assertSame( 'calmpress-sftp://localhost:22/index.php', $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that an installation path cannot escape through parent segments.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_rejects_parent_segments() {
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ) );

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

		new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, '', '' ) );
	}

	/**
	 * Tests that providing a password alongside a private key is accepted.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_accepts_password_and_private_key() {
		$key = RSA::createKey( 1024 )->toString( 'PKCS8' );
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, $key, '' ) );

		$this->assertSame( 'calmpress-sftp://localhost:22/index.php', $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that the constructor accepts an encrypted private key with its passphrase.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_accepts_encrypted_private_key() {
		$key         = RSA::createKey( 1024 )->withPassword( 'key-passphrase' )->toString( 'PKCS8' );
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'key-passphrase' ) );

		$this->assertInstanceOf( SFTP_Credentials::class, $credentials );
	}

	/**
	 * Tests reporting a failed private key upload.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_reports_upload_failure() {
		$result = SFTP_Credentials::parse_parameters(
			[
				SFTP_Credentials::HOST_FORM_NAME => 'localhost',
				SFTP_Credentials::PORT_FORM_NAME => '22',
				SFTP_Credentials::USERNAME_FORM_NAME => 'user',
				SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'key_file',
				SFTP_Credentials::PASSWORD_FORM_NAME => '',
				SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
				SFTP_Credentials::PASSPHRASE_FORM_NAME => '',
				SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => '',
			],
			[ SFTP_Credentials::PRIVATE_KEY_FORM_NAME => [ 'error' => UPLOAD_ERR_OK, 'tmp_name' => 'not-an-uploaded-file' ] ]
		);

		$this->assertNotEmpty( $result->get_error_messages( 'private_key' ) );
	}

	/**
	 * A pasted private key is parsed with its passphrase while inactive credentials are ignored.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_accepts_pasted_private_key() {
		$key = RSA::createKey( 1024 )->withPassword( 'key-passphrase' )->toString( 'PKCS8' );
		$parsed = SFTP_Credentials::parse_parameters(
			wp_slash( [
				SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'key_text',
				SFTP_Credentials::HOST_FORM_NAME => 'localhost',
				SFTP_Credentials::PORT_FORM_NAME => '22',
				SFTP_Credentials::USERNAME_FORM_NAME => 'user',
				SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
				SFTP_Credentials::PASSWORD_FORM_NAME => 'inactive-password',
				SFTP_Credentials::PRIVATE_KEY_FORM_NAME => 'inactive-file',
				SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => $key,
				SFTP_Credentials::PASSPHRASE_FORM_NAME => ' key-passphrase ',
			] ), []
		);

		$this->assertInstanceOf( SFTP_Credentials_Parameters::class, $parsed );
		$this->assertSame( $key, $parsed->private_key_content );
		$this->assertSame( 'key-passphrase', $parsed->passphrase );
		$this->assertSame( '', $parsed->password );
	}

	/**
	 * Tests that IPv6 hosts are bracketed in stream URLs.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_brackets_ipv6_host() {
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( '::1', 22, 'user', 'secret', ABSPATH, '', '' ) );

		$this->assertSame( 'calmpress-sftp://[::1]:22/index.php', $credentials->stream_url_from_path( ABSPATH . 'index.php' ) );
	}

	/**
	 * Tests that an incorrect private key passphrase is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_rejects_wrong_private_key_passphrase() {
		$key = RSA::createKey( 1024 )->withPassword( 'correct' )->toString( 'PKCS8' );

		$this->expectException( DomainException::class );

		new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', '', ABSPATH, $key, 'incorrect' ) );
	}

	/**
	 * Tests that submitted SFTP parameters report each invalid connection field.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_reports_invalid_fields() {
		$errors = SFTP_Credentials::parse_parameters( wp_slash( [
			SFTP_Credentials::HOST_FORM_NAME => '',
			SFTP_Credentials::PORT_FORM_NAME => '0',
			SFTP_Credentials::USERNAME_FORM_NAME => '',
			SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
			SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'password',
			SFTP_Credentials::PASSWORD_FORM_NAME => '',
			SFTP_Credentials::PRIVATE_KEY_FORM_NAME => '',
			SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => '',
			SFTP_Credentials::PASSPHRASE_FORM_NAME => '',
		] ), [] );

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
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );
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
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );

		$this->assertSame( 'calmpress-sftp://localhost:22/wp-config.php', $credentials->stream_url_from_path( $base . '/wp-config.php' ) );
	}

	/**
	 * Tests that a sibling path with the same prefix is outside the account base directory.
	 *
	 * @since 1.0.0
	 */
	public function test_stream_url_from_path_rejects_sibling_directory() {
		$base = dirname( rtrim( ABSPATH, '/\\' ) );
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );
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
		$credentials = new SFTP_Credentials( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', $base, '', '' ) );
		$this->expectException( DomainException::class );

		$credentials->stream_url_from_path( $base . '/site/../wp-config.php' );
	}

	/**
	 * Valid SFTP form fields produce parsed constructor values.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_returns_values_when_valid(): void {
		$this->assertEquals( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ), SFTP_Credentials::parse_parameters( wp_slash( [
			SFTP_Credentials::HOST_FORM_NAME => 'localhost',
			SFTP_Credentials::PORT_FORM_NAME => '22',
			SFTP_Credentials::USERNAME_FORM_NAME => 'user',
			SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'password',
			SFTP_Credentials::PASSWORD_FORM_NAME => 'secret',
			SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
			SFTP_Credentials::PRIVATE_KEY_FORM_NAME => '',
			SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => '',
			SFTP_Credentials::PASSPHRASE_FORM_NAME => '',
		] ), [] ) );
	}

	/**
	 * A password containing only whitespace is rejected before constructing credentials.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_rejects_whitespace_password(): void {
		$result = SFTP_Credentials::parse_parameters( wp_slash( [
			SFTP_Credentials::HOST_FORM_NAME => 'localhost',
			SFTP_Credentials::PORT_FORM_NAME => '22',
			SFTP_Credentials::USERNAME_FORM_NAME => 'user',
			SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'password',
			SFTP_Credentials::PASSWORD_FORM_NAME => '  ',
			SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
			SFTP_Credentials::PRIVATE_KEY_FORM_NAME => '',
			SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => '',
			SFTP_Credentials::PASSPHRASE_FORM_NAME => '',
		] ), [] );

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

		SFTP_Credentials::parse_parameters( [
			SFTP_Credentials::PORT_FORM_NAME => '22',
			SFTP_Credentials::USERNAME_FORM_NAME => 'user',
			SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'password',
			SFTP_Credentials::PASSWORD_FORM_NAME => 'secret',
			SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
		], [] );
	}

	/**
	 * An unsupported SFTP authentication method indicates an invalid parser call.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_throws_for_unknown_authentication_method(): void {
		$this->expectException( LogicException::class );

		SFTP_Credentials::parse_parameters( [
			SFTP_Credentials::HOST_FORM_NAME => 'localhost',
			SFTP_Credentials::PORT_FORM_NAME => '22',
			SFTP_Credentials::USERNAME_FORM_NAME => 'user',
			SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'unknown',
			SFTP_Credentials::PASSWORD_FORM_NAME => '',
			SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
			SFTP_Credentials::PRIVATE_KEY_FORM_NAME => '',
			SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => '',
			SFTP_Credentials::PASSPHRASE_FORM_NAME => '',
		], [] );
	}
}
