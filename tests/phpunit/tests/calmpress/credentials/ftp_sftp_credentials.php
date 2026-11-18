<?php
/**
 * Tests the FTP and SFTP credentials selector.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\credentials\FTP_SFTP_Credentials;
use calmpress\credentials\FTP_Credentials;
use calmpress\credentials\FTP_Credentials_Parameters;
use calmpress\credentials\SFTP_Credentials;
use calmpress\credentials\SFTP_Credentials_Parameters;

/**
 * Tests protocol selection and credential parsing.
 *
 * @since 1.0.0
 */
class WP_Test_FTP_SFTP_Credentials extends WP_UnitTestCase {

	/**
	 * Tests rejection of a protocol that has no credentials form.
	 *
	 * @since 1.0.0
	 */
	public function test_unknown_protocol_is_rejected() {
		$this->expectException( LogicException::class );
		FTP_SFTP_Credentials::parse_parameters( [ FTP_SFTP_Credentials::PROTOCOL_FORM_NAME => 'unknown' ] );
	}

	/**
	 * A missing protocol is an invalid form state.
	 *
	 * @since 1.0.0
	 */
	public function test_missing_protocol_is_rejected() {
		$this->expectException( LogicException::class );
		FTP_SFTP_Credentials::parse_parameters( [] );
	}

	/**
	 * Valid FTP parameters produce the corresponding parameter object with the supplied values.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_returns_ftp_values(): void {
		$this->assertEquals( new FTP_Credentials_Parameters( 'localhost', 21, 'user', "p'ass\\word", ABSPATH ), FTP_SFTP_Credentials::parse_parameters( [
			FTP_SFTP_Credentials::PROTOCOL_FORM_NAME => 'ftp',
			FTP_Credentials::HOST_FORM_NAME => 'localhost',
			FTP_Credentials::PORT_FORM_NAME => '21',
			FTP_Credentials::USERNAME_FORM_NAME => 'user',
			FTP_Credentials::PASSWORD_FORM_NAME => "p'ass\\word",
			FTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
		] ) );
	}

	/**
	 * Valid SFTP parameters produce the corresponding parameter object with the supplied values.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_returns_sftp_values(): void {
		$this->assertEquals( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', "p'ass\\word", ABSPATH, '', '' ), FTP_SFTP_Credentials::parse_parameters( [
			FTP_SFTP_Credentials::PROTOCOL_FORM_NAME => 'sftp',
			SFTP_Credentials::HOST_FORM_NAME => 'localhost',
			SFTP_Credentials::PORT_FORM_NAME => '22',
			SFTP_Credentials::USERNAME_FORM_NAME => 'user',
			SFTP_Credentials::AUTH_METHOD_FORM_NAME => 'password',
			SFTP_Credentials::PASSWORD_FORM_NAME => "p'ass\\word",
			SFTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
			SFTP_Credentials::PRIVATE_KEY_FORM_NAME => '',
			SFTP_Credentials::PRIVATE_KEY_TEXT_FORM_NAME => '',
			SFTP_Credentials::PASSPHRASE_FORM_NAME => '',
		] ) );
	}

	/**
	 * Missing or malformed parameters indicate a programming error.
	 *
	 * @since 1.0.0
	 *
	 * @dataProvider malformed_parameters_data
	 *
	 * @param array $parameters Missing or malformed credential parameters.
	 */
	public function test_writable_directory_credentials_throws_for_malformed_parameters( array $parameters ): void {
		$this->expectException( LogicException::class );

		FTP_SFTP_Credentials::writable_directory_credentials( $parameters, ABSPATH );
	}

	/**
	 * Supply missing and malformed credential parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return array Named cases containing invalid parameter arrays.
	 */
	public static function malformed_parameters_data(): array {
		return [
			'missing selector'       => [ [] ],
			'non-string selector'    => [ [ 'calm_credentials_protocol' => [] ] ],
			'unsupported selector'   => [ [ 'calm_credentials_protocol' => 'unknown' ] ],
			'missing FTP fields'     => [ [ 'calm_credentials_protocol' => 'ftp' ] ],
			'missing SFTP fields'    => [ [ 'calm_credentials_protocol' => 'sftp' ] ],
		];
	}

	/**
	 * Field-validation errors are returned before a connection is attempted.
	 *
	 * @since 1.0.0
	 */
	public function test_writable_directory_credentials_preserves_field_errors(): void {
		$vars = $this->validation_form_values( 'ftp' );
		$vars['calm_ftp_host'] = '';
		$result = FTP_SFTP_Credentials::writable_directory_credentials( $vars, ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'host', $result->get_error_codes() );
	}

	/**
	 * Invalid private-key content is converted to a safe validation error.
	 *
	 * @since 1.0.0
	 */
	public function test_writable_directory_credentials_rejects_invalid_private_key(): void {
		$vars = $this->validation_form_values( 'sftp' );
		$vars['calm_sftp_auth_method'] = 'key_text';
		$vars['calm_sftp_private_key_text'] = 'invalid-sensitive-key';
		$result = FTP_SFTP_Credentials::writable_directory_credentials( $vars, ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_credentials', $result->get_error_code() );
		$this->assertStringNotContainsString( 'invalid-sensitive-key', $result->get_error_message() );
	}

	/**
	 * Missing private-key text retains the private-key validation error.
	 *
	 * @since 1.0.0
	 */
	public function test_writable_directory_credentials_rejects_missing_key_text(): void {
		$vars = $this->validation_form_values( 'sftp' );
		$vars['calm_sftp_auth_method'] = 'key_text';
		$result = FTP_SFTP_Credentials::writable_directory_credentials( $vars, ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertContains( 'private_key', $result->get_error_codes() );
	}

	/**
	 * Provide valid form values without requiring a live FTP or SFTP server.
	 *
	 * @since 1.0.0
	 *
	 * @param string $protocol Selected protocol.
	 *
	 * @return array Unslashed credential fields.
	 */
	private function validation_form_values( string $protocol ): array {
		$prefix = 'calm_' . $protocol . '_';
		return [
			'calm_credentials_protocol' => $protocol,
			$prefix . 'host'            => 'localhost',
			$prefix . 'port'            => '22',
			$prefix . 'base'            => ABSPATH,
			$prefix . 'username'        => 'owner',
			$prefix . 'password'        => 'secret',
			'calm_sftp_auth_method'     => 'password',
			'calm_sftp_private_key_text' => '',
			'calm_sftp_passphrase'      => '',
		];
	}

}
