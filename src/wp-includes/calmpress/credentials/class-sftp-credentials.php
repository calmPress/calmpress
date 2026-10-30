<?php
/**
 * SFTP credentials and their form.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

use phpseclib4\Crypt\Common\PrivateKey;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Net\SFTP;
use phpseclib4\Net\SFTP\Stream;

/**
 * Provides password or private-key authentication for SFTP file streams.
 *
 * @since 1.0.0
 */
class SFTP_Credentials implements Connection_Credentials {

	/**
	 * Form field name for the SFTP server hostname.
	 *
	 * @since 1.0.0
	 */
	public const HOST_FORM_NAME       = 'calm_sftp_host';

	/**
	 * Form field name for the SFTP server port.
	 *
	 * @since 1.0.0
	 */
	public const PORT_FORM_NAME       = 'calm_sftp_port';

	/**
	 * Form field name for the SFTP account username.
	 *
	 * @since 1.0.0
	 */
	public const USERNAME_FORM_NAME   = 'calm_sftp_username';

	/**
	 * Form field name for the SFTP login password.
	 *
	 * @since 1.0.0
	 */
	public const PASSWORD_FORM_NAME   = 'calm_sftp_password';

	/**
	 * Form field name for the local directory mapped to the remote account root.
	 *
	 * @since 1.0.0
	 */
	public const BASEDIR_FORM_NAME    = 'calm_sftp_base';

	/**
	 * Form field name for an uploaded private-key file.
	 *
	 * @since 1.0.0
	 */
	public const PRIVATE_KEY_FORM_NAME = 'calm_sftp_private_key';

	/**
	 * Form field name for pasted private-key content.
	 *
	 * @since 1.0.0
	 */
	public const PRIVATE_KEY_TEXT_FORM_NAME = 'calm_sftp_private_key_text';

	/**
	 * Form field name for the selected authentication method.
	 *
	 * @since 1.0.0
	 */
	public const AUTH_METHOD_FORM_NAME = 'calm_sftp_auth_method';

	/**
	 * Form field name for the private-key passphrase.
	 *
	 * @since 1.0.0
	 */
	public const PASSPHRASE_FORM_NAME = 'calm_sftp_passphrase';

	/**
	 * Hostname or IP address of the SFTP server.
	 *
	 * @since 1.0.0
	 */
	protected string $host;

	/**
	 * Port number on which the SFTP server accepts connections.
	 *
	 * @since 1.0.0
	 */
	protected int $port;

	/**
	 * User name of the SFTP account.
	 *
	 * @since 1.0.0
	 */
	protected string $username;

	/**
	 * Password of the SFTP account when password authentication is selected.
	 *
	 * @since 1.0.0
	 */
	protected string $password;

	/**
	 * Server directory that corresponds to the SFTP account's root directory.
	 *
	 * @since 1.0.0
	 */
	protected string $base_dir;

	/**
	 * Loaded key used for authentication, or null when password authentication is used.
	 *
	 * @since 1.0.0
	 */
	protected ?PrivateKey $private_key = null;

	/**
	 * Creates credentials without opening a network connection.
	 * If both a password and a private key are provided, the private key takes precedence.
	 *
	 * @since 1.0.0
	 *
	 * @param SFTP_Credentials_Parameters $parameters SFTP connection values.
	 *
	 * @throws \DomainException If the credentials or private key are invalid.
	 */
	public function __construct( SFTP_Credentials_Parameters $parameters ) {
		$this->host     = $parameters->host;
		$this->port     = $parameters->port;
		$this->username = $parameters->username;
		$this->password = $parameters->password;
		$this->base_dir = $parameters->base_dir;

		if ( '' === $this->host ) {
			throw new \DomainException( 'SFTP hostname cannot be empty.' );
		}

		if ( $this->port < 1 || $this->port > 65535 ) {
			throw new \DomainException( 'Invalid SFTP server port.' );
		}

		if ( '' === $this->username ) {
			throw new \DomainException( 'An SFTP user name is required.' );
		}

		if ( '' === $this->password && '' === $parameters->private_key_content ) {
			throw new \DomainException( 'An SFTP authentication method is required.' );
		}

		$root = wp_normalize_path( ABSPATH );
		$base = wp_normalize_path( $this->base_dir );

		if ( '' === $this->base_dir || ! is_dir( $this->base_dir ) || ( $root !== $base && ! str_starts_with( $root, rtrim( $base, '/' ) . '/' ) ) ) {
			throw new \DomainException( 'The SFTP base directory must contain the calmPress installation.' );
		}

		if ( '' !== $parameters->private_key_content ) {
			try {
				$key = PublicKeyLoader::loadPrivateKey( $parameters->private_key_content, $parameters->passphrase );
			} catch ( \Throwable $error ) {
				throw new \DomainException( 'The private key content and passphrase do not produce a usable private key.', 0, $error );
			}

			$this->private_key = $key;
		}

		Stream::register( 'calmpress-sftp' );
	}

