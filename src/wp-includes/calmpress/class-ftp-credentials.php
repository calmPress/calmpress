<?php
/**
 * Utility functions for displaying a credentials and parsing them from the request.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Utility functions for displaying a FTP credentials and parsing them from the request.
 *
 * @since 1.0.0
 */
class FTP_Credentials {

	/**
	 * The name used for the host name input in the form.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const HOST_FORM_NAME = 'calm_ftp_host';

	/**
	 * The name used for the ftp port input in the form.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const PORT_FORM_NAME = 'calm_ftp_port';

	/**
	 * The name used for the username input in the form.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const USERNAME_FORM_NAME = 'calm_ftp_username';

	/**
	 * The name used for the password input in the form.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const PASSWORD_FORM_NAME = 'calm_ftp_password';

	/**
	 * The name used for the base directory input in the form.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	const BASEDIR_FORM_NAME = 'calm_ftp_base';

	/**
	 * The hostname of the server where the FTP server is located.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected $host;

	/**
	 * The port to which the FTP server is listening.
	 *
	 * @var int
	 *
	 * @since 1.0.0
	 */
	protected $port;

	/**
	 * The username to use for authentication.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected $username;

	/**
	 * The password to use for authentication.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected $password;

	/**
	 * The base root directory of the account.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected $base_dir;

	/**
	 * Construct an object based on given credentials information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $host The hostname to use when connecting to the server.
	 * @param int    $port The port to use when connecting to the server.
	 * @param string $username The username to use when connecting to the server.
	 * @param string $password The password to use when connecting to the server.
	 * @param string $base_dir The base directory of the account on the server.
	 */
	public function __construct( string $host,
								int $port,
								string $username,
								string $password,
								string $base_dir ) {
		$this->host     = $host;
		$this->port     = $port;
		$this->username = $username;
		$this->password = $password;
		$this->base_dir = $base_dir;
	}

	/**
	 * The FTP server host name part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @returns string
	 */
	public function host() {
		return $this->host;
	}

	/**
	 * The FTP server port number part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @returns int
	 */
	public function port() {
		return $this->port;
	}

	/**
	 * The username to authenticate with the FTP server part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @returns string
	 */
	public function username() {
		return $this->username;
	}

	/**
	 * The password to authenticate with the FTP server part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @returns string
	 */
	public function password() {
		return $this->password;
	}

	/**
	 * The directory on which the root FTP directory for the authenticated user is mapped
	 * part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @returns string
	 */
	public function base_dir() {
		return $this->base_dir;
	}

	/**
	 * Try to find credentials in the current $_POST request, and returns a
	 * credentials object if found.
	 *
	 * For flexibility and better code structure the request parameters are passed
	 * as an array, but the assumption is that that the array is $_POST or a good
	 * mimic of it.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars An array of items in the request. Basically $_POST or
	 *                    something that mimics it well.
	 *
	 * @returns FTP_Credentials|null The credentials parsed from the request, or null
	 *                               if there are no valid settings.
	 */
	public static function credentials_from_request_vars( array $vars ) {
		if ( ! empty( $vars ) ) {
			if ( isset( $vars[ self::HOST_FORM_NAME ] )
				&& isset( $vars[ self::PORT_FORM_NAME ] )
				&& isset( $vars[ self::USERNAME_FORM_NAME ] )
				&& isset( $vars[ self::PASSWORD_FORM_NAME ] )
				&& isset( $vars[ self::BASEDIR_FORM_NAME ] )
			) {
				return new FTP_Credentials(
					trim( wp_unslash( $vars[ self::HOST_FORM_NAME ] ) ),
					(int) trim( wp_unslash( $vars[ self::PORT_FORM_NAME ] ) ),
					trim( wp_unslash( $vars[ self::USERNAME_FORM_NAME ] ) ),
					trim( wp_unslash( $vars[ self::PASSWORD_FORM_NAME ] ) ),
					trim( wp_unslash( $vars[ self::BASEDIR_FORM_NAME ] ) )
				);
			}
		}

		return null;
	}

	/**
	 * Output an .htaccess needs to be resaved admin notice when the content of
	 * the WordPress section of the .htaccess file is not the same as the one
	 * calculated in code.
	 *
	 * The notice is not displayed on multisite setup, and it is displayed only
	 * for admins when the site uses apache.
	 *
	 * @since 1.0.0
	 */
	public function form() {
		$ret = '';

		$labels_for_setting = [
			self::HOST_FORM_NAME     => [
				'label'       => __( 'Hostname' ),
				'description' => __( 'the IP address or DNS of the machine where the FTP server is located. Usually it is on the same machine as the site which means localhost should be good' ),
				'attr'        => 'host',
			],
			self::PORT_FORM_NAME     => [
				'label'       => __( 'Port number' ),
				'description' => __( 'The port number to which the FTP server listens, usually 21' ),
				'attr'        => 'port',
			],
			self::USERNAME_FORM_NAME => [
				'label'       => __( 'User name' ),
				'description' => __( 'The name of the user which is allowed to access the FTP server' ),
				'attr'        => 'username',
			],
			self::PASSWORD_FORM_NAME => [
				'label'       => __( 'Password' ),
				'description' => __( 'The password authenticating the user' ),
				'attr'        => 'password',
			],
			self::BASEDIR_FORM_NAME  => [
				'label'       => __( 'Base directory' ),
				'description' => __( 'The directory to which the root FTP directory maps. Usually it is the server\'s root directory /' ),
				'attr'        => 'base_dir',
			],
		];

		foreach ( $labels_for_setting as $name => $labels ) {
			$name_id     = esc_attr( $name );
			$label       = esc_html( $labels['label'] );
			$description = esc_html( $labels['description'] );
			$value       = esc_attr( $this->{$labels['attr']} );

			$type = 'text';
			if ( self::PASSWORD_FORM_NAME === $name ) {
				$type = 'password';
			}

			$ret .= <<<EOT
<tr>
	<th>
		<label for="$name_id">$label</label>
	</th>
	<td>
		<input type="$type" id="$name_id" class="regular-text" name="$name_id" value="$value">
		<p class="description">$description</p>
	</td>
</tr>

EOT;
		}

		return $ret;
	}

	/**
	 * Returns a descriptive error text identifying the problem of accessing the
	 * FTP server using the credentials.
	 *
	 * To be able to do anything meaningful it requires that the PHP FTP module is active.
	 *
	 * @since 1.0.0
	 *
	 * @returns string An HTML escaped descriptive string for the problem if one found, otherwise
	 *                 an empty string (which just indicates that problem could not
	 *                 be identified, and not that there is no problem).
	 */
	public function human_readable_state() {
		// Test if the FTP module is installed as we need some of its APIs.
		if ( ! function_exists( 'ftp_connect' ) ) {
			return '';
		}

		// Test if host name and port are correct.
		$conn = ftp_connect( $this->host, $this->port, 1 );
		if ( ! $conn ) {
			return esc_html__( 'Host name and/or Port number values are wrong' );
		}

		// Test if username and password are correct.
		if ( ! @ftp_login( $conn, $this->username, $this->password ) ) {
			return esc_html__( 'User name and/or Password are wrong' );
		}
		ftp_pasv( $conn, true );

		// Test if the base directory is correct.
		$adjusted_root = substr( ABSPATH, strlen( $this->base_dir ) );
		$dir = ftp_nlist( $conn, $adjusted_root );
		if ( false === $dir || ! in_array( 'wp-load.php', $dir, true ) ) {
			return esc_html__( 'Base directory might be wrong' );
		}

		return '';

	}
}
