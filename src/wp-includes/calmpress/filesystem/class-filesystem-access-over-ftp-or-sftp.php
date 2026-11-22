<?php
/**
 * Filesystem access over FTP or SFTP.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Filesystem access over FTP or SFTP with a protocol selection form.
 *
 * @since 1.0.0
 */
class Filesystem_Access_Over_FTP_Or_SFTP implements Remote_Filesystem_Access {

	/**
	 * The selected filesystem access implementation.
	 *
	 * @since 1.0.0
	 */
	private readonly Filesystem_Access $credentials;

	/**
	 * Name of the protocol field in the form.
	 *
	 * @since 1.0.0
	 */
	public const PROTOCOL_FORM_NAME = 'calm_credentials_protocol';

	/**
	 * Use the selected filesystem access implementation.
	 *
	 * @since 1.0.0
	 *
	 * @param Filesystem_Access $credentials Selected filesystem access implementation.
	 */
	public function __construct( Filesystem_Access $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Change permissions on a path using these credentials.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute local path mapped by these credentials.
	 * @param int    $permissions Unix permission bits.
	 *
	 * @return bool Whether the permission change succeeded.
	 *
	 * @throws \DomainException If the selected filesystem implementation rejects the path or command.
	 * @throws Filesystem_Access_Exception If remote filesystem access fails.
	 */
	public function change_permissions( string $path, int $permissions ): bool {
		return $this->credentials->change_permissions( $path, $permissions );
	}

	/**
	 * Converts a path to a URL for the selected protocol.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute file path.
	 *
	 * @return string Stream URL.
	 *
	 * @throws \DomainException If the path is not a valid absolute local path mapped by the selected implementation.
	 */
	public function stream_url_from_path( string $path ): string {
		return $this->credentials->stream_url_from_path( $path );
	}

	/**
	 * Returns a stream context for the selected protocol.
	 *
	 * @since 1.0.0
	 *
	 * @return resource|null Stream context for the selected implementation.
	 *
	 * @throws Filesystem_Access_Exception If the selected connection fails.
	 */
	public function stream_context() {
		return $this->credentials->stream_context();
	}

	/**
	 * Renders the FTP and SFTP credentials form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $initial_state Values supplied in this array override the form defaults for these input names:
	 *                             - calm_credentials_protocol: ftp or sftp; absent selects sftp.
	 *                             - calm_ftp_host: FTP server hostname or IP address.
	 *                             - calm_ftp_port: FTP server port number.
	 *                             - calm_ftp_base: Server directory corresponding to the FTP account root.
	 *                             - calm_ftp_username: FTP account user name.
	 *                             - calm_ftp_password: FTP account password.
	 *                             - calm_sftp_host: SFTP server hostname or IP address.
	 *                             - calm_sftp_port: SFTP server port number.
	 *                             - calm_sftp_base: Server directory corresponding to the SFTP account root.
	 *                             - calm_sftp_username: SFTP account user name.
	 *                             - calm_sftp_auth_method: password, key_file, or key_text.
	 *                             - calm_sftp_password: SFTP account password; not redisplayed.
	 *                             - calm_sftp_private_key_text: Pasted private key; not redisplayed.
	 *                             - calm_sftp_passphrase: Private key passphrase; not redisplayed.
	 *                             Other keys are ignored. Pass [] for defaults and browser-stored prefilling.
	 * @param string|null $nonce_name Nonce action and prefix for its field name, or null to omit the nonce field.
	 * @param string $submit_label Text displayed on the submit button.
	 * @param string $action_url Form submission URL.
	 *
	 * @return string Form HTML.
	 *
	 * @throws \DomainException If a supplied initial value is invalid.
	 */
	public static function form( array $initial_state, ?string $nonce_name, string $submit_label, string $action_url ): string {
		$prefill = [] === $initial_state ? 'true' : 'false';
		$storage_site = esc_attr( \calmpress\utils\current_site_url() );

		$initial_state = wp_parse_args( $initial_state, [ self::PROTOCOL_FORM_NAME => 'sftp' ] );
		$protocol      = $initial_state[ self::PROTOCOL_FORM_NAME ];
		if ( ! is_string( $protocol ) ) {
			throw new \DomainException( 'Invalid credentials protocol.' );
		}
		if ( ! in_array( $protocol, [ 'ftp', 'sftp' ], true ) ) {
			throw new \DomainException( 'Invalid credentials protocol.' );
		}

		$id   = wp_unique_id( 'calm-credentials-' );
		$html = '<form data-credentials-type="ftp-sftp" data-credentials-site="' . $storage_site . '" data-credentials-prefill="' . $prefill . '" class="calm-credentials-form calm-validate" action="' . esc_url( $action_url ) . '" method="post" novalidate>';
		if ( null !== $nonce_name ) {
			$html .= wp_nonce_field( $nonce_name, $nonce_name . '_nonce', true, false );
		}

		$html .= '<div id="' . esc_attr( $id ) . '" class="calm-credentials-selector">';

		$html .= '<p class="description">' . esc_html__( 'Get the server address and FTP or SFTP account details from your hosting control panel or server administrator. Use an account that can write to this installation. These credentials are usually different from your calmPress login.' ) . '</p>';
		$html .= '<p><label for="' . esc_attr( $id . '-protocol' ) . '">' . esc_html__( 'Connection protocol' ) . '</label> ';
		$html .= '<select id="' . esc_attr( $id . '-protocol' ) . '" name="' . esc_attr( self::PROTOCOL_FORM_NAME ) . '">';
		$html .= '<option value="sftp"' . selected( $protocol, 'sftp', false ) . '>' . esc_html__( 'SFTP' ) . '</option>';
		$html .= '<option value="ftp"' . selected( $protocol, 'ftp', false ) . '>' . esc_html__( 'FTP' ) . '</option>';
		$html .= '</select></p>';

		foreach ( [ 'sftp', 'ftp' ] as $type ) {
			$active = $protocol === $type;
			$html .= '<div data-credentials-type="' . esc_attr( $type ) . '" data-credentials-site="' . $storage_site . '" data-credentials-prefill="' . $prefill . '" data-credentials-protocol="' . esc_attr( $type ) . '"' . ( $active ? '' : ' hidden' ) . '>';
			$html .= '<table class="form-table"><tbody>';
			$html .= 'sftp' === $type ? Filesystem_Access_Over_SFTP::form_fields( $initial_state ) : Filesystem_Access_Over_FTP::form_fields( $initial_state );
			$html .= '</tbody></table></div>';
		}
		$html .= '</div>';
		$html .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary button-large" value="' . esc_attr( $submit_label ) . '"></p>';
		$html .= '</form>';
		wp_enqueue_script( 'calm-ftp-sftp-credentials' );

		return $html;
	}

	/**
	 * Parse credentials for the protocol specified in the submitted fields.
	 *
	 * The calm_credentials_protocol field selects ftp or sftp.
	 *
	 * The remaining fields follow the selected protocol's parser.
	 *
	 * @since 1.0.0
	 *
	 * @see Filesystem_Access_Over_FTP::parse_parameters()
	 * @see Filesystem_Access_Over_SFTP::parse_parameters()
	 *
	 * @param array $vars  Unslashed form fields. calm_credentials_protocol must be ftp or sftp.
	 *                     All fields for the selected protocol must be present as strings;
	 *                     fields for the other protocol are ignored:
	 *                     - calm_ftp_host: FTP server hostname or IP address.
	 *                     - calm_ftp_port: Server port as a decimal string from 1 to 65535.
	 *                     - calm_ftp_username: FTP account username.
	 *                     - calm_ftp_password: FTP account password.
	 *                     - calm_ftp_base: Absolute server path corresponding to the account's root directory.
	 *                     - calm_sftp_host: SFTP server hostname or IP address.
	 *                     - calm_sftp_port: Server port as a decimal string from 1 to 65535.
	 *                     - calm_sftp_username: SFTP account username.
	 *                     - calm_sftp_base: Absolute server path corresponding to the account's root directory.
	 *                     - calm_sftp_auth_method: One of password, key_file, or key_text.
	 *                     - calm_sftp_password: Password for password authentication.
	 *                     - calm_sftp_private_key_text: Private key contents for either key authentication method.
	 *                     - calm_sftp_passphrase: Private key passphrase, if needed.
	 *                     SFTP fields for inactive authentication methods are ignored but must still be present.
	 *
	 * @return Connection_Credentials_Parameters|\WP_Error Values for the selected protocol or validation problems.
	 *
	 * @throws \LogicException If the submitted protocol is missing or unsupported.
	 */
	public static function parse_parameters( array $vars ): Connection_Credentials_Parameters|\WP_Error {
		if ( ! isset( $vars[ self::PROTOCOL_FORM_NAME ] ) || ! is_string( $vars[ self::PROTOCOL_FORM_NAME ] ) ) {
			throw new \LogicException( 'Missing or invalid connection protocol.' );
		}

		return match ( $vars[ self::PROTOCOL_FORM_NAME ] ) {
			'ftp' => Filesystem_Access_Over_FTP::parse_parameters( $vars ),
			'sftp' => Filesystem_Access_Over_SFTP::parse_parameters( $vars ),
			default => throw new \LogicException( 'Unsupported connection protocol.' ),
		};
	}

	/**
	 * Parse submitted credentials, verify remote installation access, and test write
	 * access to the supplied directory.
	 *
	 * @since 1.0.0
	 *
	 * @see self::parse_parameters() For the required protocol field and protocol-specific parameters.
	 *
	 * @param array $vars Unslashed form values accepted by parse_parameters().
	 * @param string $directory Absolute directory requiring write access.
	 *
	 * @return Remote_Filesystem_Access|\WP_Error Filesystem access on success, or validation and connection errors on failure.
	 *
	 * @throws \LogicException If required parameters are missing or malformed.
	 */
	public static function writable_directory_access( array $vars, string $directory ): Remote_Filesystem_Access|\WP_Error {
		if ( ! isset( $vars[ self::PROTOCOL_FORM_NAME ] ) || ! is_string( $vars[ self::PROTOCOL_FORM_NAME ] ) ) {
			throw new \LogicException( 'Missing or invalid connection protocol.' );
		}

		$protocol = $vars[ self::PROTOCOL_FORM_NAME ];

		return match ( $protocol ) {
			'ftp' => Filesystem_Access_Over_FTP::writable_directory_access( $vars, $directory ),
			'sftp' => Filesystem_Access_Over_SFTP::writable_directory_access( $vars, $directory ),
			default => throw new \LogicException( 'Unsupported connection protocol.' ),
		};
	}
}
