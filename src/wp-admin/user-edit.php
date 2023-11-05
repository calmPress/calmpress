<?php
/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

wp_reset_vars( array( 'action', 'user_id', 'wp_http_referer' ) );

$user_id      = (int) $user_id;
$current_user = wp_get_current_user();
if ( ! defined( 'IS_PROFILE_PAGE' ) ) {
	define( 'IS_PROFILE_PAGE', ( $user_id == $current_user->ID ) );
}

if ( ! $user_id && IS_PROFILE_PAGE ) {
	$user_id = $current_user->ID;
} elseif ( ! $user_id && ! IS_PROFILE_PAGE ) {
	wp_die( __( 'Invalid user ID.' ) );
} elseif ( ! get_userdata( $user_id ) ) {
	wp_die( __( 'Invalid user ID.' ) );
}

wp_enqueue_media();
wp_enqueue_script( 'user-profile' );

if ( wp_is_application_passwords_available_for_user( $user_id ) ) {
	wp_enqueue_script( 'application-passwords' );
}

if ( IS_PROFILE_PAGE ) {
	// Used in the HTML title tag.
	$title = __( 'Profile' );
} else {
	// Used in the HTML title tag.
	/* translators: %s: User's display name. */
	$title = __( 'Edit User %s' );
}

if ( current_user_can( 'edit_users' ) && ! IS_PROFILE_PAGE ) {
	$submenu_file = 'users.php';
} else {
	$submenu_file = 'profile.php';
}

if ( current_user_can( 'edit_users' ) && ! is_user_admin() ) {
	$parent_file = 'users.php';
} else {
	$parent_file = 'profile.php';
}

$profile_help = '<p>' . __( 'Your profile contains information about you (your &#8220;account&#8221;) as well as some personal options related to using calmPress.' ) . '</p>' .
	'<p>' . __( 'You can change your password among other things. You can hide the Toolbar (formerly called the Admin Bar) from the front end of your site, however it cannot be disabled on the admin screens.' ) . '</p>' .
	'<p>' . __( 'You can select the language you wish to use while using the calmPress administration screen without affecting the language site visitors see.' ) . '</p>' .
	'<p>' . __( 'You can log out of other devices, such as your phone or a public computer, by clicking the Log Out Everywhere Else button.' ) . '</p>' .
	'<p>' . __( 'Required fields are indicated; the rest are optional. Profile information will only be displayed if your theme is set up to do so.' ) . '</p>' .
	'<p>' . __( 'Remember to click the Update Profile button when you are finished.' ) . '</p>';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $profile_help,
	)
);

$wp_http_referer = remove_query_arg( array( 'update', 'delete_count', 'user_id' ), $wp_http_referer );

$user_can_edit = current_user_can( 'edit_posts' ) || current_user_can( 'edit_pages' );

/**
 * Filters whether to allow administrators on Multisite to edit every user.
 *
 * Enabling the user editing form via this filter also hinges on the user holding
 * the 'manage_network_users' cap, and the logged-in user not matching the user
 * profile open for editing.
 *
 * The filter was introduced to replace the EDIT_ANY_USER constant.
 *
 * @since 3.0.0
 *
 * @param bool $allow Whether to allow editing of any user. Default true.
 */
if ( is_multisite()
	&& ! current_user_can( 'manage_network_users' )
	&& $user_id != $current_user->ID
	&& ! apply_filters( 'enable_edit_any_user_configuration', true )
) {
	wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
}

// Execute confirmed email change. See send_confirmation_on_profile_email().
if ( IS_PROFILE_PAGE && isset( $_GET['newuseremail'] ) && $current_user->ID ) {
	$new_email = get_user_meta( $current_user->ID, '_new_email', true );
	if ( $new_email && hash_equals( $new_email['hash'], $_GET['newuseremail'] ) ) {
		$user             = new stdClass;
		$user->ID         = $current_user->ID;
		$user->user_email = esc_html( trim( $new_email['newemail'] ) );
		if ( is_multisite() && $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", $current_user->user_login ) ) ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->signups} SET user_email = %s WHERE user_login = %s", $user->user_email, $current_user->user_login ) );
		}
		wp_update_user( $user );
		delete_user_meta( $current_user->ID, '_new_email' );
		wp_redirect( add_query_arg( array( 'updated' => 'true' ), self_admin_url( 'profile.php' ) ) );
		die();
	} else {
		wp_redirect( add_query_arg( array( 'error' => 'new-email' ), self_admin_url( 'profile.php' ) ) );
	}
} elseif ( IS_PROFILE_PAGE && ! empty( $_GET['dismiss'] ) && $current_user->ID . '_new_email' === $_GET['dismiss'] ) {
	check_admin_referer( 'dismiss-' . $current_user->ID . '_new_email' );
	delete_user_meta( $current_user->ID, '_new_email' );
	wp_redirect( add_query_arg( array( 'updated' => 'true' ), self_admin_url( 'profile.php' ) ) );
	die();
}

