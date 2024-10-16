<?php
/**
 * WordPress user administration API.
 *
 * @package WordPress
 * @subpackage Administration
 */

use calmpress\email\Email_Address;

/**
 * Creates a new user from the "Users" form using $_POST information.
 *
 * @since 2.0.0
 *
 * @return int|WP_Error WP_Error or User ID.
 */
function add_user() {
	return edit_user();
}

/**
 * Edit user settings based on contents of $_POST
 *
 * Used on user-edit.php and profile.php to manage and process user options, passwords etc.
 *
 * @since 2.0.0
 *
 * @param int $user_id Optional. User ID.
 * @return int|WP_Error User ID of the updated user or WP_Error on failure.
 */
function edit_user( $user_id = 0 ) {
	$wp_roles = wp_roles();
	$user     = new stdClass();
	$user_id  = (int) $user_id;
	if ( $user_id ) {
		$update           = true;
		$user->ID         = $user_id;
		$userdata         = get_userdata( $user_id );
		$user->user_login = wp_slash( $userdata->user_login );
	} else {
		$update = false;
	}

	if ( ! $update ) {
		if ( isset( $_POST['user_login'] ) ) {
			$user->user_login = sanitize_user( $_POST['user_login'], true );
		} else {
			$user->user_login = md5( $_POST['email'] );
		}
	}

	$pass1 = '';
	$pass2 = '';
	if ( isset( $_POST['pass1'] ) ) {
		$pass1 = trim( $_POST['pass1'] );
	}
	if ( isset( $_POST['pass2'] ) ) {
		$pass2 = trim( $_POST['pass2'] );
	}

	if ( isset( $_POST['role'] ) && current_user_can( 'promote_users' ) && ( ! $user_id || current_user_can( 'promote_user', $user_id ) ) ) {
		$new_role = sanitize_text_field( $_POST['role'] );

		// If the new role isn't editable by the logged-in user die with error.
		$editable_roles = get_editable_roles();
		if ( ! empty( $new_role ) && empty( $editable_roles[ $new_role ] ) ) {
			wp_die( __( 'Sorry, you are not allowed to give users that role.' ), 403 );
		}

		$potential_role = isset( $wp_roles->role_objects[ $new_role ] ) ? $wp_roles->role_objects[ $new_role ] : false;

		/*
		 * Don't let anyone with 'promote_users' edit their own role to something without it.
		 * Multisite super admins can freely edit their roles, they possess all caps.
		 */
		if (
			( is_multisite() && current_user_can( 'manage_network_users' ) ) ||
			get_current_user_id() !== $user_id ||
			( $potential_role && $potential_role->has_cap( 'promote_users' ) )
		) {
			if ( ! $update ) {
				// New users are pending until user confirms activation.
				$user->role            = 'pending_activation';
				$user->activate_to_role = $new_role;
			} else if ( in_array( 'pending_activation', $userdata->roles, true ) ) {
				// The role of an inactive user is changed.
				// Change only the role after activation.
				$user->activate_to_role = $new_role;
				$user->role             = 'pending_activation';
			} else {
				$user->role = $new_role;
			}
		}
	}

	if ( isset( $_POST['email'] ) ) {
		$user->user_email = sanitize_text_field( wp_unslash( $_POST['email'] ) );
	}

	if ( isset( $_POST['display_name'] ) ) {
		$user->display_name = sanitize_text_field( $_POST['display_name'] );
	}

	if ( isset( $_POST['description'] ) ) {
		$user->description = trim( $_POST['description'] );
	}

	foreach ( wp_get_user_contact_methods( $user ) as $method => $name ) {
		if ( isset( $_POST[ $method ] ) ) {
			$user->$method = sanitize_text_field( $_POST[ $method ] );
		}
	}

	if ( isset( $_POST['locale'] ) ) {
		$locale = sanitize_text_field( $_POST['locale'] );
		if ( 'site-default' === $locale ) {
			$locale = '';
		} elseif ( '' === $locale ) {
			$locale = 'en_US';
		} elseif ( ! in_array( $locale, get_available_languages(), true ) ) {
			$locale = '';
		}

		$user->locale = $locale;
	}

	if ( $update ) {
		$user->admin_color          = isset( $_POST['admin_color'] ) ? sanitize_text_field( $_POST['admin_color'] ) : 'fresh';
		$user->show_admin_bar_front = isset( $_POST['admin_bar_front'] ) ? 'true' : 'false';
	}

	$errors = new WP_Error();

	if ( $user_id && isset( $_POST['calm_avatar_image_attachement_id'] ) ) {
		$avatar_attachment_id = wp_unslash( $_POST['calm_avatar_image_attachement_id'] );
		$avatar_attachment_id = filter_var( $avatar_attachment_id, FILTER_VALIDATE_INT );
		if ( false !== $avatar_attachment_id ) {
			$wp_user = new \WP_User( $user_id );
			if ( 0 === $avatar_attachment_id ) {
				$wp_user->remove_avatar();
			} elseif ( current_user_can( 'upload_files') ) {
				$wp_user->set_avatar( get_post( $avatar_attachment_id ) );
			} else {
				$errors->add( 'can_not_set_avatar', __( '<strong>Error</strong>: You do not have the permission to set avatars.' ) );
			}
		} else {
			$errors->add( 'can_not_set_avatar', __( '<strong>Error</strong>: Failed setting the avatar.' ) );
		}
	}

	if ( $update && isset( $_POST['mock_role'] ) ) {
		$mock_role = wp_unslash( $_POST['mock_role'] );
		if ( ! in_array( $mock_role, [ 'editor', 'author' ], true ) ) {
			$mock_role = '';
		}
		$last_mock       = '';
		if ( isset( $userdata->mock_role ) ) {
			$last_mock = $userdata->mock_role;
		}
		$user->mock_role = $mock_role;
		if ( '' === $last_mock && '' !== $mock_role ) {
			$user->mock_role_expiry = time() + 14 * DAY_IN_SECONDS;
		}
	}

	/**
	 * Fires before the password and confirm password fields are checked for congruity.
	 *
	 * @since 1.5.1
	 *
	 * @param string $user_login The username.
	 * @param string $pass1     The password (passed by reference).
	 * @param string $pass2     The confirmed password (passed by reference).
	 */
	do_action_ref_array( 'check_passwords', array( $user->user_login, &$pass1, &$pass2 ) );

	// Check for blank password when adding a user.
	if ( ! $update && empty( $pass1 ) ) {
		$errors->add( 'pass', __( '<strong>Error</strong>: Please enter a password.' ), array( 'form-field' => 'pass1' ) );
	}

	// Check for "\" in password.
	if ( false !== strpos( wp_unslash( $pass1 ), '\\' ) ) {
		$errors->add( 'pass', __( '<strong>Error</strong>: Passwords may not contain the character "\\".' ), array( 'form-field' => 'pass1' ) );
	}

	// Checking the password has been typed twice the same.
	if ( ( $update || ! empty( $pass1 ) ) && $pass1 != $pass2 ) {
		$errors->add( 'pass', __( '<strong>Error</strong>: Passwords don&#8217;t match. Please enter the same password in both password fields.' ), array( 'form-field' => 'pass1' ) );
	}

	if ( ! empty( $pass1 ) ) {
		$user->user_pass = $pass1;
	}

	if ( ! $update && isset( $_POST['user_login'] ) && ! validate_username( $_POST['user_login'] ) ) {
		$errors->add( 'user_login', __( '<strong>Error</strong>: This username is invalid because it uses illegal characters. Please enter a valid username.' ) );
	}

	/* checking email address */
	if ( empty( $user->user_email ) ) {
		$errors->add( 'empty_email', __( '<strong>Error</strong>: Please enter an email address.' ), array( 'form-field' => 'email' ) );
	} elseif ( ! is_email( $user->user_email ) ) {
		$errors->add(
			'invalid_email',
			/* translators: s: The new email address. */
			sprintf( __( '<strong>Error</strong>: The email address given "%s" is invalid.' ), $user->user_email ),
			array( 'form-field' => 'email' )
		);
	} else {
		$owner_id = email_exists( $user->user_email );
		if ( $owner_id && ( ! $update || ( $owner_id != $user->ID ) ) ) {
			$errors->add( 
				'email_exists',
				/* translators: s: The new email address. */
				sprintf( __( '<strong>Error</strong>: The email %s is already registered.' ), $user->user_email ),
				array( 'form-field' => 'email' ) );
		}
	}

	/** This filter is documented in wp-includes/user.php */
	$illegal_logins = (array) apply_filters( 'illegal_user_logins', array() );

	if ( in_array( strtolower( $user->user_login ), array_map( 'strtolower', $illegal_logins ) ) ) {
		$errors->add( 'invalid_username', __( '<strong>ERROR</strong>: Sorry, that username is not allowed.' ) );
	}

	// Check if user email is being changed while an email change already in progress.
	if ( $update && ( $user->user_email !== $userdata->user_email ) && $userdata->email_change_in_progress() ) {
		$errors->add( 'user_email_change_in_progress', __( '<strong>ERROR</strong>: Sorry, there is an active email change request and it needs to be canceled or expired before attempting another change.' ) );
	}

	/**
	 * Fires before user profile update errors are returned.
	 *
	 * @since 2.8.0
	 *
	 * @param WP_Error $errors WP_Error object (passed by reference).
	 * @param bool     $update Whether this is a user update.
	 * @param stdClass $user   User object (passed by reference).
	 */
	do_action_ref_array( 'user_profile_update_errors', array( &$errors, $update, &$user ) );

	if ( $errors->has_errors() ) {
		return $errors;
	}

	if ( $update ) {
		$new_email = $user->user_email;
		if ( $user->user_email !== $userdata->user_email ) {
			// Do not change email address for active users, approve it first.
			// Unless it is an installer waiting for email address verification.
			if ( ! in_array( 'pending_activation', $userdata->roles, true ) &&
			     ! get_user_meta( $user_id, 'installer_verify_email', true ) ) {
				$user->user_email = $userdata->user_email;
			}
			$user_id = wp_update_user( $user );
			// Reload user from DB to make sure all data is up to date.
			$tuser = get_user_by( 'id', $user_id );
			$tuser->change_email( new Email_Address( $new_email ) );
		} else {
			$user_id = wp_update_user( $user );
		}
	} else {
		$user_id = wp_insert_user( $user );
		$notify  = 'both';

		/**
		 * Fires after a new user has been created.
		 *
		 * @since 4.4.0
		 *
		 * @param int|WP_Error $user_id ID of the newly created user or WP_Error on failure.
		 * @param string       $notify  Type of notification that should happen. See
		 *                              wp_send_new_user_notifications() for more information.
		 */
		do_action( 'edit_user_created_user', $user_id, $notify );
	}
	return $user_id;
}

