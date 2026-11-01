<?php
/**
 * .htaccess Settings Administration Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\admin\htaccess;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! is_super_admin() ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage .htaccess for this site.' ) );
}

if ( ! is_apache() ) {
	wp_die( esc_html__( 'Sorry, you are not using an apache web server.' ) );
}

if ( is_multisite() ) {
	wp_die( 'The .htaccess editor is not available on a network installation.', '', [ 'response' => 403 ] );
}

$title       = __( '.htaccess Settings' );
$parent_file = 'options-general.php';

$home_path = ABSPATH;
$file_path = ABSPATH . '.htaccess';

$update_required = false;

if ( ( ! file_exists( $file_path ) && is_writable( $home_path ) ) || is_writable( $file_path ) ) {
	$writable = true;
} else {
	$writable = false;
}

$existing_rules  = array_filter( extract_from_markers( $file_path, 'WordPress' ) );
$new_rules       = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );
$update_required = ( $new_rules !== $existing_rules );

add_settings_section(
	'calm-htaccess-user-section',
	__( 'User section' ),
	'',
	'htaccess'
);

add_settings_field(
	'calm-htaccess-user-section-rules',
	__( 'Rules' ),
	__NAMESPACE__ . '\rules_input',
	'htaccess',
	'calm-htaccess-user-section',
	[ 'label_for' => 'calm-htaccess-user-section-rules' ]
);

/**
 * Output the textarea in which the user rules can be edited.
 *
 * @since 1.0.0
 */
function rules_input() {
	?>
	<textarea class="large-text" name="htaccess_user_section" id="calm-htaccess-user-section-rules" rows="6"><?php echo esc_textarea( get_option( 'htaccess_user_section' ) ); ?></textarea>
	<p class="description">
		<?php
		printf(
			/* translators: 1: .htaccess, 2: .htaccess section indicator */
			esc_html__( 'These rules are going to be saved in the %1$s file in a section marked like %2$s' ),
			'<code>.htaccess</code>',
			'<code># BEGIN User ... # END User</code>'
		);
		?>
	</p>
	<?php
}

/**
 * An handler for when saving the rules in to the .htaccess file fails. Responsible
 * to give the user a proper notification.
 *
 * @since 1.0.0
 *
 * @param string|null $message Failure reason, or null to use the last PHP error.
 */
function save_fail_notice( ?string $message = null ) {
	$message ??= \calmpress\utils\last_error_message();
	if ( '' === trim( $message ) ) {
		$message = __( 'The server did not report a reason for the failure.' );
	}

	// Add admin notice that will report the error to the user.
	add_action(
		'admin_notices',
		static function () use ( $message ) {
			?>
			<div class="notice notice-error">
				<p>
				<?php
				printf(
				/* translators: 1: Name of the .htaccess file, 2: Failure reason. */
					esc_html__( 'Failed updating the %1$s file. %2$s' ),
					'<code>.htaccess</code>',
					'<br><blockquote>' . esc_html( $message ) . '</blockquote>'
				);
				?>
				</p>
			</div>
			<?php
		},
		9,
		1
	);
}

/**
 * Reports validation problems with connection credentials.
 *
 * @since 1.0.0
 *
 * @param \WP_Error $errors Credential validation errors.
 */
function validation_error_notice( \WP_Error $errors ) {
	// Add admin notice that will report the error to the user.
	add_action(
		'admin_notices',
		function () use ( $errors ) {
			?>
			<div class="notice notice-error">
				<p>
				<?php
				esc_html_e( 'The connection credentials have the following problems:' );
				foreach ( $errors->get_error_messages() as $error ) {
					echo '<br />' . esc_html( $error );
				}
				?>
				</p>
			</div>
			<?php
		},
		9,
		1
	);
}