switch ( $action ) {
	case 'update':
		check_admin_referer( 'update-user_' . $user_id );

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
		}

		if ( IS_PROFILE_PAGE ) {
			/**
			 * Fires before the page loads on the 'Profile' editing screen.
			 *
			 * The action only fires if the current user is editing their own profile.
			 *
			 * @since 2.0.0
			 *
			 * @param int $user_id The user ID.
			 */
			do_action( 'personal_options_update', $user_id );
		} else {
			/**
			 * Fires before the page loads on the 'Edit User' screen.
			 *
			 * @since 2.7.0
			 *
			 * @param int $user_id The user ID.
			 */
			do_action( 'edit_user_profile_update', $user_id );
		}

		// Update the email address in signups, if present.
		if ( is_multisite() ) {
			$user = get_userdata( $user_id );

			if ( $user->user_login && isset( $_POST['email'] ) && is_email( $_POST['email'] ) && $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM {$wpdb->signups} WHERE user_login = %s", $user->user_login ) ) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->signups} SET user_email = %s WHERE user_login = %s", $_POST['email'], $user_login ) );
			}
		}

		// Update the user.
		$errors = edit_user( $user_id );

		// Grant or revoke super admin status if requested.
		if ( is_multisite() && is_network_admin() && ! IS_PROFILE_PAGE && current_user_can( 'manage_network_options' ) && ! isset( $super_admins ) && empty( $_POST['super_admin'] ) == is_super_admin( $user_id ) ) {
			empty( $_POST['super_admin'] ) ? revoke_super_admin( $user_id ) : grant_super_admin( $user_id );
		}

		if ( ! is_wp_error( $errors ) ) {
			$redirect = add_query_arg( 'updated', true, get_edit_user_link( $user_id ) );
			if ( $wp_http_referer ) {
				$redirect = add_query_arg( 'wp_http_referer', urlencode( $wp_http_referer ), $redirect );
			}
			wp_redirect( $redirect );
			exit;
		}

		// Intentional fall-through to display $errors.
	default:
		$profileuser = get_user_to_edit( $user_id );

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
		}

		$title    = sprintf( $title, $profileuser->display_name );
		$sessions = WP_Session_Tokens::get_instance( $profileuser->ID );

		require_once ABSPATH . 'wp-admin/admin-header.php';
		?>

		<?php if ( ! IS_PROFILE_PAGE && is_super_admin( $profileuser->ID ) && current_user_can( 'manage_network_options' ) ) { ?>
	<div class="notice notice-info"><p><strong><?php _e( 'Important:' ); ?></strong> <?php _e( 'This user has super admin privileges.' ); ?></p></div>
<?php } ?>
		<?php if ( isset( $_GET['updated'] ) ) : ?>
<div id="message" class="updated notice is-dismissible">
			<?php if ( IS_PROFILE_PAGE ) : ?>
	<p><strong><?php _e( 'Profile updated.' ); ?></strong></p>
	<?php else : ?>
	<p><strong><?php _e( 'User updated.' ); ?></strong></p>
	<?php endif; ?>
			<?php if ( $wp_http_referer && false === strpos( $wp_http_referer, 'user-new.php' ) && ! IS_PROFILE_PAGE ) : ?>
	<p><a href="<?php echo esc_url( wp_validate_redirect( esc_url_raw( $wp_http_referer ), self_admin_url( 'users.php' ) ) ); ?>"><?php _e( '&larr; Go to Users' ); ?></a></p>
	<?php endif; ?>
</div>
		<?php endif; ?>
		<?php if ( isset( $_GET['error'] ) ) : ?>