	/**
	 * Parse and validate submitted SFTP credential fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars  Slashed form fields. Every key must be present with a string value;
	 *                     fields for inactive authentication methods are ignored:
	 *                     - calm_sftp_host: Hostname or IP address of the SFTP server.
	 *                     - calm_sftp_port: Server port as a decimal string from 1 to 65535.
	 *                     - calm_sftp_username: Username for the SFTP account.
	 *                     - calm_sftp_base: Absolute server path corresponding to the account's root directory.
	 *                     - calm_sftp_auth_method: One of password, key_file, or key_text.
	 *                     - calm_sftp_password: Account password when using password authentication.
	 *                     - calm_sftp_private_key_text: Pasted private key when using key_text.
	 *                     - calm_sftp_passphrase: Passphrase for an encrypted private key, if needed.
	 * @param array $files Uploaded files keyed by input name. For key_file authentication,
	 *                     calm_sftp_private_key contains a $_FILES entry with an integer
	 *                     error code and a tmp_name path when the upload succeeds. It may
	 *                     be absent when no file was selected.
	 *
	 * @return SFTP_Credentials_Parameters|\WP_Error Connection values, or validation problems.
	 *
	 * @throws \LogicException If a required form field is missing, has an unexpected type, or specifies an unsupported authentication method.
	 */
	public static function parse_parameters( array $vars, array $files ): SFTP_Credentials_Parameters|\WP_Error {
		$fields = [
			self::HOST_FORM_NAME,
			self::PORT_FORM_NAME,
			self::USERNAME_FORM_NAME,
			self::BASEDIR_FORM_NAME,
			self::AUTH_METHOD_FORM_NAME,
			self::PASSWORD_FORM_NAME,
			self::PRIVATE_KEY_TEXT_FORM_NAME,
			self::PASSPHRASE_FORM_NAME,
		];

		foreach ( $fields as $field ) {
			if ( ! isset( $vars[ $field ] ) || ! is_string( $vars[ $field ] ) ) {
				throw new \LogicException( sprintf( 'Missing or invalid SFTP connection parameter: %s', $field ) );
			}
		}

		$host        = wp_unslash( $vars[ self::HOST_FORM_NAME ] );
		$port        = filter_var( $vars[ self::PORT_FORM_NAME ], FILTER_VALIDATE_INT );
		$username    = wp_unslash( $vars[ self::USERNAME_FORM_NAME ] );
		$base_dir    = wp_unslash( $vars[ self::BASEDIR_FORM_NAME ] );
		$auth_method = wp_unslash( $vars[ self::AUTH_METHOD_FORM_NAME ] );
		$password    = '';
		$private_key_content = '';
		$passphrase  = '';
		$errors = new \WP_Error();

		switch ( $auth_method ) {
			case 'password':
				$password = wp_unslash( $vars[ self::PASSWORD_FORM_NAME ] );

				if ( '' === trim( $password ) ) {
					$errors->add( 'authentication', __( 'Password cannot be empty.' ) );
				}
				break;

			case 'key_file':
				$passphrase = wp_unslash( $vars[ self::PASSPHRASE_FORM_NAME ] );
				$upload = $files[ self::PRIVATE_KEY_FORM_NAME ] ?? null;
				$upload_error_message = __( 'No private key file was selected, or the upload failed. Select a file or paste the private key instead.' );

				if ( ! is_array( $upload ) || UPLOAD_ERR_OK !== ( $upload['error'] ?? null ) || ! isset( $upload['tmp_name'] ) || ! is_string( $upload['tmp_name'] ) || ! is_uploaded_file( $upload['tmp_name'] ) ) {
					$errors->add( 'private_key', $upload_error_message );
				} else {
					$private_key_content = @file_get_contents( $upload['tmp_name'] );

					if ( false === $private_key_content ) {
						trigger_error( 'Could not read the uploaded SFTP private key file.', E_USER_WARNING );
						$errors->add( 'private_key', $upload_error_message );
					}
				}
				break;

			case 'key_text':
				$passphrase = wp_unslash( $vars[ self::PASSPHRASE_FORM_NAME ] );
				$private_key_content = wp_unslash( $vars[ self::PRIVATE_KEY_TEXT_FORM_NAME ] );

				if ( '' === trim( $private_key_content ) ) {
					$errors->add( 'private_key', __( 'Private key cannot be empty.' ) );
				}
				break;

			default:
				throw new \LogicException( 'Invalid SFTP authentication method.' );
		}

		if ( '' === trim( $host ) ) {
			$errors->add( 'host', __( 'Hostname cannot be empty.' ) );
		}

		if ( false === $port || $port < 1 || $port > 65535 ) {
			$errors->add( 'port', __( 'Port number must be between 1 and 65535 (inclusive).' ) );
		}

		if ( '' === trim( $username ) ) {
			$errors->add( 'username', __( 'User name cannot be empty.' ) );
		}

		$root = wp_normalize_path( ABSPATH );
		$base = wp_normalize_path( trim( $base_dir ) );

		if ( '' === trim( $base_dir ) ) {
			$errors->add( 'base_dir', __( 'Base directory cannot be empty.' ) );
		} elseif ( ! is_dir( trim( $base_dir ) ) || ( $root !== $base && ! str_starts_with( $root, rtrim( $base, '/' ) . '/' ) ) ) {
			$errors->add( 'base_dir', __( 'calmPress root directory cannot be accessed with this Base directory' ) );
		}

		if ( ! $errors->has_errors() && '' !== $private_key_content ) {
			try {
				PublicKeyLoader::loadPrivateKey( $private_key_content, trim( $passphrase ) );
			} catch ( \Throwable $error ) {
				$errors->add( 'private_key', __( 'The combination of the private key and passphrase cannot be used for SFTP.' ) );
			}
		}

		if ( $errors->has_errors() ) {
			return $errors;
		}

		return new SFTP_Credentials_Parameters( $host, $port, $username, $password, $base_dir, $private_key_content, $passphrase );
	}

