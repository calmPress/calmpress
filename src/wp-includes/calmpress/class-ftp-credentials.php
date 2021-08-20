<?php
/**
 * Utility functions for displaying a credentials form and parsing the values from the request.
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
		$this->host     = trim( $host );
		$this->port     = $port;
		$this->username = trim( $username );
		$this->password = trim( $password );
		$this->base_dir = trim( $base_dir );
	}

	/**
	 * The FTP server host name part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function host() : string {
		return $this->host;
	}

	/**
	 * The FTP server port number part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @returns int
	 */
	public function port() : int {
		return $this->port;
	}

	/**
	 * The username to authenticate with the FTP server part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function username() : string {
		return $this->username;
	}

	/**
	 * The password to authenticate with the FTP server part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function password() : string {
		return $this->password;
	}

	/**
	 * The directory on which the root FTP directory for the authenticated user is mapped
	 * part of the credentials.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function base_dir() : string {
		return $this->base_dir;
	}

	/**
	 * Try to find credentials in an array which will most likely be current $_POST request,
	 * and returns a credentials object if found the required fields are found.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars An array of items in the request. Probably $_POST or
	 *                    something that mimics it well.
	 *
	 * @return ?FTP_Credentials The credentials parsed from the request, or null
	 *                          if not all of the settings are valid.
	 */
	public static function credentials_from_request_vars( array $vars ) : ?FTP_Credentials {
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
	 * Return an HTML form to enter FTP credentials.
	 *
	 * @since 1.0.0
	 * 
	 * @return string
	 */
	public function form() : string {
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
	 * Check if a path can be accessible on the FTP server based on the base dir
	 * configuration.
	 *
	 * If the path is outside, raise an exception.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The path to check.
	 *
	 * @return string The path relative to the FTP server root (base_dir).
	 * 
	 * @throws DomainException If $path is not under the known base_dir.
	 */
	private function adjust_path_to_ftp_root( $path ) {

		// Make sure the location is accessible under the FTP server.
		// Using case insensitive here is not great but probably good enough for
		// real life usage.
		if ( 0 !== stripos( $path, $this->base_dir ) ) {
			throw new DomainException( '"' . $path . '" is not accessible as FTP root is "' . $this->base_dir . '"' );
		}

		// Remove the base dir part of the file's path.
		$adjusted_path = substr( $path, strlen( $this->base_dir ) );

		// Make sure the returned path is absolute.
		$adjusted_path = '\\' . ltrim( '\\', $adjusted_path );

		return $adjusted_path;
	}

	/**
	 * Helper to generate a full ftp:// based path to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file The file path.
	 *
	 * @return string The ftp:// representation of the file.
	 */
	private function ftp_url_for_path( string $path ) {
		if ( ! path_is_absolute( $path ) ) {
			throw new DomainException( '"' . $path . '" is not absolute " path' );
		}

		$url = 'ftp://';
		if ( ! empty( $this->username ) ) {
			// we have a user name, add a username:password@ to the url.
			$url .= rawurlencode( $this->username );
			$url .= ':';
			$url .= rawurlencode( $this->password );
			$url .= '@';
		}

		// Add the host:port part.
		$url .= $this->host;
		$url .= ':';
		$url .= $this->port;

		return $url . $this->adjust_path_to_ftp_root( $path );
	}

	/**
	 * Helper to generate the context with overwrite option for FTP file access.
	 *
	 * @since 1.0.0
	 *
	 * @return resource stream context allowing file overwrite.
	 */
	public static function stream_context() : resource {
		// Allows overwriting of existing files on the remote FTP server.
		$stream_options = [ 'ftp' => [ 'overwrite' => true ] ];

		// Creates a stream context resource with the defined options.
		$stream_context = stream_context_create( $stream_options );

		return $stream_context;
	}


}
