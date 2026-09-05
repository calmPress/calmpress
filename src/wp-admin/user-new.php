<?php
/**
 * New User Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( is_multisite() ) {
	if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'promote_users' ) ) {
		wp_die(
			'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to add users to this network.' ) . '</p>',
			403
		);
	}
} elseif ( ! current_user_can( 'create_users' ) ) {
	wp_die(
		'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
		'<p>' . __( 'Sorry, you are not allowed to create users.' ) . '</p>',
		403
	);
}

if ( is_multisite() ) {
	add_filter( 'wpmu_signup_user_notification_email', 'admin_created_user_email' );
}

if ( isset( $_REQUEST['action'] ) && 'adduser' === $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	$user_details = null;
	$user_email   = wp_unslash( $_REQUEST['email'] );

	if ( str_contains( $user_email, '@' ) ) {
		$user_details = get_user_by( 'email', $user_email );
	} else {
		wp_redirect( add_query_arg( array( 'update' => 'enter_email' ), 'user-new.php' ) );
		die();
	}

	if ( ! $user_details ) {
		wp_redirect( add_query_arg( array( 'update' => 'does_not_exist' ), 'user-new.php' ) );
		die();
	}

	if ( ! current_user_can( 'promote_user', $user_details->ID ) ) {
		wp_die(
			'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to add users to this network.' ) . '</p>',
			403
		);
	}

	// Adding an existing user to this blog.
	$new_user_email = array();
	$redirect       = 'user-new.php';
	$username       = $user_details->user_login;
	$user_id        = $user_details->ID;

	if ( array_key_exists( $blog_id, get_blogs_of_user( $user_id ) ) ) {
		$redirect = add_query_arg( array( 'update' => 'addexisting' ), 'user-new.php' );
	} else {
		$newuser_key = wp_generate_password( 20, false );
		add_option(
			'new_user_' . $newuser_key,
			array(
				'user_id' => $user_id,
				'email'   => $user_details->user_email,
				'role'    => $_REQUEST['role'],
			)
		);

		$roles = get_editable_roles();
		$role  = $roles[ $_REQUEST['role'] ];

		/**
		 * Fires immediately after an existing user is invited to join the site, but before the notification is sent.
		 *
		 * @since 4.4.0
		 *
		 * @param int    $user_id     The invited user's ID.
		 * @param array  $role        Array containing role information for the invited user.
		 * @param string $newuser_key The key of the invitation.
		 */
		do_action( 'invite_user', $user_id, $role, $newuser_key );

		$switched_locale = switch_to_user_locale( $user_id );

		if ( '' !== get_option( 'blogname' ) ) {
			$site_title = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		} else {
			$site_title = parse_url( home_url(), PHP_URL_HOST );
		}

		/* translators: 1: Site title, 2: Site URL, 3: User role, 4: Activation URL. */
		$message = __(
			'Hi,

You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.

Please click the following link to confirm the invite:
%4$s'
		);

		$new_user_email['to']      = $user_details->user_email;
		$new_user_email['subject'] = sprintf(
			/* translators: Joining confirmation notification email subject. %s: Site title. */
			__( '[%s] Joining Confirmation' ),
			$site_title
		);
		$new_user_email['message'] = sprintf(
			$message,
			get_option( 'blogname' ),
			home_url(),
			wp_specialchars_decode( translate_user_role( $role['name'] ) ),
			home_url( "/newbloguser/$newuser_key/" )
		);
		$new_user_email['headers'] = '';

		/**
		 * Filters the contents of the email sent when an existing user is invited to join the site.
		 *
		 * @since 5.6.0
		 *
		 * @param array $new_user_email {
		 *     Used to build wp_mail().
		 *
		 *     @type string $to      The email address of the invited user.
		 *     @type string $subject The subject of the email.
		 *     @type string $message The content of the email.
		 *     @type string $headers Headers.
		 * }
		 * @param int    $user_id     The invited user's ID.
		 * @param array  $role        Array containing role information for the invited user.
		 * @param string $newuser_key The key of the invitation.
		 *
		 */
		$new_user_email = apply_filters( 'invited_user_email', $new_user_email, $user_id, $role, $newuser_key );

		wp_mail(
			$new_user_email['to'],
			$new_user_email['subject'],
			$new_user_email['message'],
			$new_user_email['headers']
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$redirect = add_query_arg( array( 'update' => 'add' ), 'user-new.php' );
	}

	wp_redirect( $redirect );
	die();
} elseif ( isset( $_REQUEST['action'] ) && 'createuser' === $_REQUEST['action'] ) {
	check_admin_referer( 'create-user', '_wpnonce_create-user' );

	if ( ! current_user_can( 'create_users' ) ) {
		wp_die(
			'<h1>' . __( 'You need a higher level of permission.' ) . '</h1>' .
			'<p>' . __( 'Sorry, you are not allowed to create users.' ) . '</p>',
			403
		);
	}

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
		die();
	}
}