	/**
	 * Renders a complete SFTP credentials form.
	 *
	 * The initial state is an associative array of values keyed by the form
	 * input names: calm_sftp_host, calm_sftp_port,
	 * calm_sftp_base, calm_sftp_username, calm_sftp_auth_method (password,
	 * key_file, or key_text), calm_sftp_password, calm_sftp_private_key_text, and
	 * calm_sftp_passphrase. Absent fields use form defaults. Uploaded files are
	 * supplied separately when the form is submitted; secrets are not redisplayed.
	 * Other fields are ignored.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values keyed by the calm_sftp_* input names listed above. Pass [] for the defaults.
	 * @param string $nonce_name   Nonce action and prefix for its field name.
	 * @param string $submit_label Text displayed on the submit button.
	 *
	 * @return string Form HTML.
	 *
	 * @throws \DomainException If the authentication method is invalid.
	 */
	public static function form( array $initial_state, string $nonce_name, string $submit_label ): string {
		$id   = wp_unique_id( 'calm-sftp-credentials-' );
		$html = '<form class="calm-credentials-form calm-validate" action="" method="post" enctype="multipart/form-data" novalidate>';
		$html .= wp_nonce_field( $nonce_name, $nonce_name . '_nonce', true, false );
		$html .= '<div id="' . esc_attr( $id ) . '" class="calm-sftp-credentials">';
		$html .= '<h2 id="connection-credentials">' . esc_html__( 'Connection credentials' ) . '</h2>';
		$html .= '<p class="description">' . esc_html__( 'Get the SFTP account details from your hosting control panel or server administrator. Use an account that can write to this installation.' ) . '</p>';
		$html .= '<table class="form-table"><tbody>' . self::form_fields( $initial_state ) . '</tbody></table>';
		$html .= '</div>';
		$html .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary button-large" value="' . esc_attr( $submit_label ) . '"></p>';
		$html .= '</form>';
		wp_enqueue_script( 'calm-sftp-credentials' );

		return $html;
	}

