<?php
/**
 * FTP connection parameters.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Values needed to create FTP credentials.
 *
 * @since 1.0.0
 */
class FTP_Credentials_Parameters implements Connection_Credentials_Parameters {
	/**
	 * FTP server hostname or IP address.
	 *
	 * @since 1.0.0
	 */
	public readonly string $host;

	/**
	 * Port number on which the FTP server accepts connections.
	 *
	 * @since 1.0.0
	 */
	public readonly int $port;

	/**
	 * User name of the FTP account.
	 *
	 * @since 1.0.0
	 */
	public readonly string $username;

	/**
	 * Password of the FTP account.
	 *
	 * @since 1.0.0
	 */
	public readonly string $password;

	/**
	 * Server directory corresponding to the FTP account root.
	 *
	 * @since 1.0.0
	 */
	public readonly string $base_dir;

	/**
	 * Store the FTP connection values.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host FTP server hostname or IP address.
	 * @param int $port FTP server port.
	 * @param string $username FTP account user name.
	 * @param string $password FTP account password.
	 * @param string $base_dir Server directory corresponding to the account root.
	 */
	public function __construct( string $host, int $port, string $username, string $password, string $base_dir ) {
		$this->host = trim( $host );
		$this->port = $port;
		$this->username = trim( $username );
		$this->password = trim( $password );
		$this->base_dir = trim( $base_dir );
	}
}
