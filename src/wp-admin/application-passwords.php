<?php
/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

use function calmpress\utils\base64URL_encode;

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$current_user = wp_get_current_user();
$user_id      = $current_user->ID;

wp_enqueue_script( 'calm-application-passwords' );
wp_enqueue_style( 'calm-application-passwords' );


$title = __( 'My Application Passwords' );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap" id="application-passwords-page">
<h1 class="wp-heading-inline">
		<?php
		echo esc_html( $title );
		?>
</h1>

<hr class="wp-header-end">

<h2><?php esc_html_e( 'Connect apps and services to your account' ); ?></h2>
<p>
	<?php esc_html_e( 'Use these passwords to connect apps and services (such as mobile apps or automation tools) to your account without sharing your main password. You can revoke access at any time. They cannot be used to log in through the website.' ); ?>
</p>
<?php
	if ( is_multisite() ) {
		echo '<p>';
		esc_attr_e( 'These passwords can be used to access sites in this network according to your user permissions.' );
		echo '</p>';

	}
?>
<p><?php esc_html_e( 'Any changes you make here are saved automatically.' ); ?></p>

<?php
$password_description_text_translation = sprintf(
	/* translators: %s: place holder for Application name. */
	esc_html__( 'Your new password for %s is:' ),
	'<strong id="generated_password_description"></strong>'
);

$username_translation = sprintf(
	/* translators: %s: place holder for application name. */
	esc_html__( 'Username to use for %s is:' ),
	'<strong id="generated_login_description"></strong>'
);

$copy_translation   = esc_html__( 'Copy' );
$copied_translation = esc_html__( 'Copied' );
$save_notice_translation = esc_html__( 'Be sure to save the password in a safe location. You will not be able to retrieve it.' );
$username_copy_speak = esc_attr__( 'Application user name has been copied to your clipboard' );
$password_copy_speak = esc_attr__( 'Application password has been copied to your clipboard' );

$notice_html = <<<EOT
<p class="application-login-display">
	<label for="new-application-login-value">
		$username_translation
	</label>
	<input id="new-application-login-value" type="text" class="code" readonly="readonly" value="" />
	<button type="button" class="button copy-button" data-clipboard-text="" data-speak="$username_copy_speak">$copy_translation</button>
	<span class="success hidden" aria-hidden="true">$copied_translation</span>
</p>
<p class="application-password-display">
	<label for="new-application-password-value">
		$password_description_text_translation
	</label>
	<input id="new-application-password-value" type="text" class="code" readonly="readonly" value="" />
	<button type="button" class="button copy-button" data-clipboard-text="" data-speak="$password_copy_speak">$copy_translation</button>
	<span class="success hidden" aria-hidden="true">$copied_translation</span>
</p>
<p>$save_notice_translation</p>
<p>
EOT;

\calmpress\utils\html_for_dissmissable_admin_notice_with_html_content(
	'add_password_success_message',
	$notice_html,
	false
);

\calmpress\utils\html_for_dissmissable_admin_notice( 'add_password_error_message', true );

add_action( 
	'admin_footer',
	function () use ( $user_id ) {
		$can_add =( WP_Application_Passwords::error_if_can_not_add_to_user( $user_id ) === null ) ? 'true' : 'false';
		echo <<<JS
<script>
application_passwords_can_add = $can_add;
</script>
JS;
	},
	11
);
?>
<table id="application-passwords-section" class="application-passwords form-table" role="presentation">
	<tr>
		<th>
			<label for="new_application_password_name">
				<?php esc_html_e( 'New Password' );?>
			</label>
		</th>
		<td>
			<input class="regular-text" id="new_application_password_name" name="new_application_password_name" value="" autocomplete="off" />
			<p id="new_application_password_name_desc" class="description"><?php esc_html_e( 'Use this text to describe what this password is used for (for example, Mobile App or Automation Tool). It must be unique and not empty.' ); ?></p>
			<p>
				<button id="register_button" class="button" type="button" disabled="disabled">
					<?php esc_html_e( 'Add Password' )?>
				</button>
			</p>
			<p id="max_passwords_reached">
				<?php esc_html_e( 'You have reached the maximum number of application passwords. Revoke an existing one to be able to create a new password.' ); ?>
			</p>
		</td>
	</tr>