<div class="notice notice-error">
			<?php if ( 'new-email' === $_GET['error'] ) : ?>
	<p><?php _e( 'Error while saving the new email address. Please try again.' ); ?></p>
	<?php endif; ?>
</div>
		<?php endif; ?>
		<?php if ( isset( $errors ) && is_wp_error( $errors ) ) : ?>
<div class="error"><p><?php echo implode( "</p>\n<p>", $errors->get_error_messages() ); ?></p></div>
		<?php endif; ?>

<div class="wrap" id="profile-page">
<h1 class="wp-heading-inline">
		<?php
		echo esc_html( $title );
		?>
</h1>

		<?php
		if ( ! IS_PROFILE_PAGE ) {
			if ( current_user_can( 'create_users' ) ) {
				?>
		<a href="user-new.php" class="page-title-action"><?php echo esc_html_x( 'Add New', 'user' ); ?></a>
	<?php } elseif ( is_multisite() && current_user_can( 'promote_users' ) ) { ?>
		<a href="user-new.php" class="page-title-action"><?php echo esc_html_x( 'Add Existing', 'user' ); ?></a>
				<?php
	}
		}
		?>

<hr class="wp-header-end">

<form id="your-profile" action="<?php echo esc_url( self_admin_url( IS_PROFILE_PAGE ? 'profile.php' : 'user-edit.php' ) ); ?>" method="post" novalidate="novalidate"
		<?php
		/**
		 * Fires inside the your-profile form tag on the user editing screen.
		 *
		 * @since 3.0.0
		 */
		do_action( 'user_edit_form_tag' );
		?>
	>
		<?php wp_nonce_field( 'update-user_' . $user_id ); ?>
		<?php if ( $wp_http_referer ) : ?>
	<input type="hidden" name="wp_http_referer" value="<?php echo esc_url( $wp_http_referer ); ?>" />
		<?php endif; ?>
<p>
<input type="hidden" name="from" value="profile" />
<input type="hidden" name="checkuser_id" value="<?php echo get_current_user_id(); ?>" />
</p>

<h2><?php _e( 'Account Management' ); ?></h2>
<table class="form-table" role="presentation">
	<tr class="user-email-wrap">
		<th><label for="email"><?php _e( 'Email' ); ?> <span class="description"><?php _e( '(required)' ); ?></span></label></th>
		<td><input type="email" name="email" id="email" aria-describedby="email-description" value="<?php echo esc_attr( $profileuser->user_email ); ?>" class="regular-text ltr" />
		<?php
		if ( $profileuser->ID == $current_user->ID ) :
			?>
		<p class="description" id="email-description">
			<?php _e( 'If you change this, we will send you an email at your new address to confirm it. <strong>The new address will not become active until confirmed.</strong>' ); ?>
		</p>
			<?php
		endif;

		$new_email = get_user_meta( $current_user->ID, '_new_email', true );
		if ( $new_email && $new_email['newemail'] != $current_user->user_email && $profileuser->ID == $current_user->ID ) :
			?>
		<div class="updated inline">
		<p>
			<?php
			printf(
				/* translators: %s: New email. */
				__( 'There is a pending change of your email to %s.' ),
				'<code>' . esc_html( $new_email['newemail'] ) . '</code>'
			);
			printf(
				' <a href="%1$s">%2$s</a>',
				esc_url( wp_nonce_url( self_admin_url( 'profile.php?dismiss=' . $current_user->ID . '_new_email' ), 'dismiss-' . $current_user->ID . '_new_email' ) ),
				__( 'Cancel' )
			);
			?>
		</p>
		</div>
		<?php endif; ?>
	</td>
	</tr>
<?php
		/**
		 * Filters the display of the password fields.
		 *
		 * @since 1.5.1
		 * @since 2.8.0 Added the `$profileuser` parameter.
		 * @since 4.4.0 Now evaluated only in user-edit.php.
		 *
		 * @param bool    $show        Whether to show the password fields. Default true.
		 * @param WP_User $profileuser User object for the current user to edit.
		 */
		$show_password_fields = apply_filters( 'show_password_fields', true, $profileuser );
		if ( $show_password_fields ) :
			?>
