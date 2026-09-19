<?php
/**
 * New User Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'promote_users' ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to add users.' ) . '</p>',
		'',
		[ 'response' => 403 ]
	);
}

if ( isset( $_REQUEST['action'] ) && 'adduser' === $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	if ( ! isset( $_POST['email'] ) || ! is_string( $_POST['email'] ) ) {
		wp_die( 'The add-user form did not submit an email address.', '', [ 'response' => 400 ] );
	}
	if ( ! isset( $_POST['display_name'] ) || ! is_string( $_POST['display_name'] ) ) {
		wp_die( 'The add-user form did not submit a display name.', '', [ 'response' => 400 ] );
	}
	if ( ! isset( $_POST['locale'] ) || ! is_string( $_POST['locale'] ) ) {
		wp_die( 'The add-user form did not submit a preferred language.', '', [ 'response' => 400 ] );
	}
	if ( ! isset( $_POST['role'] ) || ! is_string( $_POST['role'] ) ) {
		wp_die( 'The add-user form did not submit a role.', '', [ 'response' => 400 ] );
	}
	if ( 'site-default' !== $_POST['locale'] && 'en_US' !== $_POST['locale'] && ! in_array( $_POST['locale'], get_available_languages(), true ) ) {
		wp_die( 'The add-user form submitted an invalid preferred language.', '', [ 'response' => 400 ] );
	}

	$roles = get_editable_roles();
	if ( ! isset( $roles[ $_POST['role'] ] ) ) {
		wp_die( 'The add-user form submitted an invalid role.', '', [ 'response' => 400 ] );
	}

	try {
		$email_address = new calmpress\email\Email_Address( wp_unslash( $_POST['email'] ) );
	} catch ( InvalidArgumentException ) {
		wp_redirect( add_query_arg( array( 'update' => 'enter_email' ), 'user-new.php' ) );
		exit;
	}
	$_POST['email'] = $email_address->address;

	// Network sites may invite existing network users or create pending accounts.
	// Standalone site invitations always create pending accounts.
	if ( is_multisite() ) {
		$user_details = get_user_by( 'email', $email_address->address );

		// A current site member or a user already invited to the site cannot be invited again.
		if ( $user_details ) {
			$is_site_member        = is_user_member_of_blog( $user_details->ID, get_current_blog_id() );
			$has_pending_invitation = $user_details->is_pending_activation_on_site( calmpress\site\Site::current() );
			if ( $is_site_member || $has_pending_invitation ) {
				wp_redirect( add_query_arg( array( 'update' => 'cannot_invite' ), 'user-new.php' ) );
				exit;
			}
		}

		// An email address that does not belong to an existing network user requires
		// a pending account and activation instructions.
		// An existing network user receives only an invitation to join this site.
		if ( ! $user_details ) {
			// edit_user() creates the pending account, records its network and site
			// invitations, and sends the activation email after that state is complete.
			$pass           = wp_generate_password( 24 );
			$_POST['pass1'] = $pass;
			$_POST['pass2'] = $pass;
			$user_id        = edit_user();

			if ( is_wp_error( $user_id ) ) {
				$add_user_errors = $user_id;
			} else {
				wp_redirect( add_query_arg( array( 'update' => 'add' ), 'user-new.php' ) );
				exit;
			}
		} else {
			// Existing network accounts receive an invitation to join only this site.
			$site = calmpress\site\Site::current();
			$user_details->invite_to_network_site( $site, $_POST['role'] );

			$redirect = add_query_arg( array( 'update' => 'add' ), 'user-new.php' );

			wp_redirect( $redirect );
			exit;
		}
	} else {

		// edit_user expects those password fields in $_POST.
		$pass           = wp_generate_password( 24 );
		$_POST['pass1'] = $pass;
		$_POST['pass2'] = $pass;

		$user_id = edit_user();

		if ( is_wp_error( $user_id ) ) {
			$add_user_errors = $user_id;
		} else {
			if ( current_user_can( 'list_users' ) ) {
				$redirect = 'users.php?update=add&id=' . $user_id;
			} else {
				$redirect = add_query_arg( 'update', 'add', 'user-new.php' );
			}

			wp_redirect( $redirect );
			exit;
		}
	}
}

// Used in the HTML title tag.
$title       = __( 'Add User' );
$parent_file = 'users.php';

require_once ABSPATH . 'wp-admin/admin-header.php';

if ( isset( $_GET['update'] ) ) {
	$messages = array();
	switch ( $_GET['update'] ) {
		case 'add':
			$messages[] = __( 'The user is pending activation. Activation instructions were sent by email.' );
			break;
		case 'cannot_invite':
			$add_user_errors = new WP_Error( 'cannot_invite', __( 'The user is already a member of this site or has a pending invitation.' ) );
			break;
		case 'enter_email':
			$add_user_errors = new WP_Error( 'enter_email', __( 'The email address provided is not valid. Please enter a valid email address.' ) );
			break;
	}
}
?>
<div class="wrap">
<h1 id="add-new-user"><?php esc_html_e( 'Add User' ); ?></h1>

<?php
if ( isset( $errors ) && is_wp_error( $errors ) ) :
	$error_message = '';
	foreach ( $errors->get_error_messages() as $err ) {
		$error_message .= "<li>$err</li>\n";
	}
	wp_admin_notice(
		'<ul>' . $error_message . '</ul>',
		array(
			'additional_classes' => array( 'error' ),
			'paragraph_wrap'     => false,
		)
	);
endif;

if ( ! empty( $messages ) ) {
	foreach ( $messages as $msg ) {
		wp_admin_notice(
			$msg,
			array(
				'id'                 => 'message',
				'additional_classes' => array( 'updated' ),
				'dismissible'        => true,
			)
		);
	}
}
?>

<?php
if ( isset( $add_user_errors ) && is_wp_error( $add_user_errors ) ) :
	$error_message = '';
	foreach ( $add_user_errors->get_error_messages() as $message ) {
		$error_message .= "<p>$message</p>\n";
	}
	wp_admin_notice(
		$error_message,
		array(
			'additional_classes' => array( 'error' ),
			'paragraph_wrap'     => false,
		)
	);
endif;
?>
<p>
	<?php esc_html_e( 'Invite a user to join this site. They will receive an email with instructions for completing the invitation. If the invitation is accepted, they will receive the role selected below.' ); ?>
</p>
<form method="post" name="adduser" id="adduser" novalidate="novalidate"
	<?php
	/** This action is documented in wp-admin/user-new.php */
	do_action( 'user_new_form_tag' );
	?>
