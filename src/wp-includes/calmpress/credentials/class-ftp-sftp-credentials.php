<?php
/**
 * Selection between FTP and SFTP credentials.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * FTP or SFTP credentials selected by the user.
 *
 * @since 1.0.0
 */
class FTP_SFTP_Credentials implements Connection_Credentials {

	/**
	 * The selected connection credentials.
	 *
	 * @since 1.0.0
	 */
	private readonly Credentials $credentials;

	/**
	 * Name of the protocol field in the form.
	 *
	 * @since 1.0.0
	 */
	public const PROTOCOL_FORM_NAME = 'calm_credentials_protocol';

	/**
	 * Creates credentials for the selected protocol.
	 *
	 * @since 1.0.0
	 *
	 * @param Credentials $credentials Selected credentials.
	 */
	public function __construct( Credentials $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Converts a path to a URL for the selected protocol.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute file path.
	 *
	 * @return string Stream URL.
	 */
	public function stream_url_from_path( string $path ): string {
		return $this->credentials->stream_url_from_path( $path );
	}

	/**
	 * Returns a stream context for the selected protocol.
	 *
	 * @since 1.0.0
	 *
	 * @return resource Stream context.
	 *
	 * @throws Connection_Exception If the selected connection fails.
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
	 *                             Other keys are ignored. Pass [] to use all defaults.
	 * @param string $nonce_name   Nonce action and prefix for its field name.
	 * @param string $submit_label Text displayed on the submit button.
	 *
	 * @return string Form HTML.
	 *
	 * @throws \DomainException If a supplied initial value is invalid.
	 */
	public static function form( array $initial_state, string $nonce_name, string $submit_label ): string {
		$initial_state = wp_parse_args( $initial_state, [ self::PROTOCOL_FORM_NAME => 'sftp' ] );
		$protocol      = $initial_state[ self::PROTOCOL_FORM_NAME ];
		if ( ! is_string( $protocol ) ) {
			throw new \DomainException( 'Invalid credentials protocol.' );
		}
		if ( ! in_array( $protocol, [ 'ftp', 'sftp' ], true ) ) {
			throw new \DomainException( 'Invalid credentials protocol.' );
		}

		$id   = wp_unique_id( 'calm-credentials-' );
		$html = '<form class="calm-credentials-form calm-validate" action="" method="post" enctype="multipart/form-data" novalidate>';
		$html .= wp_nonce_field( $nonce_name, $nonce_name . '_nonce', true, false );
		$html .= '<div id="' . esc_attr( $id ) . '" class="calm-credentials-selector">';
		$html .= '<h2 id="connection-credentials">' . esc_html__( 'Connection credentials' ) . '</h2>';
		$html .= '<p class="description">' . esc_html__( 'Get the server address and FTP or SFTP account details from your hosting control panel or server administrator. Use an account that can write to this installation. These credentials are usually different from your calmPress login.' ) . '</p>';
		$html .= '<p><label for="' . esc_attr( $id . '-protocol' ) . '">' . esc_html__( 'Connection protocol' ) . '</label> ';
		$html .= '<select id="' . esc_attr( $id . '-protocol' ) . '" name="' . esc_attr( self::PROTOCOL_FORM_NAME ) . '">';
		$html .= '<option value="sftp"' . selected( $protocol, 'sftp', false ) . '>' . esc_html__( 'SFTP' ) . '</option>';
		$html .= '<option value="ftp"' . selected( $protocol, 'ftp', false ) . '>' . esc_html__( 'FTP' ) . '</option>';
		$html .= '</select></p>';

		foreach ( [ 'sftp', 'ftp' ] as $type ) {
			$active = $protocol === $type;
			$html .= '<div data-credentials-protocol="' . esc_attr( $type ) . '"' . ( $active ? '' : ' hidden' ) . '>';
			$html .= '<table class="form-table"><tbody>';
			$html .= 'sftp' === $type ? SFTP_Credentials::form_fields( $initial_state ) : FTP_Credentials::form_fields( $initial_state );
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
	 * @since 1.0.0
	 *
	 * @param array $vars  Slashed values from the submitted form.
	 * @param array $files Uploaded files keyed by form input name, in the format of $_FILES.
	 *
	 * @return Connection_Credentials_Parameters|\WP_Error Values for the selected protocol or validation problems.
	 *
	 * @throws \LogicException If the submitted protocol is missing or unsupported.
	 */
	public static function parse_parameters( array $vars, array $files ): Connection_Credentials_Parameters|\WP_Error {
		if ( ! isset( $vars[ self::PROTOCOL_FORM_NAME ] ) || ! is_string( $vars[ self::PROTOCOL_FORM_NAME ] ) ) {
			throw new \LogicException( 'Missing or invalid connection protocol.' );
		}

		return match ( wp_unslash( $vars[ self::PROTOCOL_FORM_NAME ] ) ) {
			'ftp' => FTP_Credentials::parse_parameters( $vars, $files ),
			'sftp' => SFTP_Credentials::parse_parameters( $vars, $files ),
			default => throw new \LogicException( 'Unsupported connection protocol.' ),
		};
	}
}