</table>
<?php \calmpress\utils\html_for_dissmissable_admin_notice( 'password_table_message', true );?>
<?php
	$passwords = WP_Application_Passwords::get_user_application_passwords( $user_id );
	$style     = count( $passwords ) === 0 ? ' style="display:none"' : '';
	$nonestyle = count( $passwords ) !== 0 ? ' style="display:none"' : '';
?>
<table id="application-passwords-table" class="form-table" role="presentation">
	<tr id="username_for_password" <?php echo $style;?> >
		<th>
			<?php esc_html_e( 'Username for passwords' ); ?>
		</th>
		<td>
			<?php echo esc_html( WP_Application_Passwords::get_user_application_login( $current_user->ID ) ); ?>
		</td>
	</tr>
	<tr>
		<th>
			<?php esc_html_e( 'Existing passwords' ); ?>
		</th>
		<td>
<?php
	echo '<div id="no_passwords_message"' . $nonestyle . '>' . esc_html__( 'None' ) . '</div>' . "\n";
	echo '<table id="passwords-grid" class="widefat striped"' . $style . '>' . "\n";
		echo '<thead><tr>' . "\n";
			echo '<th class="description">' . esc_html__( 'Description' ) . '</th>' . "\n";
			echo '<th class="created">' . esc_html__( 'Created' ) . '</th>' . "\n";
			echo '<th class="last-used">' . esc_html__( 'Last Used' ) . '</th>' . "\n";
			echo '<th class="last-ip">' . esc_html__( 'Last IP' ) . '</th>' . "\n";
			echo '<th class="actions">' . esc_html__( 'Actions' ) . '</th>' . "\n";
		echo '</tr></thead>' . "\n";
		echo '<tbody>';
		$row=0;
		$row_template =
			'<tr data-uuid="__UUID__">'
				. '<td class="description">'
					. '<span id="password_description_text__ROW__">'
						. '__DESC__'
					. '</span>'
					. '<div class="edit_form" id="password_change__ROW__">'
						. '<label for="password__ROW__">'
							. esc_html__( 'New Description' )
						. '</label>'
						. '<input id="password_description__ROW__" type="text">'
						. '<div>'
							. '<button class="button update_description" type="button">'
								. esc_html__( 'Update' )
							. '</button>'
							. '<button class="button close_change" type="button">'
								. esc_html__( 'Cancel' )
							. '</button>'
						. '</div>'
					. '</div>'
				. '</td>'
				. '<td class="created">'
					. '__CREATED__'
				. '</td>'
				. '<td class="last-used">'
					. '__LAST_USED__'
				. '</td>'
				. '<td class="last-ip">'
					. '__LAST_IP__'
				. '</td>'
				. '<td class="actions">'
					. '<button class="button edit" type="button" aria-expanded="false" aria-controls="password_change__ROW_____ROW__">'
						. esc_html__( 'Edit' )
					. '</button>'
					. '<button class="button revoke" type="button" aria-describedby="password_description_text__ROW__">'
						. esc_html__( 'Revoke' )
					. '</button>'
				. '</td>'
			. '</tr>';
		foreach ( $passwords as $password ) {
			$row++;
			$row_html = str_replace( '__ROW__', $row, $row_template );
			$row_html = str_replace( '__UUID__', $password['uuid'], $row_html );
			$row_html = str_replace( '__DESC__', esc_html( $password['name'] ), $row_html );

			if ( empty( $password['created'] ) ) {
				$created = '&mdash;';
			} else {
				$created = date_i18n( __( 'F j, Y' ), $password['created'] );
			}
			$row_html = str_replace( '__CREATED__', esc_html( $created ), $row_html );

			if ( empty( $password['last_used'] ) ) {
				$last_used = '&mdash;';
			} else {
				$last_used = date_i18n( __( 'F j, Y' ), $password['last_used'] );
			}
			$row_html = str_replace( '__LAST_USED__', esc_html( $last_used ), $row_html );

			if ( empty( $password['last_ip'] ) ) {
				$last_ip = '&mdash;';
			} else {
				$last_ip = $password['last_ip'];
			}
			$row_html = str_replace( '__LAST_IP__', esc_html( $last_ip ), $row_html );
			echo $row_html;
		}
	echo '</tbody>';
	echo '</table>' . "\n";
	echo '<template id="password_row_template">' . $row_template . '</template>';
?>
		</td>
	</tr>
</table>

</div>
<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';