<tr id="password" class="user-pass1-wrap">
	<th><label for="pass1"><?php _e( 'New Password' ); ?></label></th>
	<td>
		<input class="hidden" value=" " /><!-- #24364 workaround -->
		<button type="button" class="button wp-generate-pw hide-if-no-js" aria-expanded="false"><?php _e( 'Set New Password' ); ?></button>
		<div class="wp-pwd hide-if-js">
			<span class="password-input-wrapper">
				<input type="password" name="pass1" id="pass1" class="regular-text" value="" autocomplete="off" data-pw="<?php echo esc_attr( wp_generate_password( 24 ) ); ?>" aria-describedby="pass-strength-result" />
			</span>
			<button type="button" class="button wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
				<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
				<span class="text"><?php _e( 'Hide' ); ?></span>
			</button>
			<button type="button" class="button wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Cancel password change' ); ?>">
				<span class="dashicons dashicons-no" aria-hidden="true"></span>
				<span class="text"><?php _e( 'Cancel' ); ?></span>
			</button>
			<div style="display:none" id="pass-strength-result" aria-live="polite"></div>
		</div>
	</td>
</tr>
<tr class="user-pass2-wrap hide-if-js">
	<th scope="row"><label for="pass2"><?php _e( 'Repeat New Password' ); ?></label></th>
	<td>
	<input name="pass2" type="password" id="pass2" class="regular-text" value="" autocomplete="off" aria-describedby="pass2-desc" />
			<?php if ( IS_PROFILE_PAGE ) : ?>
				<p class="description" id="pass2-desc"><?php _e( 'Type your new password again.' ); ?></p>
			<?php else : ?>
				<p class="description" id="pass2-desc"><?php _e( 'Type the new password again.' ); ?></p>
			<?php endif; ?>
	</td>
</tr>
<tr class="pw-weak">
	<th><?php _e( 'Confirm Password' ); ?></th>
	<td>
		<label>
			<input type="checkbox" name="pw_weak" class="pw-checkbox" />
			<span id="pw-weak-text-label"><?php _e( 'Confirm use of weak password' ); ?></span>
		</label>
	</td>
</tr>
	<?php endif; ?>

		<?php
		// Allow admins to send reset password link.
		if ( ! IS_PROFILE_PAGE ) :
			?>
	<tr class="user-generate-reset-link-wrap hide-if-no-js">
		<th><?php _e( 'Password Reset' ); ?></th>
		<td>
			<div class="generate-reset-link">
				<button type="button" class="button button-secondary" id="generate-reset-link">
					<?php _e( 'Send Reset Link' ); ?>
				</button>
			</div>
			<p class="description">
				<?php
				/* translators: %s: User's display name. */
				printf( __( 'Send %s a link to reset their password. This will not change their password, nor will it force a change.' ), esc_html( $profileuser->display_name ) );
				?>
			</p>
		</td>
	</tr>
		<?php endif; ?>

		<?php
		if ( IS_PROFILE_PAGE && count( $sessions->get_all() ) === 1 ) :
			?>
	<tr class="user-sessions-wrap hide-if-no-js">
		<th><?php _e( 'Sessions' ); ?></th>
		<td aria-live="assertive">
			<div class="destroy-sessions"><button type="button" disabled class="button"><?php _e( 'Log Out Everywhere Else' ); ?></button></div>
			<p class="description">
				<?php _e( 'You are only logged in at this location.' ); ?>
			</p>
		</td>
	</tr>
<?php elseif ( IS_PROFILE_PAGE && count( $sessions->get_all() ) > 1 ) : ?>
	<tr class="user-sessions-wrap hide-if-no-js">
		<th><?php _e( 'Sessions' ); ?></th>
		<td aria-live="assertive">
			<div class="destroy-sessions"><button type="button" class="button" id="destroy-sessions"><?php _e( 'Log Out Everywhere Else' ); ?></button></div>
			<p class="description">
				<?php _e( 'Did you lose your phone or leave your account logged in at a public computer? You can log out everywhere else, and stay logged in here.' ); ?>
			</p>
		</td>
	</tr>
<?php elseif ( ! IS_PROFILE_PAGE && $sessions->get_all() ) : ?>
	<tr class="user-sessions-wrap hide-if-no-js">
		<th><?php _e( 'Sessions' ); ?></th>
		<td>
			<p><button type="button" class="button" id="destroy-sessions"><?php _e( 'Log Out Everywhere' ); ?></button></p>
			<p class="description">
				<?php
				/* translators: %s: User's display name. */
				printf( __( 'Log %s out of all locations.' ), $profileuser->display_name );
				?>
			</p>
		</td>
	</tr>