/**
 * Fetch a filtered list of user roles that the current user is
 * allowed to edit.
 *
 * Simple function whose main purpose is to allow filtering of the
 * list of roles in the $wp_roles object so that plugins can remove
 * inappropriate ones depending on the situation or user making edits.
 * Specifically because without filtering anyone with the edit_users
 * capability can edit others to be administrators, even if they are
 * only editors or authors. This filter allows admins to delegate
 * user management.
 *
 * @since 2.8.0
 *
 * @return array[] Array of arrays containing role information.
 */
function get_editable_roles() {
	$all_roles = wp_roles()->roles;

	unset( $all_roles['pending_activation'] );

	/**
	 * Filters the list of editable roles.
	 *
	 * @since 2.8.0
	 *
	 * @param array[] $all_roles Array of arrays containing role information.
	 */
	$editable_roles = apply_filters( 'editable_roles', $all_roles );

	return $editable_roles;
}

/**
 * Retrieve user data and filter it.
 *
 * @since 2.0.5
 *
 * @param int $user_id User ID.
 * @return WP_User|false WP_User object on success, false on failure.
 */
function get_user_to_edit( $user_id ) {
	$user = get_userdata( $user_id );

	if ( $user ) {
		$user->filter = 'edit';
	}

	return $user;
}

