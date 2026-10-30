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
		FTP_SFTP_Credentials::parse_parameters( [ FTP_SFTP_Credentials::PROTOCOL_FORM_NAME => 'unknown' ], [] );
	}

	/**
	 * A missing protocol is an invalid form state.
	 *
	 * @since 1.0.0
	 */
	public function test_missing_protocol_is_rejected() {
		$this->expectException( LogicException::class );
		FTP_SFTP_Credentials::parse_parameters( [], [] );
	}

	/**
	 * FTP form fields produce FTP parameters through the protocol selector.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_selects_ftp(): void {
		$this->assertEquals( new FTP_Credentials_Parameters( 'localhost', 21, 'user', 'secret', ABSPATH ), FTP_SFTP_Credentials::parse_parameters( wp_slash( [
			FTP_SFTP_Credentials::PROTOCOL_FORM_NAME => 'ftp',
			FTP_Credentials::HOST_FORM_NAME => 'localhost',
			FTP_Credentials::PORT_FORM_NAME => '21',
			FTP_Credentials::USERNAME_FORM_NAME => 'user',
			FTP_Credentials::PASSWORD_FORM_NAME => 'secret',
			FTP_Credentials::BASEDIR_FORM_NAME => ABSPATH,
		] ), [] ) );
	}

	/**
	 * SFTP form fields produce SFTP parameters through the protocol selector.
	 *
	 * @since 1.0.0
	 */
	public function test_parse_parameters_selects_sftp(): void {
		$this->assertEquals( new SFTP_Credentials_Parameters( 'localhost', 22, 'user', 'secret', ABSPATH, '', '' ), FTP_SFTP_Credentials::parse_parameters( wp_slash( [
			FTP_SFTP_Credentials::PROTOCOL_FORM_NAME => 'sftp',
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
}
