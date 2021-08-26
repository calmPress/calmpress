<?php
/**
 * wp-config.php Settings Administration Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\admin\wp_config;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! is_super_admin() ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage wp-config.php for this site.' ) );
}

$title       = __( 'wp-config.php Additional Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			/* translators: %s: placeholder for the file name. */
			'<p>' . sprintf( __( 'The %s file contains configuration settings for calmPress written as PHP code, most ly as defines.' ), '<code>wp-config.php</code>' ) . '</p>' .
			'<p>' . __( 'This screen allows you to add code including defines to it in a secure way.' ) . '</p>',
	)
);

add_settings_section(
	'calm-wp-config-user-section',
	__( 'User section' ),
	'',
	'wp-config'
);

add_settings_field(
	'calm-wp-config-user-section-code',
	__( 'Code' ),
	__NAMESPACE__ . '\code_input',
	'wp-config',
	'calm-wp-config-user-section',
	[ 'label_for' => 'calm-wp-config-user-section-code' ]
);

/**
 * Output the textarea in which the user code can be edited.
 *
 * @since 1.0.0
 */
function code_input() {
	?>
	<textarea class="large-text" name="wp_config_user_section" id="calm-wp-config-user-section-code" rows="6"><?php echo esc_textarea( get_option( 'wp_config_user_section' ) ); ?></textarea>
	<p class="description">
		<?php
		printf(
			/* translators: 1: wp-config.php, 2: wp-config.php section indicator */
			esc_html__( 'This code is going to be saved in the %1$s file in a section marked like %2$s' ),
			'<code>wp-config.php</code>',
			'<code>// BEGIN User ... // END User</code>'
		);
		?>
	</p>
	<p class="description">
		<?php
		printf(
			/* translators: 1: br, 2: common usage explenation, 3: concrete example */
			esc_html__( 'You can add and modify defines like %1$s %2$s %1$s for example to activate caching you can add %1$s %3$s  %1$s You can also add  "//" style comments after the defines or on separate line and empty lines' ),
			'<br />',
			"<code>define('setting_name', setting_value);</code>",
			"<code>define('WP_CACHE', true);</code>"
		);
		?>
	</p>
	<?php
}

/**
 * An handler for when saving the rules in to the wp-config.php file fails. Responsible
 * to give the user a proper notification.
 *
 * The raeson for the failure is extracted by using the error_get_last() PHP function.
 *
 * @since 1.0.0
 *
 * @param \Exception $exception The exception containing iformation about the cause.
 */