/**
 * Retrieve the user's drafts.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $user_id User ID.
 * @return array
 */
function get_users_drafts( $user_id ) {
	global $wpdb;
	$query = $wpdb->prepare( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'draft' AND post_author = %d ORDER BY post_modified DESC", $user_id );

	/**
	 * Filters the user's drafts query string.
	 *
	 * @since 2.0.0
	 *
	 * @param string $query The user's drafts query string.
	 */
	$query = apply_filters( 'get_users_drafts', $query );
	return $wpdb->get_results( $query );
}

/**
 * Remove user and optionally reassign posts and links to another user.
 *
 * If the $reassign parameter is not assigned to a User ID, then all posts will
 * be deleted of that user. The action {@see 'delete_user'} that is passed the User ID
 * being deleted will be run after the posts are either reassigned or deleted.
 * The user meta will also be deleted that are for that User ID.
 *
 * @since 2.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $id User ID.
 * @param int $reassign Optional. Reassign posts and links to new User ID.
 * @return bool True when finished.
 */
function wp_delete_user( $id, $reassign = null ) {
	global $wpdb;

	if ( ! is_numeric( $id ) ) {
		return false;
	}

	$id   = (int) $id;
	$user = new WP_User( $id );

	if ( ! $user->exists() ) {
		return false;
	}

	// Normalize $reassign to null or a user ID. 'novalue' was an older default.
	if ( 'novalue' === $reassign ) {
		$reassign = null;
	} elseif ( null !== $reassign ) {
		$reassign = (int) $reassign;
	}

	/**
	 * Fires immediately before a user is deleted from the database.
	 *
	 * @since 2.0.0
	 * @since 5.5.0 Added the `$user` parameter.
	 *
	 * @param int      $id       ID of the user to delete.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the user to delete.
	 */
	do_action( 'delete_user', $id, $reassign, $user );

	if ( null === $reassign ) {
		$post_types_to_delete = array();
		foreach ( get_post_types( array(), 'objects' ) as $post_type ) {
			if ( $post_type->delete_with_user ) {
				$post_types_to_delete[] = $post_type->name;
			} elseif ( null === $post_type->delete_with_user && post_type_supports( $post_type->name, 'author' ) ) {
				$post_types_to_delete[] = $post_type->name;
			}
		}

		/**
		 * Filters the list of post types to delete with a user.
		 *
		 * @since 3.4.0
		 *
		 * @param string[] $post_types_to_delete Array of post types to delete.
		 * @param int      $id                   User ID.
		 */
		$post_types_to_delete = apply_filters( 'post_types_to_delete_with_user', $post_types_to_delete, $id );
		$post_types_to_delete = implode( "', '", $post_types_to_delete );
		$post_ids             = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d AND post_type IN ('$post_types_to_delete')", $id ) );
		if ( $post_ids ) {
			foreach ( $post_ids as $post_id ) {
				wp_delete_post( $post_id );
			}
		}

	} else {
		$post_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_author = %d", $id ) );
		$wpdb->update( $wpdb->posts, array( 'post_author' => $reassign ), array( 'post_author' => $id ) );
		if ( ! empty( $post_ids ) ) {
			foreach ( $post_ids as $post_id ) {
				clean_post_cache( $post_id );
			}
		}
	}

	// FINALLY, delete user.
	if ( is_multisite() ) {
		remove_user_from_blog( $id, get_current_blog_id() );
	} else {
		$meta = $wpdb->get_col( $wpdb->prepare( "SELECT umeta_id FROM $wpdb->usermeta WHERE user_id = %d", $id ) );
		foreach ( $meta as $mid ) {
			delete_metadata_by_mid( 'user', $mid );
		}

		$wpdb->delete( $wpdb->users, array( 'ID' => $id ) );
	}

	clean_user_cache( $user );

	/**
	 * Fires immediately after a user is deleted from the database.
	 *
	 * @since 2.9.0
	 * @since 5.5.0 Added the `$user` parameter.
	 *
	 * @param int      $id       ID of the deleted user.
	 * @param int|null $reassign ID of the user to reassign posts and links to.
	 *                           Default null, for no reassignment.
	 * @param WP_User  $user     WP_User object of the deleted user.
	 */
	do_action( 'deleted_user', $id, $reassign, $user );

	return true;
}

/**
 * Remove all capabilities from user.
 *
 * @since 2.1.0
 *
 * @param int $id User ID.
 */
function wp_revoke_user( $id ) {
	$id = (int) $id;

	$user = new WP_User( $id );
	$user->remove_all_caps();
}

/**
 * @since 2.8.0
 *
 * @global int $user_ID
 *
 * @param false $errors Deprecated.
 */
function default_password_nag_handler( $errors = false ) {
	global $user_ID;
	// Short-circuit it.
	if ( ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	// get_user_setting() = JS-saved UI setting. Else no-js-fallback code.
	if ( 'hide' === get_user_setting( 'default_password_nag' )
		|| isset( $_GET['default_password_nag'] ) && '0' == $_GET['default_password_nag']
	) {
		delete_user_setting( 'default_password_nag' );
		update_user_meta( $user_ID, 'default_password_nag', false );
	}
}

/**
 * @since 2.8.0
 *
 * @param int     $user_ID
 * @param WP_User $old_data
 */
function default_password_nag_edit_user( $user_ID, $old_data ) {
	// Short-circuit it.
	if ( ! get_user_option( 'default_password_nag', $user_ID ) ) {
		return;
	}

	$new_data = get_userdata( $user_ID );

	// Remove the nag if the password has been changed.
	if ( $new_data->user_pass != $old_data->user_pass ) {
		delete_user_setting( 'default_password_nag' );
		update_user_meta( $user_ID, 'default_password_nag', false );
	}
}

/**
 * @since 2.8.0
 *
 * @global string $pagenow
 */
function default_password_nag() {
	global $pagenow;
	// Short-circuit it.
	if ( 'profile.php' === $pagenow || ! get_user_option( 'default_password_nag' ) ) {
		return;
	}

	echo '<div class="error default-password-nag">';
	echo '<p>';
	echo '<strong>' . __( 'Notice:' ) . '</strong> ';
	_e( 'You&rsquo;re using the auto-generated password for your account. Would you like to change it?' );
	echo '</p><p>';
	printf( '<a href="%s">' . __( 'Yes, take me to my profile page' ) . '</a> | ', get_edit_profile_url() . '#password' );
	printf( '<a href="%s" id="default-password-nag-no">' . __( 'No thanks, do not remind me again' ) . '</a>', '?default_password_nag=0' );
	echo '</p></div>';
}

/**
 * @since 3.5.0
 * @access private
 */
function delete_users_add_js() {
	?>
<script>
jQuery( function($) {
	var submit = $('#submit').prop('disabled', true);
	$('input[name="delete_option"]').one('change', function() {
		submit.prop('disabled', false);
	});
	$('#reassign_user').focus( function() {
		$('#delete_option1').prop('checked', true).trigger('change');
	});
} );
</script>
	<?php
}

/**
 * @since MU (3.0.0)
 *
 * @param string $text
 * @return string
 */
function admin_created_user_email( $text ) {
	$roles = get_editable_roles();
	$role  = $roles[ $_REQUEST['role'] ];

	return sprintf(
		/* translators: 1: Site title, 2: Site URL, 3: User role. */
		__(
			'Hi,
You\'ve been invited to join \'%1$s\' at
%2$s with the role of %3$s.
If you do not want to join this site please ignore
this email. This invitation will expire in a few days.

Please click the following link to activate your user account:
%%s'
		),
		wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		home_url(),
		wp_specialchars_decode( translate_user_role( $role['name'] ) )
	);
}

/**
 * Checks if the Authorize Application Password request is valid.
 *
 * @since 5.6.0
 *
 * @param array   $request {
 *     The array of request data. All arguments are optional and may be empty.
 *
 *     @type string $app_name    The suggested name of the application.
 *     @type string $app_id      A UUID provided by the application to uniquely identify it.
 *     @type string $success_url The URL the user will be redirected to after approving the application.
 *     @type string $reject_url  The URL the user will be redirected to after rejecting the application.
 * }
 * @param WP_User $user The user authorizing the application.
 * @return true|WP_Error True if the request is valid, a WP_Error object contains errors if not.
 */
function wp_is_authorize_application_password_request_valid( $request, $user ) {
	$error = new WP_Error();

	if ( ! empty( $request['success_url'] ) ) {
		$scheme = wp_parse_url( $request['success_url'], PHP_URL_SCHEME );

		if ( 'http' === $scheme ) {
			$error->add(
				'invalid_redirect_scheme',
				__( 'The success URL must be served over a secure connection.' )
			);
		}
	}

	if ( ! empty( $request['reject_url'] ) ) {
		$scheme = wp_parse_url( $request['reject_url'], PHP_URL_SCHEME );

		if ( 'http' === $scheme ) {
			$error->add(
				'invalid_redirect_scheme',
				__( 'The rejection URL must be served over a secure connection.' )
			);
		}
	}

	if ( ! empty( $request['app_id'] ) && ! wp_is_uuid( $request['app_id'] ) ) {
		$error->add(
			'invalid_app_id',
			__( 'The application ID must be a UUID.' )
		);
	}

	/**
	 * Fires before application password errors are returned.
	 *
	 * @since 5.6.0
	 *
	 * @param WP_Error $error   The error object.
	 * @param array    $request The array of request data.
	 * @param WP_User  $user    The user authorizing the application.
	 */
	do_action( 'wp_authorize_application_password_request_errors', $error, $request, $user );

	if ( $error->has_errors() ) {
		return $error;
	}

	return true;
}
