<?php
/**
 * Implementation of FTP credentials holder.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * FTP credentials holder and UI oriented helper.
 *
 * @since 1.0.0
 */
class FTP_Credentials implements Connection_Credentials {

	/**
	 * The name used for the host name input in the form.
	 *
	 * @since 1.0.0
	 */
	const HOST_FORM_NAME = 'calm_ftp_host';

	/**
	 * The name used for the ftp port input in the form.
	 *
	 * @since 1.0.0
	 */
	const PORT_FORM_NAME = 'calm_ftp_port';

	/**
	 * The name used for the username input in the form.
	 *
	 * @since 1.0.0
	 */
	const USERNAME_FORM_NAME = 'calm_ftp_username';

	/**
	 * The name used for the password input in the form.
	 *
	 * @since 1.0.0
	 */
	const PASSWORD_FORM_NAME = 'calm_ftp_password';

	/**
	 * The name used for the base directory input in the form.
	 *
	 * @since 1.0.0
	 */
	const BASEDIR_FORM_NAME = 'calm_ftp_base';

	/**
	 * Hostname or IP address of the FTP server.
	 *
	 * @since 1.0.0
	 */
	protected string $host;

	/**
	 * Port number on which the FTP server accepts connections.
	 *
	 * @since 1.0.0
	 */
	protected int $port;

	/**
	 * User name of the FTP account.
	 *
	 * @since 1.0.0
	 */
	protected string $username;

	/**
	 * Password of the FTP account.
	 *
	 * @since 1.0.0
	 */
	protected string $password;

	/**
	 * Server directory that corresponds to the FTP account's root directory.
	 *
	 * @since 1.0.0
	 */
	protected string $base_dir;

	/**
	 * Whether this instance has successfully checked its FTP connection.
	 *
	 * @since 1.0.0
	 */
	private bool $connection_checked = false;

	/**
	 * Construct an object based on given credentials information.
	 *
	 * @since 1.0.0
	 *
	 * @param FTP_Credentials_Parameters $parameters FTP connection values.
	 *
	 * @throws \DomainException If any of the parameters did not validate. The message incates which
	 *                          parameters had failed validation.
	 */
	public function __construct( FTP_Credentials_Parameters $parameters ) {
		$this->host     = $parameters->host;
		$this->port     = $parameters->port;
		$this->username = $parameters->username;
		$this->password = $parameters->password;
		$this->base_dir = $parameters->base_dir;

		if ( '' === $this->host ) {
			throw new \DomainException( 'FTP hostname cannot be empty.' );
		}

		if ( $this->port < 1 || $this->port > 65535 ) {
			throw new \DomainException( 'Invalid FTP server port.' );
		}

		if ( '' === $this->username ) {
			throw new \DomainException( 'FTP user name cannot be empty.' );
		}

		if ( '' === $this->password ) {
			throw new \DomainException( 'FTP password cannot be empty.' );
		}

		if ( false !== strpbrk( $this->username . $this->password, "\r\n" ) ) {
			throw new \DomainException( 'FTP credentials cannot contain line breaks.' );
		}

		$base = wp_normalize_path( $this->base_dir );
		$root = wp_normalize_path( ABSPATH );

		if ( '' === $this->base_dir || ! is_dir( $this->base_dir ) || ( $root !== $base && ! str_starts_with( $root, rtrim( $base, '/' ) . '/' ) ) ) {
			throw new \DomainException( 'The FTP base directory must contain the calmPress installation.' );
		}
	}