<?php endif; ?>

</table>

<h2><?php _e( 'Administration Interface Options' ); ?></h2>

<table class="form-table" role="presentation">
		<?php if ( count( $_wp_admin_css_colors ) > 1 && has_action( 'admin_color_scheme_picker' ) ) : ?>
	<tr class="user-admin-color-wrap">
		<th scope="row"><?php _e( 'Admin Color Scheme' ); ?></th>
		<td>
			<?php
			/**
			 * Fires in the 'Admin Color Scheme' section of the user editing screen.
			 *
			 * The section is only enabled if a callback is hooked to the action,
			 * and if there is more than one defined color scheme for the admin.
			 *
			 * @since 3.0.0
			 * @since 3.8.1 Added `$user_id` parameter.
			 *
			 * @param int $user_id The user ID.
			 */
			do_action( 'admin_color_scheme_picker', $user_id );
			?>
		</td>
	</tr>
		<?php endif; // End if count ( $_wp_admin_css_colors ) > 1 ?>

	<tr class="show-admin-bar user-admin-bar-front-wrap">
		<th scope="row"><?php _e( 'Toolbar' ); ?></th>
		<td>
			<label for="admin_bar_front">
				<input name="admin_bar_front" type="checkbox" id="admin_bar_front" value="1"<?php checked( _get_admin_bar_pref( 'front', $profileuser->ID ) ); ?> />
				<?php _e( 'Show Toolbar when viewing site' ); ?>
			</label><br />
		</td>
	</tr>

		<?php
		$languages = get_available_languages();
		if ( $languages ) :
			?>
	<tr class="user-language-wrap">
		<th scope="row">
			<?php /* translators: The user language selection field label. */ ?>
			<label for="locale"><?php _e( 'Language' ); ?><span class="dashicons dashicons-translation" aria-hidden="true"></span></label>
		</th>
		<td>
			<?php
				$user_locale = $profileuser->locale;

			if ( 'en_US' === $user_locale ) {
				$user_locale = '';
			} elseif ( '' === $user_locale || ! in_array( $user_locale, $languages, true ) ) {
				$user_locale = 'site-default';
			}

			wp_dropdown_languages(
				array(
					'name'                        => 'locale',
					'id'                          => 'locale',
					'selected'                    => $user_locale,
					'languages'                   => $languages,
					'show_available_translations' => false,
					'show_option_site_default'    => true,
				)
			);
			?>
		</td>
	</tr>
			<?php
endif;
		?>


		<?php if ( ! IS_PROFILE_PAGE && ! is_network_admin() && current_user_can( 'promote_user', $profileuser->ID ) ) : ?>
<tr class="user-role-wrap"><th><label for="role"><?php _e( 'Role' ); ?></label></th>
<td><select name="role" id="role">
			<?php
			// Compare user role against currently editable roles.
			$user_roles = array_intersect( array_values( $profileuser->roles ), array_keys( get_editable_roles() ) );
			$user_role  = reset( $user_roles );

			// Print the full list of roles with the primary one selected.
			wp_dropdown_roles( $user_role );

			// Print the 'no role' option. Make it selected if the user has no role yet.
			if ( $user_role ) {
				echo '<option value="">' . __( '&mdash; No role for this site &mdash;' ) . '</option>';
			} else {
				echo '<option value="" selected="selected">' . __( '&mdash; No role for this site &mdash;' ) . '</option>';
			}
			?>
