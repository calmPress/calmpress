<?php
/**
 * Edit user administration panel.
 *
 * @package WordPress
 * @subpackage Administration
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

/** WordPress Translation Installation API */
require_once ABSPATH . 'wp-admin/includes/translation-install.php';

$action          = ! empty( $_REQUEST['action'] ) ? sanitize_text_field( $_REQUEST['action'] ) : '';
$user_id         = ! empty( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;
$wp_http_referer = ! empty( $_REQUEST['wp_http_referer'] ) ? sanitize_url( $_REQUEST['wp_http_referer'] ) : '';

$current_user = wp_get_current_user();
if ( ! $user_id ) {
	$user_id = $current_user->ID;
}

if ( ! get_userdata( $user_id ) ) {
	wp_die( __( 'Invalid user ID.' ) );
}

// Define IS_PROFILE_PAGE to reflect if the user is editing his own profile.
// Checking if its already define for backword compatibility with profile.php.
if ( ! defined( 'IS_PROFILE_PAGE' ) ) {
	define( 'IS_PROFILE_PAGE', ( $user_id === $current_user->ID ) );
}

wp_enqueue_media();
wp_enqueue_script( 'user-profile' );

if ( wp_is_application_passwords_available_for_user( $user_id ) ) {
	wp_enqueue_script( 'application-passwords' );
}

if ( IS_PROFILE_PAGE ) {
	// Used in the HTML title tag.
	$title = __( 'My account' );
} else {
	// Used in the HTML title tag.
	/* translators: %s: User's display name. */
	$title = __( 'Edit User Account %s' );
}

if ( ! IS_PROFILE_PAGE ) {
	$parent_file = 'edited-user';
} else {
	$parent_file = 'my-profile';
}

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
	&& $user_id !== $current_user->ID
	&& ! apply_filters( 'enable_edit_any_user_configuration', true )
) {
	wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
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
		if ( is_multisite() && is_network_admin()
			&& ! IS_PROFILE_PAGE && current_user_can( 'manage_network_options' )
			&& ! isset( $super_admins ) && empty( $_POST['super_admin'] ) === is_super_admin( $user_id )
		) {
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
		$profile_user = get_user_to_edit( $user_id );

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
		}

		$title    = sprintf( $title, $profile_user->display_name );
		$sessions = WP_Session_Tokens::get_instance( $profile_user->ID );

		require_once ABSPATH . 'wp-admin/admin-header.php';

		if ( ! IS_PROFILE_PAGE && is_super_admin( $profile_user->ID ) && current_user_can( 'manage_network_options' ) ) :
			$message = '<strong>' . __( 'Important:' ) . '</strong> ' . __( 'This user has super admin privileges.' );
			wp_admin_notice(
				$message,
				array(
					'type' => 'info',
				)
			);
		endif;

		if ( in_array( 'pending_activation', $profile_user->roles, true ) ) {
			$message = __( 'This user account is pending activation. The user has not yet logged in and should request a temporary password from the login page and log in to activate the account.' );
			wp_admin_notice(
				$message,
				array(
					'type' => 'info',
				)
			);
		}

		if ( isset( $_GET['updated'] ) ) :
			if ( IS_PROFILE_PAGE ) :
				$message = '<p><strong>' . __( 'Profile updated.' ) . '</strong></p>';
			else :
				$message = '<p><strong>' . __( 'User updated.' ) . '</strong></p>';
			endif;
			if ( $wp_http_referer && ! str_contains( $wp_http_referer, 'user-new.php' ) && ! IS_PROFILE_PAGE ) :
				$message .= sprintf(
					'<p><a href="%1$s">%2$s</a></p>',
					esc_url( wp_validate_redirect( sanitize_url( $wp_http_referer ), self_admin_url( 'users.php' ) ) ),
					__( '&larr; Go to Users' )
				);
			endif;
			wp_admin_notice(
				$message,
				array(
					'id'                 => 'message',
					'dismissible'        => true,
					'additional_classes' => array( 'updated' ),
					'paragraph_wrap'     => false,
				)
			);
		endif;

		if ( isset( $errors ) && is_wp_error( $errors ) ) {
			wp_admin_notice(
				implode( "</p>\n<p>", $errors->get_error_messages() ),
				array(
					'additional_classes' => array( 'error' ),
				)
			);
		}
		?>

		<div class="wrap" id="profile-page">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $title ); ?>
			</h1>

			<?php if ( ! IS_PROFILE_PAGE ) : ?>
				<?php if ( current_user_can( 'create_users' ) ) : ?>
					<a href="user-new.php" class="page-title-action"><?php echo esc_html__( 'Add User' ); ?></a>
				<?php elseif ( is_multisite() && current_user_can( 'promote_users' ) ) : ?>
					<a href="user-new.php" class="page-title-action"><?php echo esc_html__( 'Add Existing User' ); ?></a>
				<?php endif; ?>
			<?php endif; ?>

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

				<h2><?php esc_html_e( 'Account Management' ); ?></h2>

				<table class="form-table" role="presentation">
					<tr class="user-email-wrap">
						<?php
							$email_readonly = $profile_user->email_change_in_progress() ? 'readonly="readonly"' : '';
							$hide_description = false;
						?>
						<th><label for="email"><?php _e( 'Email' ); ?> <span class="description"><?php _e( '(required)' ); ?></span></label></th>
						<td><input type="email" name="email" id="email" aria-describedby="email-description" <?php echo $email_readonly;?> value="<?php echo esc_attr( $profile_user->user_email ); ?>" class="regular-text ltr" />
						<?php
							// If its the installer's user and he did not verify his email address yet
							// indicate it.
							if ( get_user_meta( $profile_user->ID, 'installer_verify_email', true ) ) {
								$hide_description = true;
								?>
								<div class="notice inline">
									<p>
										<?php
										esc_html_e( 'Was not verified yet. It should be verified to be sure that system emails will be delivered. You can change the email address and then the verification will need to be done for the new address.' );
										if ( current_user_can( 'manage_options' ) ) {
										?>
										<br>
										<?php
											printf(
												/* translators: 1: Openning link to email delivery settings page, 2: Closing </a> */
												esc_html__( 'Make sure you have properly configured your %1$semail delivery settings%2$s first.' ),
												'<a href="' . esc_url( admin_url( 'options-email.php' ) ) . '">',
												'</a>'
											);
										}
										?>
									</p>
								</div>
								<?php \calmpress\utils\html_for_dissmissable_admin_notice( 'verify-installer-notice' );?>
								<div>
									<button id="verify-installer" class="button" type="button">
										<?php esc_html_e( 'Send verification email' );?>
									</button>
								</div>
								<?php
							} elseif ( $profile_user->email_change_in_progress() ) {
								$hide_description = true;
								?>
								<div class="notice inline">
									<p>
										<?php
											try {
												// will throw if new email was already approved.
												$new_email = $profile_user->changed_email_into()->address;
												/* translators: %s: The email address being changed to. */
												printf(
													esc_html__( 'The email address is being changed and the new address of %s was not verified yet.' ),
													'<code>' . esc_html( $new_email ) . '</code>'
												);
											} catch ( \RuntimeException $e ) {
												// new email was set but undo still possible.
												/* translators: %s: The email address to which the undo link was sent. */
												printf(
													esc_html__( 'The email address was changed, but the owner of the %s email address can undo the change.' ),
													'<code>' . esc_html( $profile_user->changed_email_from()->address ) . '</code>'
												);
											}
										?>
									</p>
									<?php \calmpress\utils\html_for_dissmissable_admin_notice( 'cancel-email-change-notice' );?>
									<p>
										<button type="button" class="button button-secondary" id="cancel-email-change">
											<?php esc_html_e( 'Cancle email change' ); ?>
										</button>
									</p>
								</div>
								<?php
							}
							// Do not show description for pending users as no email will be sent.
							if ( in_array( 'pending_activation', $profile_user->roles, true ) ) {
								$hide_description = true;
							}
							?>
							<p class="description" <?php if ( $hide_description ) echo 'style="display:none"';?>>
								<?php _e( 'If you change the email address, we will send an email to the new address to confirm the email. The new address will not become active until confirmed. An email will be sent to the old address with instructions how to undo the change.' ); ?>
							</p>
							<?php
							
						?>
						</td>
					</tr>
					<?php
					/**
					 * Filters the display of the password fields.
					 *
					 * @since 1.5.1
					 * @since 2.8.0 Added the `$profile_user` parameter.
					 * @since 4.4.0 Now evaluated only in user-edit.php.
					 *
					 * @param bool    $show         Whether to show the password fields. Default true.
					 * @param WP_User $profile_user User object for the current user to edit.
					 */
					$show_password_fields = apply_filters( 'show_password_fields', true, $profile_user );
					?>
					<?php if ( $show_password_fields ) : ?>
						<tr id="password" class="user-pass1-wrap">
							<th><label for="pass1"><?php _e( 'New Password' ); ?></label></th>
							<td>
								<input type="hidden" value=" " /><!-- #24364 workaround -->
								<button type="button" class="button wp-generate-pw hide-if-no-js" aria-expanded="false"><?php _e( 'Set New Password' ); ?></button>
								<div class="wp-pwd hide-if-js">
									<div class="password-input-wrapper">
										<input type="password" name="pass1" id="pass1" class="regular-text" value="" autocomplete="new-password" spellcheck="false" data-pw="<?php echo esc_attr( wp_generate_password( 24 ) ); ?>" aria-describedby="pass-strength-result" />
										<div style="display:none" id="pass-strength-result" aria-live="polite"></div>
									</div>
									<button type="button" class="button wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
										<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
										<span class="text"><?php _e( 'Hide' ); ?></span>
									</button>
									<button type="button" class="button wp-cancel-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Cancel password change' ); ?>">
										<span class="dashicons dashicons-no" aria-hidden="true"></span>
										<span class="text"><?php _e( 'Cancel' ); ?></span>
									</button>
								</div>
							</td>
						</tr>
						<tr class="user-pass2-wrap hide-if-js">
							<th scope="row"><label for="pass2"><?php _e( 'Repeat New Password' ); ?></label></th>
							<td>
							<input type="password" name="pass2" id="pass2" class="regular-text" value="" autocomplete="new-password" spellcheck="false" aria-describedby="pass2-desc" />
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
					<?php endif; // End Show Password Fields. ?>

					<?php if ( IS_PROFILE_PAGE && count( $sessions->get_all() ) === 1 ) : ?>
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
									printf( __( 'Log %s out of all locations.' ), $profile_user->display_name );
									?>
								</p>
							</td>
						</tr>
					<?php endif; ?>
					<tr id="qrlink">
						<th><?php esc_html_e( 'Direct Login' ); ?></th>
						<td>
							<?php \calmpress\utils\html_for_dissmissable_admin_notice( 'qr_message' );?>
							<p><button type="button" class="button" id="show-qrlink"><?php esc_html_e( 'Generate QR Code' ); ?></button></p>
							<p id="qrdesription" class="description">
								<?php
								esc_html_e( 'Log in on another device using a short-lived QR code.' );
								?>
							</p>
							<div id="qr_section" style="display:none">
								<p>
									<img id="qr_image" width="200" alt="<?php esc_attr_e( 'QR code' );?>">
								</p>
								<p>
									<?php
									esc_html_e( 'Scan this code with your phone to log in. It will expire in few minutes.' );
									?>
								</p>
							</div>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Administration Interface Options' ); ?></h2>
				<table class="form-table" role="presentation">

					<?php if ( count( $_wp_admin_css_colors ) > 1 && has_action( 'admin_color_scheme_picker' ) ) : ?>
					<tr class="user-admin-color-wrap">
						<th scope="row"><?php _e( 'Administration Color Scheme' ); ?></th>
						<td>
							<?php
							/**
							 * Fires in the 'Administration Color Scheme' section of the user editing screen.
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
								<input name="admin_bar_front" type="checkbox" id="admin_bar_front" value="1"<?php checked( _get_admin_bar_pref( 'front', $profile_user->ID ) ); ?> />
								<?php _e( 'Show Toolbar when viewing site' ); ?>
							</label><br />
						</td>
					</tr>

					<?php
					$languages                = get_available_languages();
					$can_install_translations = current_user_can( 'install_languages' ) && wp_can_install_language_pack();
					?>
					<?php if ( $languages || $can_install_translations ) : ?>
					<tr class="user-language-wrap">
						<th scope="row">
							<?php /* translators: The user language selection field label. */ ?>
							<label for="locale"><?php _e( 'Language' ); ?><span class="dashicons dashicons-translation" aria-hidden="true"></span></label>
						</th>
						<td>
							<?php
								$user_locale = $profile_user->locale;

							if ( 'en_US' === $user_locale ) {
								$user_locale = '';
							} elseif ( '' === $user_locale || ! in_array( $user_locale, $languages, true ) ) {
								$user_locale = 'site-default';
							}

							wp_dropdown_languages(
								array(
									'name'      => 'locale',
									'id'        => 'locale',
									'selected'  => $user_locale,
									'languages' => $languages,
									'show_available_translations' => $can_install_translations,
									'show_option_site_default' => true,
								)
							);
							?>
						</td>
					</tr>
					<?php endif; ?>
					<?php if ( ! IS_PROFILE_PAGE && ! is_network_admin() && current_user_can( 'promote_user', $profile_user->ID ) ) {
						$activation_label = '';
						$roles            = $profile_user->roles;
						if ( in_array( 'pending_activation', $profile_user->roles, true ) ) {
							$activation_label = ' ' . esc_html__( '(when activated)' );
							$roles            = [ get_user_meta( $profile_user->ID, 'activate_to_role', true ) ];
						}
					?>
					<tr class="user-role-wrap"><th><label for="role"><?php esc_html_e( 'Role' ) . $activation_label; ?></label></th>
						<td>
							<select name="role" id="role">
								<?php
									// Compare user role against currently editable roles.
									$user_roles = array_intersect( array_values( $roles ), array_keys( get_editable_roles() ) );
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
							</select>
						</td>
					</tr>

					<?php
					} // End User roles.

					if ( in_array( 'administrator', $profile_user->roles, true ) ) {
						?>
					<tr id="mock-role-wrap" class="user-mock-role-wrap"><th><label for="mock-role"><?php esc_html_e( 'Behave like the role' ); ?></label></th>
						<td>
							<select name="mock_role" id="mock-role">
								<?php
								$current_behave = $profile_user->mocked_role( );
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
						$expiry = (int) get_user_meta( $profile_user->ID, 'mock_role_expiry', true );
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
						</td>
				</tr>
				<?php
					} // end mock role.
					if ( is_multisite() && is_network_admin() && ! IS_PROFILE_PAGE && ! isset( $super_admins ) ) : ?>
						<tr class="user-super-admin-wrap">
							<th><?php _e( 'Super Admin' ); ?></th>
							<td>
								<p><label><input type="checkbox" id="super_admin" name="super_admin"<?php checked( is_super_admin( $profile_user->ID ) ); ?> /> <?php _e( 'Grant this user super admin privileges for the Network.' ); ?></label></p>
							</td>
						</tr>
					<?php endif; ?>


				</table>

				<h2><?php _e( 'Public Information' ); ?></h2>

				<table class="form-table" role="presentation">

					<tr class="user-display-name-wrap">
						<th><label for="display_name"><?php esc_html_e( 'Display name publicly as' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" name="display_name" id="display_name" value="<?php echo esc_attr( $profile_user->display_name ); ?>">
							<p class="description">
								<?php esc_html_e( 'The name which will be used to identify you in the admin and at public contexts like comments.' ); ?>
							</p>
						</td>
					</tr>
					<tr class="user-description-wrap">
						<th><label for="description"><?php _e( 'Biographical Info' ); ?></label></th>
						<td><textarea name="description" id="description" rows="5" cols="30"><?php echo $profile_user->description; // textarea_escaped ?></textarea>
						<p class="description"><?php _e( 'Some information about yourself.' ); ?></p></td>
					</tr>

					<tr class="user-avatar-image">
						<th><?php esc_html_e( 'Avatar image' ); ?></th>
						<td>
							<?php
							$avatar     = $profile_user->avatar();
							$attachment = $avatar->attachment();
							if ( $attachment ) {
								$attachment_id = $attachment->ID;
								$text_avatar   = new \calmpress\avatar\Text_Based_Avatar( $profile_user->display_name, $profile_user->user_email );
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
											echo $avatar->html( 50 );
										} else {
											echo "<img style='border-radius:50%' src='' alt='' width=50 height=50>";
										}
										?>
									</span>
									<span id="avatar_text_preview"<?php echo $text_display; ?>>
										<?php
										echo $text_avatar->html( 50 );
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
				<?php
				ob_start();

				/**
				 * Fires at the 'Additional Settings' settings table on the user editing screen.
				 *
				 * @since 2.7.0
				 *
				 * @param WP_User $profile_user The current WP_User object.
				 */
				do_action( 'personal_options', $profile_user );

				if ( IS_PROFILE_PAGE ) {
					/**
					 * Fires at the 'Additional Settings' settings table on the 'Profile' editing screen.
					 *
					 * The action only fires if the current user is editing their own profile.
					 *
					 * @since 2.0.0
					 *
					 * @param WP_User $profile_user The current WP_User object.
					 */
					do_action( 'profile_personal_options', $profile_user );
				}

				$content = trim( ob_get_clean() );

				if ( $content !== '' ) {
					echo '<h2>' . esc_html__( 'Additional settings' ) . '</h2>';
					echo '<table class="form-table" role="presentation">';
					echo $content;
					echo '</table>';
				}
				
				if ( wp_is_application_passwords_available_for_user( $user_id ) || ! wp_is_application_passwords_supported() ) : ?>
					<div class="application-passwords hide-if-no-js" id="application-passwords-section">
						<h2><?php _e( 'Application Passwords' ); ?></h2>
						<p><?php _e( 'Application passwords allow authentication via non-interactive systems, such as XML-RPC or the REST API, without providing your actual password. Application passwords can be easily revoked. They cannot be used for traditional logins to your website.' ); ?></p>
						<?php if ( wp_is_application_passwords_available_for_user( $user_id ) ) : ?>
							<?php
							if ( is_multisite() ) :
								$blogs       = get_blogs_of_user( $user_id, true );
								$blogs_count = count( $blogs );

								if ( $blogs_count > 1 ) :
									?>
									<p>
										<?php
										/* translators: 1: URL to my-sites.php, 2: Number of sites the user has. */
										$message = _n(
											'Application passwords grant access to <a href="%1$s">the %2$s site in this installation that you have permissions on</a>.',
											'Application passwords grant access to <a href="%1$s">all %2$s sites in this installation that you have permissions on</a>.',
											$blogs_count
										);

										if ( is_super_admin( $user_id ) ) {
											/* translators: 1: URL to my-sites.php, 2: Number of sites the user has. */
											$message = _n(
												'Application passwords grant access to <a href="%1$s">the %2$s site on the network as you have Super Admin rights</a>.',
												'Application passwords grant access to <a href="%1$s">all %2$s sites on the network as you have Super Admin rights</a>.',
												$blogs_count
											);
										}

										printf(
											$message,
											admin_url( 'my-sites.php' ),
											number_format_i18n( $blogs_count )
										);
										?>
									</p>
									<?php
								endif;
							endif;
							?>

							<?php if ( ! wp_is_site_protected_by_basic_auth( 'front' ) ) : ?>
								<div class="create-application-password form-wrap">
									<div class="form-field">
										<label for="new_application_password_name"><?php _e( 'New Application Password Name' ); ?></label>
										<input type="text" size="30" id="new_application_password_name" name="new_application_password_name" class="input" aria-required="true" aria-describedby="new_application_password_name_desc" spellcheck="false" />
										<p class="description" id="new_application_password_name_desc"><?php _e( 'Required to create an Application Password, but not to update the user.' ); ?></p>
									</div>

									<?php
									/**
									 * Fires in the create Application Passwords form.
									 *
									 * @since 5.6.0
									 *
									 * @param WP_User $profile_user The current WP_User object.
									 */
									do_action( 'wp_create_application_password_form', $profile_user );
									?>

									<button type="button" name="do_new_application_password" id="do_new_application_password" class="button button-secondary"><?php _e( 'Add Application Password' ); ?></button>
								</div>
								<?php
							else :
								wp_admin_notice(
									__( 'Your website appears to use Basic Authentication, which is not currently compatible with Application Passwords.' ),
									array(
										'type' => 'error',
										'additional_classes' => array( 'inline' ),
									)
								);
							endif;
							?>

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
									__( 'If this is a development website, you can <a href="%s">set the environment type accordingly</a> to enable application passwords.' ),
									__( 'https://developer.wordpress.org/apis/wp-config-php/#wp-environment-type' )
								);
								?>
							</p>
						<?php endif; ?>
					</div>
				<?php endif; // End Application Passwords. ?>

				<?php
				if ( IS_PROFILE_PAGE ) {
					/**
					 * Fires after the 'Application Passwords' section is loaded on the 'Profile' editing screen.
					 *
					 * The action only fires if the current user is editing their own profile.
					 *
					 * @since 2.0.0
					 *
					 * @param WP_User $profile_user The current WP_User object.
					 */
					do_action( 'show_user_profile', $profile_user );
				} else {
					/**
					 * Fires after the 'Application Passwords' section is loaded on 'Edit User' screen.
					 *
					 * The action only fires if the current user is editing another user's profile.
					 *
					 * @since 2.0.0
					 *
					 * @param WP_User $profile_user The current WP_User object.
					 */
					do_action( 'edit_user_profile', $profile_user );
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
				 * @param WP_User $profile_user The current WP_User object.
				 */
				$display_additional_caps = apply_filters( 'additional_capabilities_display', true, $profile_user );
				?>

				<?php if ( count( $profile_user->caps ) > count( $profile_user->roles ) && ( true === $display_additional_caps ) ) : ?>
					<h2><?php _e( 'Additional Capabilities' ); ?></h2>

					<table class="form-table" role="presentation">
						<tr class="user-capabilities-wrap">
							<th scope="row"><?php _e( 'Capabilities' ); ?></th>
							<td>
								<?php
								$output = '';
								foreach ( $profile_user->caps as $cap => $value ) {
									if ( ! $wp_roles->is_role( $cap ) ) {
										if ( '' !== $output ) {
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
				<?php endif; // End Display Additional Capabilities. ?>

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

<script type="text/javascript">
	jQuery( function( $ ) {
		var languageSelect = $( '#locale' );
		$( 'form' ).on( 'submit', function() {
			/*
			 * Don't show a spinner for English and installed languages,
			 * as there is nothing to download.
			 */
			if ( ! languageSelect.find( 'option:selected' ).data( 'installed' ) ) {
				$( '#submit', this ).after( '<span class="spinner language-install-spinner is-active" />' );
			}
		});
	} );
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
				<button type="button" class="button copy-button" data-clipboard-text="{{ data.password }}"><?php _e( 'Copy' ); ?></button>
				<span class="success hidden" aria-hidden="true"><?php _e( 'Copied!' ); ?></span>
			</p>
			<p><?php _e( 'Be sure to save this in a safe location. You will not be able to retrieve it.' ); ?></p>
			<button type="button" class="notice-dismiss">
				<span class="screen-reader-text">
					<?php
					/* translators: Hidden accessibility text. */
					_e( 'Dismiss this notice.' );
					?>
				</span>
			</button>
		</div>
	</script>

	<script type="text/html" id="tmpl-application-password-row">
		<?php $application_passwords_list_table->print_js_template_row(); ?>
	</script>
<?php endif; ?>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';

