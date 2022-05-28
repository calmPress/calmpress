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
 * The raeson for the failure is extracted by using the error_get_last() PHP function.
 *
 * @since 1.0.0
 */
function save_fail_notice() {
	// Add admin notice that will report the error to the user.
	add_action(
		'admin_notices',
		static function () {
			?>
			<div class="notice notice-error">
				<p>
				<?php
				printf(
					/* translators: 1: Name the .htaccess file 2: The exception error message in blockquote */
					esc_html__( 'Failed writing to the %1$s file. The text of the system error message is: %2$s' ),
					'<code>.htaccess</code>',
					'<br><blockquote>' . esc_html( \calmpress\utils\last_error_message() ) . '</blockquote>'
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
 * An handler for when parsing the FTP credentials fails due to validation.
 *
 * @param string[] $errors Array of validation error strings.
 *
 * @since 1.0.0
 */
function validation_error_notice( array $errors ) {
	// Add admin notice that will report the error to the user.
	add_action(
		'admin_notices',
		function () use ( $errors ) {
			?>
			<div class="notice notice-error">
				<p>
				<?php
				esc_html_e( 'The FTP credentials has the following problems:' );
				foreach ( $errors as $error ) {
					echo '<br />' . $error;
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
		$ftp_creds = \calmpress\credentials\FTP_Credentials::credentials_from_request_vars( $_POST );
		if ( is_array( $ftp_creds ) ) {
			// Validation had failed.
			validation_error_notice( $ftp_creds );
		} else {
			$url   = $ftp_creds->stream_url_from_path( $file_path );
			$saved = save_mod_rewrite_rules( $url );
			if ( ! $saved ) {
				save_fail_notice();
			}
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
		<p><?php esc_html_e( 'You should either supply FTP credentials which can be used to save the file for you, or update the file with the content below the FTP form.' ); ?></p>
		<form action="" method="post">
			<h2><?php esc_html_e( 'FTP credentials' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: .htaccess */
					esc_html__( 'The FTP credentials required to access the server over FTP in order to modify the %s file' ),
					'<code>.htaccess</code>'
				);
				?>
			</p>
			<?php wp_nonce_field( 'calm_htacess_ftp', 'calm_htacess_ftp_nonce' ); ?>
			<table class="form-table">
				<?php
				echo \calmpress\credentials\FTP_Credentials::form( $_POST );
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
