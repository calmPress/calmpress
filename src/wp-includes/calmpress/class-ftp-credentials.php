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
	 * 
	 * @throws DomainException If any of the parameters did not validate. The message incates which
	 *                         parameters had failed validation.
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

		// Validate parameters.
		$validation_errors = static::validate(
			$this->host,
			$this->port,
			$this->username,
			$this->password,
			$this->base_dir
		);

		if ( ! empty( $validation_errors ) ) {
			$message = 'Validation failed for the following parameters: ';
			$keys    = array_keys( $validation_errors ); 
			throw new \DomainException( $message . '"' . join( '", "', $keys ) . '"' ); 
		}
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
	 * @return string[]|FTP_Credentials The credentials parsed from the request, or array
	 *                               containing validation errors if there are any.
	 *                               The strings are HTML escaped.
	 *
	 * @throws DomainException If the array do not include all the fields by their names.
	 */
	public static function credentials_from_request_vars( array $vars ) {
		$fields = [
			self::HOST_FORM_NAME,
			self::PORT_FORM_NAME,
			self::USERNAME_FORM_NAME,
			self::PASSWORD_FORM_NAME,
			self::BASEDIR_FORM_NAME,
		];

		// Detect missing fields.
		foreach ( $fields as $field ) {
			if ( ! isset( $vars[ $field ] ) ) {
				throw new \DomainException( 'Passed array do not include required field ' . $field );
			}
		}

		$validation_errors = static::validate(
			$vars[ self::HOST_FORM_NAME ],
			(int) $vars[ self::PORT_FORM_NAME ],
			$vars[ self::USERNAME_FORM_NAME ],
			$vars[ self::PASSWORD_FORM_NAME ],
			$vars[ self::BASEDIR_FORM_NAME ]
		);

		if ( ! empty( $validation_errors ) ) {
			return $validation_errors;
		}
				
		return new FTP_Credentials(
			$vars[ self::HOST_FORM_NAME ],
			(int) $vars[ self::PORT_FORM_NAME ],
			$vars[ self::USERNAME_FORM_NAME ],
			$vars[ self::PASSWORD_FORM_NAME ],
			$vars[ self::BASEDIR_FORM_NAME ]
		);
	}

	/**
	 * Credentials validation, validates that the various credentials make sense by themselves.
	 * 
	 * The aim is to give a more complete indication about problems with the various values that
	 * serve better a user facing notifications.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $host The hostname to use when connecting to the server.
	 * @param int    $port The port to use when connecting to the server.
	 * @param string $username The username to use when connecting to the server.
	 * @param string $password The password to use when connecting to the server.
	 * @param string $base_dir The base directory of the account on the server.
	 * 
	 * @return string[] The array has an element per parameter in which an error was detected. When
	 *                  all validations pass the array will be empty.
	 *                  The returned strings are HTML escaped.
	 */
	public static function validate( string $host,
									int $port,
									string $username,
									string $password,
									string $base_dir ) : array {
		$errors = [];

		// Validate host is valid domaine name or IP address.
		if ( ! filter_var( trim( $host ), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) &&
			 ! filter_var( trim( $host ), FILTER_VALIDATE_IP ) ) {
			$errors['host'] = esc_html__( 'The Hostname is not a valid domain name or IP address' );
		}

		// Validate port.
		if ( $port < 1 || $port > 65535 ) {
			$errors['port'] = esc_html__( 'The Port Number should be in the range of 1 to 65535' );
		}
		
		// Validate that if a password is given, a username is given as well.

		$username = trim( $username );
		$password = trim( $password );
		if ( ! empty( $password ) && empty( $username ) ) {
			$errors['username'] = esc_html__( 'A Password was given without a Username' );
		}

		// Validate that the root directory of calmPress can be accessed,
		// and that base_dir is actual directory.
		$base_dir = trim( $base_dir );
		if ( 0 !== strpos( ABSPATH, $base_dir ) || ! is_dir( $base_dir ) ) {
			$errors['base_dir'] = esc_html__( 'calmPress root directory can not be accessed with this Base directory' );
		}

		return $errors;
	}

	/**
	 * Return an HTML form to enter FTP credentials.
	 *
	 * @since 1.0.0
	 * 
	 * @param array $current The values passed by the user, probably in the $_POST variable.
	 * 
	 * @return string
	 */
	public static function form( array $current ) : string {
		$ret = '';

		$labels_for_setting = [
			self::HOST_FORM_NAME     => [
				'label'       => __( 'Hostname' ),
				'description' => __( 'the IP address or domain name of the machine where the FTP server is located from the POV of the server. Usually it is on the same machine as the site which means localhost or 127.0.0.1 should be good' ),
				'type'        => 'text',
				'attr'        => 'host',
				'extra_attrs' => 'required',
				'default'     => '',
			],
			self::PORT_FORM_NAME     => [
				'label'       => __( 'Port number' ),
				'description' => __( 'The port number to which the FTP server listens, usually 21' ),
				'type'        => 'number',
				'attr'        => 'port',
				'extra_attrs' => 'min="1" max="65535"',
				'default'     => 21,
			],
			self::USERNAME_FORM_NAME => [
				'label'       => __( 'User name' ),
				'description' => __( 'The name of the user which is allowed to access the FTP server' ),
				'type'        => 'text',
				'attr'        => 'username',
				'extra_attrs' => '',
				'default'     => '',
			],
			self::PASSWORD_FORM_NAME => [
				'label'       => __( 'Password' ),
				'description' => __( 'The password authenticating the user' ),
				'type'        => 'password',
				'attr'        => 'password',
				'extra_attrs' => '',
				'default'     => '',
			],
			self::BASEDIR_FORM_NAME  => [
				'label'       => __( 'Base directory' ),
				'description' => __( 'The directory to which the root FTP directory maps. Usually it is the server\'s root directory /' ),
				'type'        => 'text',
				'attr'        => 'base_dir',
				'validation'  => '',
				'extra_attrs' => 'required',
				'default'     => realpath( '/' ),
			],
		];

		foreach ( $labels_for_setting as $name => $labels ) {
			$name_id     = esc_attr( $name );
			$label       = esc_html( $labels['label'] );
			$description = esc_html( $labels['description'] );
			$type        = esc_attr( $labels['type'] );
			if ( isset( $current[ $name ] ) ) {
				$value = esc_attr( wp_unslash( $current[ $name ] ) );
			} else {
				$value = esc_attr( $labels['default'] );
			}
			$extra_attrs = $labels['extra_attrs'];
			$ret .= <<<EOT
<tr>
	<th>
		<label for="$name_id">$label</label>
	</th>
	<td>
		<input type="$type" id="$name_id" class="regular-text" name="$name_id" value="$value" $extra_attrs>
		<p class="description">$description</p>
	</td>
</tr>

EOT;
		}

		return $ret;
	}

	/**
	 * Generate a full ftp:// based path to a file. The URLs generated are always pointing
	 * to an absolute path on the ftp server.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path. Must be an absoluted path. 
	 *
	 * @return string The ftp:// representation of the file.
	 */
	public function ftp_url_for_path( string $path ) {

		if ( 0 !== strpos( $path, rtrim( ABSPATH , '/' ) ) ) {
			throw new \DomainException( '"' . $path . '" is not in the current calmPress directories' );
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

		// Remove the base dir part of the file's path.
		$adjusted_path = wp_normalize_path( substr( $path, strlen( $this->base_dir ) ) );

		// Make sure the path is absolute.
		$adjusted_path = '/' . ltrim( $adjusted_path, '/' );

		//
		return $url . $adjusted_path;
	}

	/**
	 * Helper to generate the context with overwrite option for FTP file access.
	 *
	 * @since 1.0.0
	 *
	 * @return resource stream context allowing file overwrite.
	 */
	public static function stream_context() {
		// Allows overwriting of existing files on the remote FTP server.
		$stream_options = [ 'ftp' => [ 'overwrite' => true ] ];

		// Creates a stream context resource with the defined options.
		$stream_context = stream_context_create( $stream_options );

		return $stream_context;
	}
}