</select></td></tr>
			<?php
		endif; // End if ! IS_PROFILE_PAGE.

		if ( is_multisite() && is_network_admin() && ! IS_PROFILE_PAGE && current_user_can( 'manage_network_options' ) && ! isset( $super_admins ) ) {
			?>
<tr class="user-super-admin-wrap"><th><?php _e( 'Super Admin' ); ?></th>
<td>
			<?php if ( 0 !== strcasecmp( $profileuser->user_email, get_site_option( 'admin_email' ) ) || ! is_super_admin( $profileuser->ID ) ) : ?>
<p><label><input type="checkbox" id="super_admin" name="super_admin"<?php checked( is_super_admin( $profileuser->ID ) ); ?> /> <?php _e( 'Grant this user super admin privileges for the Network.' ); ?></label></p>
<?php else : ?>
<p><?php _e( 'Super admin privileges cannot be removed because this user has the network admin email.' ); ?></p>
<?php endif; ?>
</td></tr>
			<?php
		}

		if ( in_array( 'administrator', $profileuser->roles, true ) ) {
			?>
<tr id="mock-role-wrap" class="user-mock-role-wrap"><th><label for="mock-role"><?php esc_html_e( 'Behave like the role' ); ?></label></th>
<td><select name="mock_role" id="mock-role">
			<?php
			$current_behave = $profileuser->mocked_role( );
			foreach (
				[
					'' => 'Administrator',
					'editor' => 'Editor',
					'author' => 'Author',
				]
				as $key => $role ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( $key, $current_behave, false ) .'>' . esc_html(  translate_user_role( $role ) ) . '</option>';
			}
			?>
	</select>
			<?php
			$expiry = (int) get_user_meta( $profileuser->ID, 'mock_role_expiry', true );
			if ( '' !== $current_behave && $expiry > time() ) {
				?>
	<p>
				<?php
				printf(
					/* translators: %s: Time until behaviour expires. */
					esc_html__( 'the current behaviour will last for another %s.' ),
					human_time_diff( $expiry, time() )
				);
				?>
	</p>
			<?php } ?>
	<p class="description"><?php esc_html_e( 'The account can be set to behave like an Editor or Author instead of Administrator.' ); ?></p>
	<p class="description"><?php esc_html_e( 'This can be used to reduce the admin clutter if the account is used for content editting.' ); ?></p>
	<p class="description"><?php esc_html_e( 'The behaviour will last 14 days from the time is set, or until it is set to Administrator.' ); ?></p>
</td></tr>
			<?php
		}
		?>
		<?php
		/**
		 * Fires at the end of the 'Personal Options' settings table on the user editing screen.
		 *
		 * @since 2.7.0
		 *
		 * @param WP_User $profileuser The current WP_User object.
		 */
		do_action( 'personal_options', $profileuser );
		?>

		<?php
		if ( IS_PROFILE_PAGE ) {
			/**
			 * Fires after the 'Personal Options' settings table on the 'Profile' editing screen.
			 *
			 * The action only fires if the current user is editing their own profile.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_User $profileuser The current WP_User object.
			 */
			do_action( 'profile_personal_options', $profileuser );
		}
		?>
</table>
<h2><?php _e( 'Public Information' ); ?></h2>