	/**
	 * Returns table rows for the SFTP credential fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values supplied in this array override the form defaults, keyed by input name:
	 *                             - calm_sftp_host: Server hostname or IP address; defaults to 127.0.0.1.
	 *                             - calm_sftp_port: Server port; defaults to 22.
	 *                             - calm_sftp_base: Server path of the account's root directory; defaults to empty.
	 *                             - calm_sftp_username: SFTP account username; defaults to empty.
	 *                             - calm_sftp_auth_method: password, key_file, or key_text; defaults to password.
	 *                             - calm_sftp_password: Account password; defaults to empty and is not redisplayed.
	 *                             - calm_sftp_private_key_text: Pasted private key; defaults to empty and is not redisplayed.
	 *                             - calm_sftp_passphrase: Private key passphrase; defaults to empty and is not redisplayed.
	 *                             Other keys are ignored. Pass [] to use all defaults.
	 *
	 * @return string HTML table rows.
	 *
	 * @throws \DomainException If the authentication method is invalid.
	 */
	public static function form_fields( array $initial_state ): string {
		$initial_state = wp_parse_args( $initial_state, [
			self::HOST_FORM_NAME => '127.0.0.1',
			self::PORT_FORM_NAME => '22',
			self::BASEDIR_FORM_NAME => '',
			self::USERNAME_FORM_NAME => '',
			self::AUTH_METHOD_FORM_NAME => 'password',
			self::PASSWORD_FORM_NAME => '',
			self::PRIVATE_KEY_TEXT_FORM_NAME => '',
			self::PASSPHRASE_FORM_NAME => '',
		] );
		$auth_method = $initial_state[ self::AUTH_METHOD_FORM_NAME ];

		if ( ! in_array( $auth_method, [ 'password', 'key_file', 'key_text' ], true ) ) {
			throw new \DomainException( 'Invalid SFTP authentication method.' );
		}

		$host_name = esc_attr( self::HOST_FORM_NAME );
		$port_name = esc_attr( self::PORT_FORM_NAME );
		$base_name = esc_attr( self::BASEDIR_FORM_NAME );
		$user_name = esc_attr( self::USERNAME_FORM_NAME );
		$auth_name = esc_attr( self::AUTH_METHOD_FORM_NAME );
		$password_name = esc_attr( self::PASSWORD_FORM_NAME );
		$file_name = esc_attr( self::PRIVATE_KEY_FORM_NAME );
		$text_name = esc_attr( self::PRIVATE_KEY_TEXT_FORM_NAME );
		$passphrase_name = esc_attr( self::PASSPHRASE_FORM_NAME );
		$host = esc_attr( $initial_state[ self::HOST_FORM_NAME ] );
		$port = esc_attr( $initial_state[ self::PORT_FORM_NAME ] );
		$base = esc_attr( $initial_state[ self::BASEDIR_FORM_NAME ] );
		$user = esc_attr( $initial_state[ self::USERNAME_FORM_NAME ] );
		$password_selected = selected( $auth_method, 'password', false );
		$file_selected = selected( $auth_method, 'key_file', false );
		$text_selected = selected( $auth_method, 'key_text', false );
		$password_hidden = 'password' === $auth_method ? '' : ' hidden';
		$file_hidden = 'key_file' === $auth_method ? '' : ' hidden';
		$text_hidden = 'key_text' === $auth_method ? '' : ' hidden';
		$passphrase_hidden = 'password' === $auth_method ? ' hidden' : '';
		$password_required = 'password' === $auth_method ? ' required' : '';
		$file_required = 'key_file' === $auth_method ? ' required' : '';
		$text_required = 'key_text' === $auth_method ? ' required' : '';
		$host_label = esc_html__( 'Hostname' );
		$port_label = esc_html__( 'Port number' );
		$base_label = esc_html__( 'Base directory' );
		$auth_label = esc_html__( 'Authentication method' );
		$user_label = esc_html__( 'Username' );
		$password_label = esc_html__( 'Password' );
		$file_label = esc_html__( 'Private key file' );
		$text_label = esc_html__( 'Private key' );
		$passphrase_label = esc_html__( 'Private key passphrase' );
		$password_option = esc_html__( 'Username and password' );
		$file_option = esc_html__( 'Username and private key file' );
		$text_option = esc_html__( 'Username and pasted private key' );
		$empty_warning = esc_html__( 'Cannot be empty.' );
		$port_warning = esc_html__( 'Should be between 1 and 65535 (inclusive).' );
		$file_warning = esc_html__( 'A file must be selected.' );
		$host_description = esc_html__( 'The SFTP server address as seen by this web server. If SFTP runs on the same machine, use localhost or 127.0.0.1.' );
		$port_description = esc_html__( 'The SFTP server port, usually 22.' );
		$base_description = esc_html__( 'The path on the server\'s filesystem to the directory this SFTP account sees as its root (/). The calmPress installation directory must be this directory or one of its subdirectories.' );
		$user_description = esc_html__( 'The account used to access the SFTP server.' );
		$password_description = esc_html__( 'The password for the SFTP account.' );
		$file_description = esc_html__( 'Choose a private key file to upload.' );
		$text_description = esc_html__( 'Paste the private key.' );
		$passphrase_description = esc_html__( 'The passphrase needed to unlock the private key, if it is protected.' );

		return <<<HTML
		<tr><th><label for="{$host_name}">{$host_label}</label></th><td>
			<input class="regular-text" type="text" id="{$host_name}" name="{$host_name}" value="{$host}" required validate="nonempty">
			<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
			<p class="description">{$host_description}</p>
		</td></tr>
		<tr><th><label for="{$port_name}">{$port_label}</label></th><td>
			<input class="regular-text" type="number" id="{$port_name}" name="{$port_name}" value="{$port}" required min="1" max="65535" step="1" validate="integer-range">
			<p class="validate-failure-message" aria-live="polite">{$port_warning}</p>
			<p class="description">{$port_description}</p>
		</td></tr>
		<tr><th><label for="{$base_name}">{$base_label}</label></th><td>
			<input class="regular-text" type="text" id="{$base_name}" name="{$base_name}" value="{$base}" required validate="nonempty">
			<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
			<p class="description">{$base_description}</p>
		</td></tr>
		<tr><th><label for="{$auth_name}">{$auth_label}</label></th><td>
			<select id="{$auth_name}" name="{$auth_name}">
				<option value="password"{$password_selected}>{$password_option}</option>
				<option value="key_file"{$file_selected}>{$file_option}</option>
				<option value="key_text"{$text_selected}>{$text_option}</option>
			</select>
		</td></tr>
		<tr><th><label for="{$user_name}">{$user_label}</label></th><td>
			<input class="regular-text" type="text" id="{$user_name}" name="{$user_name}" value="{$user}" autocomplete="section-calmpresssftp username" required validate="nonempty">
			<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
			<p class="description">{$user_description}</p>
		</td></tr>
		<tr data-sftp-auth-method="password"{$password_hidden}><th><label for="{$password_name}">{$password_label}</label></th><td>
			<input class="regular-text" type="password" id="{$password_name}" name="{$password_name}" value="" autocomplete="section-calmpresssftp current-password" validate="nonempty" data-sftp-required="true"{$password_required}>
			<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
			<p class="description">{$password_description}</p>
		</td></tr>
		<tr data-sftp-auth-method="key_file"{$file_hidden}><th><label for="{$file_name}">{$file_label}</label></th><td>
			<input class="regular-text" type="file" id="{$file_name}" name="{$file_name}" validate="file" data-sftp-required="true"{$file_required}>
			<p class="validate-failure-message" aria-live="polite">{$file_warning}</p>
			<p class="description">{$file_description}</p>
		</td></tr>
		<tr data-sftp-auth-method="key_text"{$text_hidden}><th><label for="{$text_name}">{$text_label}</label></th><td>
			<textarea class="large-text code" rows="8" id="{$text_name}" name="{$text_name}" validate="nonempty" data-sftp-required="true"{$text_required}></textarea>
			<p class="validate-failure-message" aria-live="polite">{$empty_warning}</p>
			<p class="description">{$text_description}</p>
		</td></tr>
		<tr data-sftp-auth-method="key_file key_text"{$passphrase_hidden}><th><label for="{$passphrase_name}">{$passphrase_label}</label></th><td>
			<input class="regular-text" type="password" id="{$passphrase_name}" name="{$passphrase_name}" value="">
			<p class="description">{$passphrase_description}</p>
		</td></tr>
		HTML;
	}