>
<input name="action" type="hidden" value="adduser" />
<?php wp_nonce_field( 'add-user', '_wpnonce_add-user' ); ?>
<?php
// Load up the passed data, else set to a default.
$creating = isset( $_POST['adduser'] );

$new_user_email        = $creating && isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
$new_user_display_name = $creating && isset( $_POST['display_name'] ) ? wp_unslash( $_POST['display_name'] ) : '';
$new_user_role         = $creating && isset( $_POST['role'] ) ? wp_unslash( $_POST['role'] ) : '';
$new_user_locale       = $creating && isset( $_POST['locale'] ) ? wp_unslash( $_POST['locale'] ) : 'site-default';

?>
<table class="form-table" role="presentation">
	<tr class="form-field form-required">
		<th scope="row"><label for="email"><?php _e('Email'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
		<td>
			<input name="email" type="email" id="email" value="<?php echo esc_attr( $new_user_email ); ?>" />
			<p class="description">
				<?php esc_html_e( 'The user\'s email address.' );?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="display_name"><?php _e('Display Name'); ?></label></th>
		<td>
			<input name="display_name" type="text" id="display_name" value="<?php echo esc_attr( $new_user_display_name ); ?>" />
			<p class="description">
				<?php esc_html_e( 'The user\'s initial display name, which can be changed later. If left empty, it will be generated from the email address.' );?>
			</p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="role"><?php _e( 'Role' ); ?></label></th>
		<td><select name="role" id="role">
			<?php
			if ( ! $new_user_role ) {
				$new_user_role = get_option( 'default_role' );
			}
			wp_dropdown_roles( $new_user_role );
			?>
			</select>
			<p class="description">
				<?php esc_html_e( 'The role that will be assigned to the user when they log in for the first time.' );?>
			</p>
		</td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="locale"><?php esc_html_e( 'Language' ); ?><span class="dashicons dashicons-translation" aria-hidden="true"></span></label></th>
		<td>
			<?php
			wp_dropdown_languages(
				[
					'name'                        => 'locale',
					'id'                          => 'locale',
					'selected'                    => $new_user_locale,
					'languages'                   => get_available_languages(),
					'show_available_translations' => false,
					'show_option_site_default'    => true,
					'explicit_option_en_us'       => true,
				]
			);
			?>
			<p class="description"><?php esc_html_e( 'The language used for the invitation email and in the administration interface after activation. The user can change it later in their profile.' ); ?></p>
		</td>
	</tr>
</table>

	<?php
	/** This action is documented in wp-admin/user-new.php */
	do_action( 'user_new_form', 'add-new-user' );
	?>

	<?php submit_button( __( 'Add User' ), 'primary', 'adduser', true, array( 'id' => 'addusersub' ) ); ?>

</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