if ( $update_required ) {

	$saved = false;
	if ( $writable ) {
		$saved = save_mod_rewrite_rules();
		if ( ! $saved ) {
			save_fail_notice();
		}
	} elseif ( isset( $_POST['calm_htacess_ftp_nonce'] )
		&& wp_verify_nonce( wp_unslash( $_POST['calm_htacess_ftp_nonce'] ), 'calm_htacess_ftp' )
		) {
		$connection_failure = __( 'The file could not be updated through this connection. Check the connection settings and file permissions.' );
		$parsed = null;
		try {
			$parsed = \calmpress\credentials\FTP_SFTP_Credentials::parse_parameters( $_POST, $_FILES );
			if ( is_wp_error( $parsed ) ) {
				validation_error_notice( $parsed );
			} else {
				$connection_credentials = $parsed instanceof \calmpress\credentials\FTP_Credentials_Parameters
					? new \calmpress\credentials\FTP_Credentials( $parsed )
					: new \calmpress\credentials\SFTP_Credentials( $parsed );
				$access_errors = \calmpress\utils\validate_connection_directories( $connection_credentials, [ dirname( $file_path ) ] );
				if ( $access_errors->has_errors() ) {
					validation_error_notice( $access_errors );
				} else {
					$url   = $connection_credentials->stream_url_from_path( $file_path );
					$lock  = new \calmpress\filesystem\Path_Lock( $file_path );
					$saved = insert_with_markers( $url, 'WordPress', explode( "\n", $wp_rewrite->mod_rewrite_rules() ), '#', $connection_credentials->stream_context() );
				}
			}
		} catch ( \DomainException $error ) {
			if ( $parsed instanceof \calmpress\credentials\SFTP_Credentials_Parameters ) {
				validation_error_notice( new \WP_Error( 'sftp_authentication_failed', __( 'Could not sign in to the SFTP server. Check the username and password or private key.' ) ) );
			} else {
				validation_error_notice( new \WP_Error( 'invalid_credentials', $error->getMessage() ) );
			}
		} catch ( \calmpress\filesystem\Marker_File_Exception $error ) {
			save_fail_notice( $error->to_wp_error()->get_error_message() );
		} catch ( \Throwable ) {
			save_fail_notice( $connection_failure );
		}
	}

	if ( $saved ) {
		add_action(
			'admin_notices',
			static function () {
				?>
				<div class='updated notice is-dismissible'>
					<p>
					<?php
					printf(
						/* translators: 1: The file name */
						esc_html__( 'The %1$s file was updated.' ),
						'<code>.htaccess</code>'
					);
					?>
					</p>
				</div>
				<?php
			}
		);
	}
}

require ABSPATH . 'wp-admin/admin-header.php';

?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<form method="post" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'htaccess' );
		do_settings_sections( 'htaccess' );
		submit_button();
		?>
	</form>
	<?php
	if ( $update_required && ! $saved  ) {
		?>
		<p>
			<?php
			printf(
				/* translators: 1: .htaccess */
				esc_html__( 'If your %1$s file was writable, we could update it, but it isn&#8217;t (which is a good thing!)' ),
				'<code>.htaccess</code>'
			);
			?>
		</p>
		<p><?php esc_html_e( 'Supply FTP or SFTP credentials to save the file, or update it with the content below.' ); ?></p>
		<?php
		try {
			echo \calmpress\credentials\FTP_SFTP_Credentials::form( wp_unslash( $_POST ), 'calm_htacess_ftp', __( 'Update .htaccess' ) );
		} catch ( \DomainException $error ) {
			echo \calmpress\credentials\FTP_SFTP_Credentials::form( [], 'calm_htacess_ftp', __( 'Update .htaccess' ) );
		}
		?>
		<h2><?php esc_html_e( '.htaccess content to save' ); ?></h2>
		<p><textarea rows="6" class="large-text readonly" onclick="this.focus();this.select()" name="rules" id="rules" readonly="readonly"><?php echo esc_textarea( "# BEGIN WordPress\n" . $wp_rewrite->mod_rewrite_rules() . "# END WordPress\n" ); ?></textarea></p>
		<?php
	}
	?>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
