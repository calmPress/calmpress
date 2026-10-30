<?php
/**
 * SFTP connection parameters.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Values needed to create SFTP credentials.
 *
 * @since 1.0.0
 */
class SFTP_Credentials_Parameters implements Connection_Credentials_Parameters {
	/**
	 * SFTP server hostname or IP address.
	 *
	 * @since 1.0.0
	 */
	public readonly string $host;

	/**
	 * Port number on which the SFTP server accepts connections.
	 *
	 * @since 1.0.0
	 */
	public readonly int $port;

	/**
	 * User name of the SFTP account.
	 *
	 * @since 1.0.0
	 */
	public readonly string $username;

	/**
	 * Password of the SFTP account when password authentication is used.
	 *
	 * @since 1.0.0
	 */
	public readonly string $password;

	/**
	 * Server directory corresponding to the SFTP account root.
	 *
	 * @since 1.0.0
	 */
	public readonly string $base_dir;

	/**
	 * Private key used for key authentication.
	 *
	 * @since 1.0.0
	 */
	public readonly string $private_key_content;

	/**
	 * Passphrase used to read the private key.
	 *
	 * @since 1.0.0
	 */
	public readonly string $passphrase;

	/**
	 * Store the SFTP connection values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host SFTP server hostname or IP address.
	 * @param int $port SFTP server port.
	 * @param string $username SFTP account user name.
	 * @param string $password SFTP account password.
	 * @param string $base_dir Server directory corresponding to the account root.
	 * @param string $private_key_content Private key content.
	 * @param string $passphrase Passphrase for the private key.
	 */
	public function __construct( string $host, int $port, string $username, string $password, string $base_dir, string $private_key_content, string $passphrase ) {
		$this->host = trim( $host );
		$this->port = $port;
		$this->username = trim( $username );
		$this->password = trim( $password );
		$this->base_dir = trim( $base_dir );
		$this->private_key_content = $private_key_content;
		$this->passphrase = trim( $passphrase );
	}
}