	/**
	 * Parse and validate submitted FTP credential fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars  Slashed form fields. Every key must be present with a string value:
	 *                     - calm_ftp_host: Hostname or IP address of the FTP server.
	 *                     - calm_ftp_port: Server port as a decimal string from 1 to 65535.
	 *                     - calm_ftp_username: Username for the FTP account.
	 *                     - calm_ftp_password: Password for the FTP account.
	 *                     - calm_ftp_base: Absolute server path corresponding to the account's root directory.
	 * @param array $files Unused; present for compatibility with the Connection_Credentials interface.
	 *
	 * @return FTP_Credentials_Parameters|\WP_Error Connection values, or validation problems.
	 *
	 * @throws \LogicException If a required form field is missing or has an unexpected type.
	 */
	public static function parse_parameters( array $vars, array $files = [] ): FTP_Credentials_Parameters|\WP_Error {
		$fields = [
			self::HOST_FORM_NAME,
			self::PORT_FORM_NAME,
			self::USERNAME_FORM_NAME,
			self::PASSWORD_FORM_NAME,
			self::BASEDIR_FORM_NAME,
		];

		foreach ( $fields as $field ) {
			if ( ! isset( $vars[ $field ] ) || ! is_string( $vars[ $field ] ) ) {
				throw new \LogicException( sprintf( 'Missing or invalid FTP connection parameter: %s', $field ) );
			}
		}

		$host     = wp_unslash( $vars[ self::HOST_FORM_NAME ] );
		$port     = filter_var( $vars[ self::PORT_FORM_NAME ], FILTER_VALIDATE_INT );
		$username = wp_unslash( $vars[ self::USERNAME_FORM_NAME ] );
		$password = wp_unslash( $vars[ self::PASSWORD_FORM_NAME ] );
		$base_dir = wp_unslash( $vars[ self::BASEDIR_FORM_NAME ] );

		$errors = new \WP_Error();

		if ( '' === trim( $host ) ) {
			$errors->add( 'host', __( 'Hostname cannot be empty.' ) );
		}

		if ( false === $port || $port < 1 || $port > 65535 ) {
			$errors->add( 'port', __( 'The Port Number should be in the range of 1 to 65535' ) );
		}

		if ( '' === trim( $username ) ) {
			$errors->add( 'username', __( 'User name cannot be empty.' ) );
		}

		if ( '' === trim( $password ) ) {
			$errors->add( 'password', __( 'Password cannot be empty.' ) );
		}

		$base = wp_normalize_path( trim( $base_dir ) );
		$root = wp_normalize_path( ABSPATH );

		if ( '' === trim( $base_dir ) || ! is_dir( trim( $base_dir ) ) || ( $root !== $base && ! str_starts_with( $root, rtrim( $base, '/' ) . '/' ) ) ) {
			$errors->add( 'base_dir', __( 'calmPress root directory cannot be accessed with this Base directory' ) );
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return new FTP_Credentials_Parameters( $host, $port, $username, $password, $base_dir );
	}

	/**
	 * Renders a complete FTP credentials form.
	 *
	 * Values supplied in the initial state override the form defaults and are keyed by input name.
	 * See form_fields() for the accepted keys and their meanings. Absent fields
	 * use form defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values keyed by the calm_ftp_* input names documented in form_fields(). Pass [] for the defaults.
	 * @param string $nonce_name   Nonce action and prefix for its field name.
	 * @param string $submit_label Text displayed on the submit button.
	 *
	 * @return string Form HTML.
	 *
	 */
	public static function form( array $initial_state, string $nonce_name, string $submit_label ): string {
		$html = '<form class="calm-credentials-form calm-validate" action="" method="post" enctype="multipart/form-data" novalidate>';
		$html .= wp_nonce_field( $nonce_name, $nonce_name . '_nonce', true, false );
		$html .= '<h2 id="connection-credentials">' . esc_html__( 'Connection credentials' ) . '</h2>';
		$html .= '<p class="description">' . esc_html__( 'Get the FTP account details from your hosting control panel or server administrator. Use an account that can write to this installation.' ) . '</p>';
		$html .= '<table class="form-table"><tbody>' . self::form_fields( $initial_state ) . '</tbody></table>';
		$html .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary button-large" value="' . esc_attr( $submit_label ) . '"></p>';
		$html .= '</form>';
		wp_enqueue_script( 'calm-form-validate' );

		return $html;
	}

	/**
	 * Returns table rows for the FTP credential fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values supplied in the array override the form defaults for these input names:
	 *                             - calm_ftp_host: FTP server hostname or IP address.
	 *                             - calm_ftp_port: FTP server port number.
	 *                             - calm_ftp_base: Server directory corresponding to the FTP account root.
	 *                             - calm_ftp_username: FTP account user name.
	 *                             - calm_ftp_password: FTP account password.
	 *                             Other keys are ignored. Pass [] to use all defaults.
	 *
	 * @return string HTML table rows.
	 */
	public static function form_fields( array $initial_state ): string {
		$initial_state = wp_parse_args( $initial_state, [
			self::HOST_FORM_NAME => '127.0.0.1',
			self::PORT_FORM_NAME => 21,
			self::BASEDIR_FORM_NAME => '',
			self::USERNAME_FORM_NAME => '',
			self::PASSWORD_FORM_NAME => '',
		] );
		$host_name     = esc_attr( self::HOST_FORM_NAME );
		$port_name     = esc_attr( self::PORT_FORM_NAME );
		$base_dir_name = esc_attr( self::BASEDIR_FORM_NAME );
		$username_name = esc_attr( self::USERNAME_FORM_NAME );
		$password_name = esc_attr( self::PASSWORD_FORM_NAME );
		$host          = esc_attr( $initial_state[ self::HOST_FORM_NAME ] );
		$port          = esc_attr( $initial_state[ self::PORT_FORM_NAME ] );
		$base_dir      = esc_attr( $initial_state[ self::BASEDIR_FORM_NAME ] );
		$username      = esc_attr( $initial_state[ self::USERNAME_FORM_NAME ] );
		$password      = esc_attr( $initial_state[ self::PASSWORD_FORM_NAME ] );
		$host_label    = esc_html__( 'Hostname' );
		$port_label    = esc_html__( 'Port number' );
		$base_label    = esc_html__( 'Base directory' );
		$user_label    = esc_html__( 'User name' );
		$password_label = esc_html__( 'Password' );
		$port_warning  = esc_html__( 'Should be between 1 and 65535 (inclusive).' );
		$empty_warning = esc_html__( 'Cannot be empty.' );
		$host_description = esc_html__( 'The FTP server address as seen by this web server. If FTP runs on the same machine, use localhost or 127.0.0.1.' );
		$port_description = esc_html__( 'The port number to which the FTP server listens, between 1 and 65535, usually 21' );
		$base_description = esc_html__( 'The path on the server\'s filesystem to the directory this FTP account sees as its root (/). The calmPress installation directory must be this directory or one of its subdirectories.' );
		$user_description = esc_html__( 'The name of the user which is allowed to access the FTP server' );
		$password_description = esc_html__( 'The password authenticating the user' );

		return <<<HTML
		<tr>
			<th><label for="{$host_name}">{$host_label}</label></th>
			<td>
				<input type="text" id="{$host_name}" name="{$host_name}" class="regular-text" value="{$host}" required validate="nonempty">
				<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
				<p class="description">{$host_description}</p>
			</td>
		</tr>
		<tr>
			<th><label for="{$port_name}">{$port_label}</label></th>
			<td>
				<input type="number" id="{$port_name}" name="{$port_name}" class="regular-text" value="{$port}" required min="1" max="65535" step="1" validate="integer-range">
				<p class="validate-failure-message" aria-live="polite">{$port_warning}</p>
				<p class="description">{$port_description}</p>
			</td>
		</tr>
		<tr>
			<th><label for="{$base_dir_name}">{$base_label}</label></th>
			<td>
				<input type="text" id="{$base_dir_name}" name="{$base_dir_name}" class="regular-text" value="{$base_dir}" required validate="nonempty">
				<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
				<p class="description">{$base_description}</p>
			</td>
		</tr>
		<tr>
			<th><label for="{$username_name}">{$user_label}</label></th>
			<td>
				<input type="text" id="{$username_name}" name="{$username_name}" class="regular-text" value="{$username}" autocomplete="section-calmpressftp username" required validate="nonempty">
				<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
				<p class="description">{$user_description}</p>
			</td>
		</tr>
		<tr>
			<th><label for="{$password_name}">{$password_label}</label></th>
			<td>
				<input type="password" id="{$password_name}" name="{$password_name}" class="regular-text" value="{$password}" autocomplete="section-calmpressftp current-password" required validate="nonempty">
				<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
				<p class="description">{$password_description}</p>
			</td>
		</tr>
		HTML;
	}

	/**
	 * Generate a full ftp:// based path to a file. The URLs generated are always pointing
	 * to an absolute path on the ftp server.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path. Must be an absolute path.
	 *
	 * @return string The ftp:// url representation of the file adjusted to the root of the
	 *                FTP server as indicated by the base directory.
	 *
	 * @throws \DomainException When the path is not absolute or is outside the account base directory.
	 */
	public function stream_url_from_path( string $path ) {
		$path = wp_normalize_path( $path );
		$base = rtrim( wp_normalize_path( $this->base_dir ), '/' );

		if ( '' === $base ) {
			$base = '/';
		}

		if ( ! path_is_absolute( $path )
			|| in_array( '..', explode( '/', $path ), true )
			|| ( $path !== $base && ! str_starts_with( $path, rtrim( $base, '/' ) . '/' ) ) ) {
			throw new \DomainException( 'Path must be absolute and within the FTP base directory.' );
		}

		$url = 'ftp://' . rawurlencode( $this->username ) . ':' . rawurlencode( $this->password ) . '@';

		// Brackets separate the colons in an IPv6 address from the port in the URL.
		$url .= filter_var( $this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )
			? '[' . $this->host . ']'
			: rawurlencode( $this->host );
		$url .= ':';
		$url .= $this->port;

		// Remove the base dir part of the file's path.
		$adjusted_path = substr( $path, strlen( $base ) );

		// Make sure the path is absolute.
		$adjusted_path = '/' . ltrim( $adjusted_path, '/' );

		return $url . $adjusted_path;
	}

	/**
	 * Verify FTP connectivity and generate the context for file access.
	 *
	 * @since 1.0.0
	 *
	 * @return resource stream context allowing file overwrite when accessed over FTP.
	 *
	 * @throws FTP_Connection_Exception If the FTP server cannot be reached, authentication fails, or the installation directory cannot be accessed.
	 */
	public function stream_context() {
		if ( $this->connection_checked ) {
			return stream_context_create( [ 'ftp' => [ 'overwrite' => true ] ] );
		}

		$host = filter_var( $this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )
			? '[' . $this->host . ']'
			: $this->host;
		$connection = false;

		// Connect to the FTP port first so an unreachable host or closed port can be reported separately from a failed login.
		try {
			$connection = @stream_socket_client( 'tcp://' . $host . ':' . $this->port, $error_code, $error_message, 3 );
		} catch ( \Throwable ) {
			// Use the same failure path as a false return from stream_socket_client().
		}

		if ( false === $connection ) {
			throw new FTP_Connection_Exception( 'FTP connection failed.', FTP_Connection_Exception::CONNECTION_FAILED );
		}

		// Check the FTP greeting and login so connection failures can be reported separately from file access failures.
		try {
			stream_set_timeout( $connection, 3 );
			$reply = self::ftp_reply( $connection );

			// A 120 greeting means the server is starting; wait for its 220 ready response.
			if ( 120 === $reply ) {
				$reply = self::ftp_reply( $connection );
			}

			// Any other greeting means the endpoint is not ready to accept FTP commands.
			if ( 220 !== $reply ) {
				throw new FTP_Connection_Exception( 'FTP greeting failed.', FTP_Connection_Exception::COMMUNICATION_FAILED );
			}

			self::ftp_command( $connection, 'USER ' . $this->username );
			$reply = self::ftp_reply( $connection );

			// The server may accept USER immediately (230) or ask for PASS (331).
			if ( 331 === $reply ) {
				self::ftp_command( $connection, 'PASS ' . $this->password );
				$reply = self::ftp_reply( $connection );
			}

			// Successful login returns 230; any other reply means login did not complete.
			if ( 230 !== $reply ) {
				throw new FTP_Connection_Exception( 'FTP login failed.', FTP_Connection_Exception::AUTHENTICATION_FAILED );
			}

			// Verify that the configured base directory maps the calmPress root to a directory this account can access.
			$base = rtrim( wp_normalize_path( $this->base_dir ), '/' );
			$root = wp_normalize_path( ABSPATH );
			$remote_root = '/' . ltrim( substr( $root, strlen( $base ) ), '/' );

			try {
				self::ftp_command( $connection, 'CWD ' . $remote_root );
				$reply = self::ftp_reply( $connection );
			} catch ( FTP_Connection_Exception $error ) {
				throw new FTP_Connection_Exception( 'FTP installation directory check failed.', FTP_Connection_Exception::INSTALLATION_INACCESSIBLE, $error );
			}

			if ( 250 !== $reply ) {
				throw new FTP_Connection_Exception( 'FTP installation directory inaccessible.', FTP_Connection_Exception::INSTALLATION_INACCESSIBLE );
			}
		} finally {
			@fwrite( $connection, "QUIT\r\n" );
			fclose( $connection );
		}

		$this->connection_checked = true;
		return stream_context_create( [ 'ftp' => [ 'overwrite' => true ] ] );
	}

	/**
	 * Send a command over an FTP control connection.
	 *
	 * @since 1.0.0
	 *
	 * @param resource $connection Open FTP control connection.
	 * @param string   $command    Command without a line ending.
	 *
	 * @throws FTP_Connection_Exception If the command cannot be sent.
	 */
	private static function ftp_command( $connection, string $command ): void {
		$bytes = $command . "\r\n";

		if ( strlen( $bytes ) !== @fwrite( $connection, $bytes ) ) {
			throw new FTP_Connection_Exception( 'Failed transmitting FTP command.', FTP_Connection_Exception::COMMUNICATION_FAILED );
		}
	}

	/**
	 * Read an FTP control response, including multiline replies.
	 *
	 * @since 1.0.0
	 *
	 * @param resource $connection Open FTP control connection.
	 *
	 * @return int Three-digit FTP response code.
	 *
	 * @throws FTP_Connection_Exception If the response is missing or malformed.
	 */
	private static function ftp_reply( $connection ): int {
		$line = @fgets( $connection, 512 );

		if ( false === $line || ! preg_match( '/^([1-5][0-9]{2})([ -])/', $line, $matches ) ) {
			throw new FTP_Connection_Exception( 'Invalid FTP response.', FTP_Connection_Exception::COMMUNICATION_FAILED );
		}

		$code = (int) $matches[1];

		if ( '-' === $matches[2] ) {
			for ( $index = 0; $index < 50; $index++ ) {
				$line = @fgets( $connection, 512 );

				if ( false === $line ) {
					break;
				}

				if ( str_starts_with( $line, $matches[1] . ' ' ) ) {
					return $code;
				}
			}

			throw new FTP_Connection_Exception( 'Incomplete FTP response.', FTP_Connection_Exception::COMMUNICATION_FAILED );
		}

		return $code;
	}

}