	/**
	 * Returns the SFTP stream URL for a path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path within the account base directory.
	 *
	 * @return string SFTP stream URL.
	 *
	 * @throws \DomainException If the path is not absolute or is outside the account base directory.
	 */
	public function stream_url_from_path( string $path ) {
		$path = wp_normalize_path( $path );
		$base = rtrim( wp_normalize_path( $this->base_dir ), '/' );

		if ( '' === $base ) {
			$base = '/';
		}

		if ( ! path_is_absolute( $path )
			|| ( $path !== $base && ! str_starts_with( $path, rtrim( $base, '/' ) . '/' ) )
			|| in_array( '..', explode( '/', $path ), true ) ) {
			throw new \DomainException( 'Path must be absolute and within the SFTP base directory.' );
		}

		$relative_path = substr( $path, strlen( $base ) );

		// Brackets separate the colons in an IPv6 address from the port in the URL.
		$host = filter_var( $this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 )
			? '[' . $this->host . ']'
			: $this->host;
		return 'calmpress-sftp://' . $host . ':' . $this->port . '/' . ltrim( $relative_path, '/' );
	}

	/**
	 * Returns a stream context using the authenticated SFTP session.
	 *
	 * @since 1.0.0
	 *
	 * @return resource SFTP stream context.
	 *
	 * @throws SFTP_Connection_Exception If the SSH connection, authentication, or SFTP service fails.
	 */
	public function stream_context() {
		return stream_context_create( [ 'calmpress-sftp' => [ 'sftp' => $this->session() ] ] );
	}

