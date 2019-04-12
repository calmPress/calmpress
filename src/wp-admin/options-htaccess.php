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

if ( ! got_mod_rewrite() ) {
	wp_die( esc_html__( 'Sorry, you are not using an apache web server.' ) );
}

if ( is_multisite() ) {
	wp_die( esc_html__( 'Sorry, but .htaccess for multisite is managed from the network admin.' ) );
}

$title       = __( '.htaccess Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			/* translators: %s: placeholder for the file name. */
			'<p>' . sprintf( __( 'The %s file contains configuration instructions for the Apache web server that control how it handles URLs in the local context.' ), '<code>.htaccess</code>' ) . '</p>' .
			/* translators: %s: placeholder for the file name. */
			'<p>' . sprintf( __( 'This screen allows you to add rules to the %s file without having to edit the full file with an FTP software.' ), '<code>.htaccess</code>' ) . '</p>',
	)
);

$home_path = get_home_path();

$update_required = false;

if ( ( ! file_exists( $home_path . '.htaccess' ) && is_writable( $home_path ) ) || is_writable( $home_path . '.htaccess' ) ) {
	$writable = true;
} else {
	$writable = false;
}

$existing_rules  = array_filter( extract_from_markers( $home_path . '.htaccess', 'WordPress' ) );
$new_rules       = array_filter( explode( "\n", $wp_rewrite->mod_rewrite_rules() ) );
$update_required = ( $new_rules !== $existing_rules );

$ftp_creds = null;

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
		/* translators: 1: .htaccess, 2: .htaccess section indicator */
		printf( esc_html__( 'These rules are going to be saved in the %1$s file in a section marked like %2$s' ),
			'<code>.htaccess</code>',
			'<code># BEGIN User ... # END User</code>'
		);
		?>
	</p>
	<?php
}

/**
 * An handler for when saving the rules intothe .htaccess file fails. Responsible
 * to give the user a proper notification.
 *
 * @param \calmpress\filesystem\Locked_File_Exception $e The exception reported
 *                                                       by insert_with_markers.
 */
function save_fail_handler( \calmpress\filesystem\Locked_File_Exception $e ) {
	// Add admin notice that will report the error to the user.
	add_action( 'admin_notices', function () use ( $e ) {
		?>
		<div class="notice notice-error">
			<p>
			<?php
			/* translators: 1: Name the .htaccess file 2: The exception error message in blockquote */
			printf( esc_html__( 'Failed writing to the %1$s file. The text of the system error message is: %2$s' ),
				'<code>.htaccess</code>',
				'<br><code>' . esc_html( $e->message() ) . '</code>'
			);

			// If FTP credentials were used, point the user to verify them.
			$ftp_creds = \calmpress\credentials\FTP_Credentials::credentials_from_request_vars( $_POST );
			if ( null !== $ftp_creds ) {
				$error = $ftp_creds->human_readable_state();
				if ( '' !== $error ) {
					echo '<br>' . esc_html__( 'Additional information: ' ) . $error;
				}
				echo '<br>' . esc_html__( 'Please make sure the FTP credentials you used are correct' );
			}
			?>
			</p>
		</div>
		<?php
	}, 9, 1 );
}

if ( $update_required ) {

	// Try to save the .htaccess file with the rules as system assume they should be.
	// Notify the user if the save had failed.
	add_action( 'calm_insert_with_markers_exception', __NAMESPACE__ . '\save_fail_handler', 10, 1 );

	$saved = false;
	if ( $writable ) {
		$saved = save_mod_rewrite_rules();
	} elseif ( isset( $_POST['calm_htacess_ftp_nonce'] )
		&& wp_verify_nonce( wp_unslash( $_POST['calm_htacess_ftp_nonce'] ), 'calm_htacess_ftp' )
		) {
		$ftp_creds = \calmpress\credentials\FTP_Credentials::credentials_from_request_vars( $_POST );
		if ( null !== $ftp_creds ) {
			add_filter( 'calm_insert_with_markers_locked_file_getter', function () use ( $ftp_creds ) {
				return function ( $filename ) use ( $ftp_creds ) {
					return new \calmpress\filesystem\Locked_File_FTP_Write_Access(
						$filename,
						$ftp_creds
					);
				};
			} );
			$saved = save_mod_rewrite_rules();
		}
	}

	if ( $saved ) {
		add_action( 'admin_notices', function () {
			?>
			<div class='updated notice is-dismissible'>
				<p>
				<?php
				/* translators: 1: The file name */
				printf( esc_html( 'The %1$s file was updated.' ),
					'<code>.htaccess</code>'
				);
				?>
				</p>
			</div>
			<?php
		});
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
	if ( ! $writable && $update_required ) {
		?>
		<p>
			<?php
			printf(
				/* translators: 1: .htaccess */
				esc_html( 'If your %1$s file was writable, we could update it, but it isn&#8217;t (which is a good thing!)' ),
				'<code>.htaccess</code>'
			);
			?>
		</p>
		<p><?php esc_html_e( 'You should either supply FTP credentials which can be used to save the file for you, or update the file with the content below the FTP form.' ); ?></p>
		<form action="" method="post">
			<h2><?php esc_html_e( 'FTP credentials' ); ?></h2>
			<?php wp_nonce_field( 'calm_htacess_ftp', 'calm_htacess_ftp_nonce' ); ?>
			<table class="form-table">
				<?php
				if ( null === $ftp_creds ) {
					$ftp_creds = new \calmpress\credentials\FTP_Credentials( 'localhost', 21, '', '', '/' );
				}
				echo $ftp_creds->form();
				?>
			</table>
			<?php submit_button( __( 'Update .htaccess using FTP' ) ); ?>
		</form>
		<h2><?php esc_html_e( '.htaccess content to save' ); ?></h2>
		<p><textarea rows="6" class="large-text readonly" onclick="this.focus();this.select()" name="rules" id="rules" readonly="readonly"><?php echo esc_textarea( "# BEGIN WordPress\n" . $wp_rewrite->mod_rewrite_rules() . "# END WordPress\n" ); ?></textarea></p>
		<?php
	}
	?>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