// Used in the HTML title tag.
$title       = __( 'Add User' );
$parent_file = 'users.php';

$do_both = false;
if ( is_multisite() && current_user_can( 'promote_users' ) && current_user_can( 'create_users' ) ) {
	$do_both = true;
}

wp_enqueue_script( 'wp-ajax-response' );

/**
 * Filters whether to enable user auto-complete for non-super admins in Multisite.
 *
 * @since 3.4.0
 *
 * @param bool $enable Whether to enable auto-complete for non-super admins. Default false.
 */
if ( is_multisite() && current_user_can( 'promote_users' ) && ! wp_is_large_network( 'users' )
	&& ( current_user_can( 'manage_network_users' ) || apply_filters( 'autocomplete_users_for_site_admins', false ) )
) {
	wp_enqueue_script( 'user-suggest' );
}

require_once ABSPATH . 'wp-admin/admin-header.php';

if ( isset( $_GET['update'] ) ) {
	$messages = array();
	if ( is_multisite() ) {
		$edit_link = '';
		if ( ( isset( $_GET['user_id'] ) ) ) {
			$user_id_new = absint( $_GET['user_id'] );
			if ( $user_id_new ) {
				$edit_link = esc_url( add_query_arg( 'wp_http_referer', urlencode( wp_unslash( $_SERVER['REQUEST_URI'] ) ), get_edit_user_link( $user_id_new ) ) );
			}
		}

		switch ( $_GET['update'] ) {
			case 'newuserconfirmation':
				$messages[] = __( 'Invitation email sent to new user. A confirmation link must be clicked before their account is created.' );
				break;
			case 'add':
				$messages[] = __( 'Invitation email sent to user. A confirmation link must be clicked for them to be added to your site.' );
				break;
			case 'addexisting':
				$messages[] = __( 'That user is already a member of this site.' );
				break;
			case 'could_not_add':
				$add_user_errors = new WP_Error( 'could_not_add', __( 'That user could not be added to this site.' ) );
				break;
			case 'created_could_not_add':
				$add_user_errors = new WP_Error( 'created_could_not_add', __( 'User has been created, but could not be added to this site.' ) );
				break;
			case 'does_not_exist':
				$add_user_errors = new WP_Error( 'does_not_exist', __( 'The requested user does not exist.' ) );
				break;
			case 'enter_email':
				$add_user_errors = new WP_Error( 'enter_email', __( 'Please enter a valid email address.' ) );
				break;
		}
	} else {
		if ( 'add' === $_GET['update'] ) {
			$messages[] = __( 'User added.' );
		}
	}
}
?>
<div class="wrap">
<h1 id="add-new-user">
<?php
if ( current_user_can( 'create_users' ) ) {
	_e( 'Add User' );
} elseif ( current_user_can( 'promote_users' ) ) {
	_e( 'Add Existing User' );
}
?>
</h1>

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
<div id="ajax-response"></div>