<table class="form-table" role="presentation">
<tr class="user-display-name-wrap">
	<th><label for="display_name"><?php esc_html_e( 'Display name publicly as' ); ?></label></th>
	<td>
		<input type="text" class="regular-text" name="display_name" id="display_name" value="<?php echo esc_attr( $profileuser->display_name ); ?>">
		<p class="description">
			<?php esc_html_e( 'The name which will be used to identify you in the admin and
 at public contexts like comments.' ); ?>
		</p>
	</td>
</tr>

<tr class="user-description-wrap">
	<th><label for="description"><?php _e( 'Biographical Info' ); ?></label></th>
	<td><textarea name="description" id="description" rows="5" cols="30"><?php echo $profileuser->description; // textarea_escaped ?></textarea>
	<p class="description"><?php _e( 'Some information about yourself.' ); ?></p></td>
</tr>

<tr class="user-avatar-image">
	<th><?php esc_html_e( 'Avatar image' ); ?></th>
	<td>
		<?php
		$avatar     = $profileuser->avatar();
		$attachment = $avatar->attachment();
		if ( $attachment ) {
			$attachment_id = $attachment->ID;
			$text_avatar   = new \calmpress\avatar\Text_Based_Avatar( $profileuser->display_name, $profileuser->user_email );
			$image_display = '';
			$text_display  = ' style="display:none"';
		} else {
			$attachment_id = 0;
			$text_avatar   = $avatar;
			$text_display  = '';
			$image_display = ';display:none';
		}
		?>
		<input type="hidden" id="calm_avatar_image_attachement_id" name="calm_avatar_image_attachement_id" value="<?php echo esc_attr( $attachment_id ); ?>">
		<div id='calm_avatar_container'>
			<div style="margin-bottom:4px">
				<span id="avatar_image_preview" style="vertical-align:top<?php echo $image_display?>">
					<?php
					if ( $attachment_id ) {
						echo $avatar->html( 50, 50 );
					} else {
						echo "<img style='border-radius:50%' src='' alt='' width=50 height=50>";
					}
					?>
				</span>
				<span id="avatar_text_preview"<?php echo $text_display; ?>>
					<?php
					echo $text_avatar->html( 50, 50 );
					?>
				</span>
			</div>
			<div>
				<?php
				$disabled = '';
				if ( ! $avatar->attachment() ) {
					$disabled = ' disabled=""';
				}
				if ( current_user_can( 'upload_files' ) ) {
					echo '<button type="button" class="button" id="select_avatar_image" style="margin:0 5px">' . esc_html__( 'Use a Different Image' ) . '</button>';
				}
				echo '<button type="button" class="button" id="revert_avatar_image"' . $disabled . '>' . esc_html__( 'Revert to the Site`s Default' ) . '</button>';
				if ( ! current_user_can( 'upload_files' ) ) {
					echo '<p>' . esc_html__( 'You do not have the permissions required to upload a new avatar image.' ) . '</p>';
				}
				?>
			</div>
			<p class="description">
				<?php esc_html_e( 'This image is being displayed next to your profile name on the admin side, and might be displayed next to comments you leave and in other contexts.' ); ?>
			</p>
		</div>
	</td>
</tr>
	</table>

<?php if ( wp_is_application_passwords_available_for_user( $user_id ) || ! wp_is_application_passwords_supported() ) : // phpcs:disable Generic.WhiteSpace.ScopeIndent ?>
	<div class="application-passwords hide-if-no-js" id="application-passwords-section">
		<h2><?php _e( 'Application Passwords' ); ?></h2>
		<p><?php _e( 'Application passwords allow authentication via non-interactive systems, such as the REST API, without providing your actual password. Application passwords can be easily revoked. They cannot be used for traditional logins to your website.' ); ?></p>
		<?php if ( wp_is_application_passwords_available_for_user( $user_id ) ) : ?>
			<?php
			if ( is_multisite() ) {
				$blogs       = get_blogs_of_user( $user_id, true );
				$blogs_count = count( $blogs );
				if ( $blogs_count > 1 ) {
					?>
					<p>
						<?php
						printf(
							/* translators: 1: URL to my-sites.php, 2: Number of sites the user has. */
							_n(
								'Application passwords grant access to <a href="%1$s">the %2$s site in this installation that you have permissions on</a>.',
								'Application passwords grant access to <a href="%1$s">all %2$s sites in this installation that you have permissions on</a>.',
								$blogs_count
							),
							admin_url( 'my-sites.php' ),
							number_format_i18n( $blogs_count )
						);
						?>
					</p>
					<?php
				}
			}

			if ( ! wp_is_site_protected_by_basic_auth( 'front' ) ) {
				?>
			<table class="create-application-password form-table">
				<tr>
					<th>
						<label for="new_application_password_name"><?php _e( 'New Application Password Name' ); ?></label>
					</th>
					<td>
						<input type="text" size="30" id="new_application_password_name" name="new_application_password_name" class="input" aria-required="true" aria-describedby="new_application_password_name_desc" />
						<p class="description" id="new_application_password_name_desc"><?php _e( 'Required to create an Application Password, but not to update the user.' ); ?></p>
					</td>
				</tr>
				<?php
				/**
				 * Fires in the create Application Passwords form.
				 *
				 * @since 5.6.0
				 *
				 * @param WP_User $profileuser The current WP_User object.
				 */
				do_action( 'wp_create_application_password_form', $profileuser );
				?>

				<tr><td><button type="button" name="do_new_application_password" id="do_new_application_password" class="button button-secondary"><?php _e( 'Add New Application Password' ); ?></button></td></tr>
			</table>
		<?php } else { ?>
			<div class="notice notice-error inline">
				<p><?php _e( 'Your website appears to use Basic Authentication, which is not currently compatible with Application Passwords.' ); ?></p>
			</div>
		<?php } ?>

		<div class="application-passwords-list-table-wrapper">
			<?php
			$application_passwords_list_table = _get_list_table( 'WP_Application_Passwords_List_Table', array( 'screen' => 'application-passwords-user' ) );
			$application_passwords_list_table->prepare_items();
			$application_passwords_list_table->display();
			?>
		</div>
		<?php elseif ( ! wp_is_application_passwords_supported() ) : ?>
			<p><?php _e( 'The application password feature requires HTTPS, which is not enabled on this site.' ); ?></p>
			<p>
				<?php
				printf(
					/* translators: %s: Documentation URL. */
					__( 'If this is a development website you can <a href="%s" target="_blank">set the environment type accordingly</a> to enable application passwords.' ),
					__( 'https://wordpress.org/support/article/editing-wp-config-php/#wp_environment_type' )
				);
				?>
			</p>
		<?php endif; ?>
	</div>
<?php endif; // phpcs:enable Generic.WhiteSpace.ScopeIndent ?>

		<?php
		if ( IS_PROFILE_PAGE ) {
			/**
			 * Fires after the 'About Yourself' settings table on the 'Profile' editing screen.
			 *
			 * The action only fires if the current user is editing their own profile.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_User $profileuser The current WP_User object.
			 */
			do_action( 'show_user_profile', $profileuser );
		} else {
			/**
			 * Fires after the 'About the User' settings table on the 'Edit User' screen.
			 *
			 * @since 2.0.0
			 *
			 * @param WP_User $profileuser The current WP_User object.
			 */
			do_action( 'edit_user_profile', $profileuser );
		}
		?>

		<?php
		/**
		 * Filters whether to display additional capabilities for the user.
		 *
		 * The 'Additional Capabilities' section will only be enabled if
		 * the number of the user's capabilities exceeds their number of
		 * roles.
		 *
		 * @since 2.8.0
		 *
		 * @param bool    $enable      Whether to display the capabilities. Default true.
		 * @param WP_User $profileuser The current WP_User object.
		 */
		if ( count( $profileuser->caps ) > count( $profileuser->roles )
		&& apply_filters( 'additional_capabilities_display', true, $profileuser )
		) :
			?>
	<h2><?php _e( 'Additional Capabilities' ); ?></h2>
<table class="form-table" role="presentation">
<tr class="user-capabilities-wrap">
	<th scope="row"><?php _e( 'Capabilities' ); ?></th>
	<td>
			<?php
			$output = '';
			foreach ( $profileuser->caps as $cap => $value ) {
				if ( ! $wp_roles->is_role( $cap ) ) {
					if ( '' != $output ) {
						$output .= ', ';
					}

					if ( $value ) {
						$output .= $cap;
					} else {
						/* translators: %s: Capability name. */
						$output .= sprintf( __( 'Denied: %s' ), $cap );
					}
				}
			}
			echo $output;
			?>
	</td>
</tr>
</table>
	<?php endif; ?>

<input type="hidden" name="action" value="update" />
<input type="hidden" name="user_id" id="user_id" value="<?php echo esc_attr( $user_id ); ?>" />

		<?php submit_button( IS_PROFILE_PAGE ? __( 'Update Profile' ) : __( 'Update User' ) ); ?>

</form>
</div>
		<?php
		break;
}
?>
<script type="text/javascript">
	if (window.location.hash == '#password') {
		document.getElementById('pass1').focus();
	}