function save_fail_notice( \Exception $exception ) {
	// Add admin notice that will report the error to the user.
	$message = $exception->getMessage();
	add_action(
		'admin_notices',
		function () use ( $message ) {
			?>
			<div class="notice notice-error">
				<p>
				<?php
				printf(
					/* translators: 1: Name the wp-config.php file 2: The exception error message in blockquote */
					esc_html__( 'Failed writing to the %1$s file. The text of the system error message is: %2$s' ),
					'<code>wp-config.php</code>',
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

$wp_config       = \calmpress\wp_config\wp_config::current_wp_config();
$update_required = false;

/*
 * Get the errors identified by options.php when updating the option. When there are no errors
 * (array is empty) it indicates no save was done, probably the user navigated to the page from
 * somewhere else.
 * When there is one error with type of 'success' it indicates the option was saved.
 */
$error_messages = get_settings_errors();
if ( ( 1 === count( $error_messages ) ) && ( 'success' === $error_messages[0]['type'] ) ) {
	// If there was a successful save, check if the setting passes sanitization and if not
	// do not save it.
	$setting   = get_option( 'wp_config_user_section' );
	$sanitized = \calmpress\wp_config\wp_config::sanitize_user_setting( $setting );
	if ( $sanitized !== $setting ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class='notice notice-error is-dismissible'>
					<p>
					<?php
					printf(
						/* translators: %s: The wp-config.php file name */
						esc_html__( 'Some lines did not pass sanitization. Settings must pass sanitization for the %s file to be updated.' ),
						'<code>wp-config.php</code>'
					);
					?>
					</p>
				</div>
				<?php
			}
		);
	} else {
		$update_required = true;
	}
}

if ( empty( $errors ) ) {
	$existing_rules = $wp_config->user_section_in_file();
	$new_rules      = get_option( 'wp_config_user_section' );
	if ( $new_rules !== $existing_rules ) {
		$update_required = true;
	}
}

$writable = $wp_config->is_writable();
$saved    = false;
if ( $update_required ) {

	$creds = null;
	if ( $writable ) {
		$creds = new \calmpress\credentials\File_Credentials();
	} elseif ( isset( $_POST['calm_wp_config_ftp_nonce'] )
		&& wp_verify_nonce( wp_unslash( $_POST['calm_wp_config_ftp_nonce'] ), 'calm_wp_config_ftp' )
		) {
		$ftp_creds = \calmpress\credentials\FTP_Credentials::credentials_from_request_vars( $_POST );
		if ( is_array( $ftp_creds ) ) {
			// Validation had failed.
			validation_error_notice( $ftp_creds );
		} else {
			$creds = $ftp_creds;
		}
	} else {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class='notice notice-error is-dismissible'>
					<p>
					<?php
					printf(
						/* translators: 1: The wp-config.php file name, 2: Opening <a linking to FTP credentials form, 3: Closing </a */
						esc_html__( '%1$s is not writable. Please provide %2$sFTP credentials%3$s to perform the file update.' ),
						'<code>wp-config.php</code>',
						'<a href="#ftpcreds">',
						'</a>'
					);
					?>
					</p>
				</div>
				<?php
			}
		);
	}

	if ( null !== $creds ) {
		try {
			$wp_config->save_user_section_to_file( get_option( 'wp_config_user_section' ), $creds );
			$saved = true;
		} catch ( \Exception $exception ) {
			save_fail_notice( $exception );
		}
	}

	if ( $saved ) {
		add_action(
			'admin_notices',
			function () {
				?>
				<div class='updated notice is-dismissible'>
					<p>
					<?php
					printf(
						/* translators: 1: The wp-config.php file name */
						esc_html__( 'The %1$s file was updated.' ),
						'<code>wp-config.php</code>'
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
		settings_fields( 'wp-config' );
		do_settings_sections( 'wp-config' );
		submit_button();
		?>
	</form>
	<?php
	if ( ! $saved && ! $writable && $update_required ) {
		?>
		<p>
			<?php
			printf(
				/* translators: 1: wp-config.php */
				esc_html__( 'If your %1$s file was writable, we could update it, but it isn&#8217;t (which is a good thing!)' ),
				'<code>wp-config.php</code>'
			);
			?>
		</p>
		<p><?php esc_html_e( 'You should either supply FTP credentials which can be used to save the file for you, or update the file with the content below the FTP form.' ); ?></p>
		<form action="" method="post">
			<h2 id="ftpcreds"><?php esc_html_e( 'FTP credentials' ); ?></h2>
			<?php wp_nonce_field( 'calm_wp_config_ftp', 'calm_wp_config_ftp_nonce' ); ?>
			<table class="form-table">
				<?php
				echo \calmpress\credentials\FTP_Credentials::form( $_POST );
				?>
			</table>
			<?php submit_button( __( 'Update wp-config.php using FTP' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'wp-config.php content to save' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: 1: path to the wp-config.php file */
				esc_html__( 'The file that should be updated is %1$s' ),
				esc_html( $wp_config->filename() )
			);
			?>
		</p>
		<p><textarea rows="6" class="large-text readonly" onclick="this.focus();this.select()" name="code" id="code" readonly="readonly"><?php echo esc_textarea( $wp_config->expected_file_content( get_option( 'wp_config_user_section' ) ) ); ?></textarea></p>
		<?php
	}
	?>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