	/**
	 * Connects and authenticates an SFTP session.
	 *
	 * @since 1.0.0
	 *
	 * @return SFTP Authenticated session.
	 *
	 * @throws SFTP_Connection_Exception If the SSH connection, authentication, or SFTP service fails.
	 */
	private function session(): SFTP {
		try {
			$session = new SFTP( $this->host, $this->port );
			$host_key = $session->getServerPublicHostKey();
		} catch ( \Throwable $error ) {
			throw new SFTP_Connection_Exception( 'SSH connection failed.', SFTP_Connection_Exception::SSH_CONNECTION_FAILED, $error );
		}

		if ( null === $host_key ) {
			throw new SFTP_Connection_Exception( 'SSH server signature invalid.', SFTP_Connection_Exception::SSH_SIGNATURE_INVALID );
		}

		// Use the private key when both a key and a password were supplied.
		try {
			$authenticated = $session->login( $this->username, $this->private_key ?? $this->password );
		} catch ( \Throwable $error ) {
			throw new SFTP_Connection_Exception( 'SFTP authentication failed.', SFTP_Connection_Exception::AUTHENTICATION_FAILED, $error );
		}

		if ( ! $authenticated ) {
			throw new SFTP_Connection_Exception( 'SFTP authentication failed.', SFTP_Connection_Exception::AUTHENTICATION_FAILED );
		}

		// Verify that the configured base directory maps the calmPress root to a directory this account can access.
		$base = rtrim( wp_normalize_path( $this->base_dir ), '/' );
		$root = wp_normalize_path( ABSPATH );
		$remote_root = '/' . ltrim( substr( $root, strlen( $base ) ), '/' );

		try {
			$root_accessible = $session->is_dir( $remote_root );
		} catch ( \Throwable $error ) {
			throw new SFTP_Connection_Exception( 'SFTP installation directory check failed.', SFTP_Connection_Exception::INSTALLATION_INACCESSIBLE, $error );
		}

		if ( ! $root_accessible ) {
			throw new SFTP_Connection_Exception( 'SFTP installation directory inaccessible.', SFTP_Connection_Exception::INSTALLATION_INACCESSIBLE );
		}

		return $session;
	}
}