</script>

<?php if ( isset( $application_passwords_list_table ) ) : ?>
	<script type="text/html" id="tmpl-new-application-password">
		<div class="notice notice-success is-dismissible new-application-password-notice" role="alert" tabindex="-1">
			<p class="application-login-display">
				<label for="new-application-login-value">
					<?php
					printf(
						/* translators: %s: Application name. */
						__( 'Your user name for %s is:' ),
						'<strong>{{ data.name }}</strong>'
					);
					?>
				</label>
				<input id="new-application-login-value" type="text" class="code" readonly="readonly" value="{{ data.login }}" />
			</p>
			<p class="application-password-display">
				<label for="new-application-password-value">
					<?php
					printf(
						/* translators: %s: Application name. */
						__( 'Your new password for %s is:' ),
						'<strong>{{ data.name }}</strong>'
					);
					?>
				</label>
				<input id="new-application-password-value" type="text" class="code" readonly="readonly" value="{{ data.password }}" />
			</p>
			<p><?php _e( 'Be sure to save this in a safe location. You will not be able to retrieve it.' ); ?></p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text"><?php _e( 'Dismiss this notice.' ); ?></span>
			</button>
		</div>
	</script>

	<script type="text/html" id="tmpl-application-password-row">
		<?php $application_passwords_list_table->print_js_template_row(); ?>
	</script>
<?php endif; ?>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