<?php
if ( is_multisite() && current_user_can( 'promote_users' ) ) {
	if ( $do_both ) {
		echo '<h2 id="add-existing-user">' . __( 'Add Existing User' ) . '</h2>';
	}
	if ( ! current_user_can( 'manage_network_users' ) ) {
		echo '<p>' . __( 'Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.' ) . '</p>';
		$label = __( 'Email' );
		$type  = 'email';
	} else {
		echo '<p>' . __( 'Enter the email address of an existing user on this network to invite them to this site. That person will be sent an email asking them to confirm the invite.' ) . '</p>';
		$label = __('Email');
		$type  = 'text';
	}
	?>
<form method="post" name="adduser" id="adduser" novalidate="novalidate"
	<?php
	/**
	 * Fires inside the adduser form tag.
	 *
	 * @since 3.0.0
	 */
	do_action( 'user_new_form_tag' );
	?>
>
<input name="action" type="hidden" value="adduser" />
	<?php wp_nonce_field( 'add-user', '_wpnonce_add-user' ); ?>

<table class="form-table" role="presentation">
	<tr class="form-field form-required">
		<th scope="row"><label for="adduser-email"><?php echo esc_html( $label ); ?></label></th>
		<td><input name="email" type="<?php echo esc_attr( $type ); ?>" id="adduser-email" class="wp-suggest-user" value="" /></td>
	</tr>
	<tr class="form-field">
		<th scope="row"><label for="adduser-role"><?php _e( 'Role' ); ?></label></th>
		<td><select name="role" id="adduser-role">
			<?php wp_dropdown_roles( get_option( 'default_role' ) ); ?>
			</select>
		</td>
	</tr>
</table>
	<?php
	/**
	 * Fires at the end of the new user form.
	 *
	 * Passes a contextual string to make both types of new user forms
	 * uniquely targetable. Contexts are 'add-existing-user' (Multisite),
	 * and 'add-new-user' (single site and network admin).
	 *
	 * @since 3.7.0
	 *
	 * @param string $type A contextual string specifying which type of new user form the hook follows.
	 */
	do_action( 'user_new_form', 'add-existing-user' );
	?>
	<?php submit_button( __( 'Add Existing User' ), 'primary', 'adduser', true, array( 'id' => 'addusersub' ) ); ?>
</form>
	<?php
} // End if is_multisite().

if ( current_user_can( 'create_users' ) ) {
	if ( $do_both ) {
		echo '<h2 id="create-new-user">' . __( 'Add User' ) . '</h2>';
	}
	?>
<p>
	<?php
	/* translators: %s: link to Login page URL, <br> can be used. */
	echo str_replace( '&lt;br&gt;', '<br>', sprintf(
		esc_html__( 'Create a new user account and add it to this site.<br>
After creating the account, notify the user to visit %s and request 
 a temporary password using the email address assigned to the account.<br>
Until they log in for the first time, the account will appear with the 
 "Pending Activation" role. After login, it will switch to the role you selected.' ),
		'<code style="user-select:all">' . esc_url( wp_login_url() ) . '</code>'
 	) );
	?>
</p>
<form method="post" name="createuser" id="createuser" novalidate="novalidate"
	<?php
	/** This action is documented in wp-admin/user-new.php */
	do_action( 'user_new_form_tag' );
	?>
>
<input name="action" type="hidden" value="createuser" />
<?php wp_nonce_field( 'create-user', '_wpnonce_create-user' ); ?>
<?php
// Load up the passed data, else set to a default.
$creating = isset( $_POST['createuser'] );

$new_user_login        = $creating && isset( $_POST['user_login'] ) ? wp_unslash( $_POST['user_login'] ) : '';
$new_user_email        = $creating && isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '';
$new_user_display_name = $creating && isset( $_POST['display_name'] ) ? wp_unslash( $_POST['display_name'] ) : '';
$new_user_role         = $creating && isset( $_POST['role'] ) ? wp_unslash( $_POST['role'] ) : '';

?>
<table class="form-table" role="presentation">
	<tr class="form-field form-required">
		<th scope="row"><label for="email"><?php _e('Email'); ?> <span class="description"><?php _e('(required)'); ?></span></label></th>
		<td>
			<input name="email" type="email" id="email" value="<?php echo esc_attr( $new_user_email ); ?>" />
			<p class="decription">
				<?php esc_html_e( 'The user\'s email address.' );?>
			</p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="display_name"><?php _e('Display Name'); ?></label></th>
		<td>
			<input name="display_name" type="test" id="display_name" value="<?php echo esc_attr( $new_user_display_name ); ?>" />
			<p class="decription">
				<?php esc_html_e( 'The user\'s initial display name, which can be changed later. If left empty,
 it will be automatically generated from the email address.' );?>
			</p>
		</td>
	</tr>
	<?php if ( current_user_can( 'promote_users' ) ) { ?>
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
			<p class="decription">
				<?php esc_html_e( 'The role that will be assigned to the user when they log in for the first time.' );?>
			</p>
		</td>
	</tr>
	<?php } ?>
</table>

	<?php
	/** This action is documented in wp-admin/user-new.php */
	do_action( 'user_new_form', 'add-new-user' );
	?>

	<?php submit_button( __( 'Add User' ), 'primary', 'createuser', true, array( 'id' => 'createusersub' ) ); ?>

</form>
<?php } // End if current_user_can( 'create_users' ). ?>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
