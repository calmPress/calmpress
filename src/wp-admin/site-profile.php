<?php
/**
 * Site-specific user profile administration screen.
 *
 * @package CalmPress
 * @subpackage Administration
 * @since calmPress 1.0.0
 */

/** Load WordPress Administration Bootstrap. */
require_once __DIR__ . '/admin.php';

if ( ! is_multisite() ) {
	wp_die( 'Site profiles are available only for sites in a network.' );
}

$current_user             = wp_get_current_user();
$user                     = $current_user;
$site                     = calmpress\site\Site::current();
$errors                   = new WP_Error();

// Edit the active user by default, or verify that the active user can edit the requested user.
if ( isset( $_GET['user_id'] ) ) {
	if ( ! is_string( $_GET['user_id'] ) ) {
		wp_die( 'Invalid user ID.', '', [ 'response' => 400 ] );
	}

	$user_id = filter_var( wp_unslash( $_GET['user_id'] ), FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	if ( false === $user_id ) {
		wp_die( 'Invalid user ID.', '', [ 'response' => 400 ] );
	}

	$user = get_userdata( $user_id );
	if ( ! $user || ! $user->can_login() ) {
		wp_die( 'Invalid user ID.', '', [ 'response' => 400 ] );
	}

	if ( $user->ID !== $current_user->ID && ! current_user_can( 'promote_user', $user->ID ) ) {
		wp_die( 'You are not allowed to edit this user.', '', [ 'response' => 403 ] );
	}
}

$is_profile_page          = $user->ID === $current_user->ID;
$can_upload_avatar_images = current_user_can( 'upload_files' );
$can_manage_role          = ! $is_profile_page && current_user_can( 'promote_user', $user->ID );
$can_change_role          = $can_manage_role && ! $user->is_system_notification_recipient( $site );
$is_pending_activation    = in_array( 'pending_activation', $user->roles, true );

if ( ! is_user_member_of_blog( $user->ID, (int) $site->blog_id ) ) {
	wp_die( 'The user is not a member of this site.', '', [ 'response' => 403 ] );
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	check_admin_referer( 'update-site-profile_' . $user->ID );

	// Save the values which control how the current user appears and works on this site.
	if ( ! isset( $_POST['display_name'] )
		|| ! is_string( $_POST['display_name'] )
		|| ! isset( $_POST['avatar_source'] )
		|| ! is_string( $_POST['avatar_source'] )
		|| $can_upload_avatar_images && ! isset( $_POST['calm_avatar_image_attachement_id'] )
		|| $can_change_role && ! isset( $_POST['role'] )
	) {
		wp_die( 'The site profile form is incomplete.' );
	}

	$display_name = trim( wp_unslash( $_POST['display_name'] ) );
	$avatar_source = wp_unslash( $_POST['avatar_source'] );
	$attachment    = null;

	if ( isset( $_POST['override_display_name'] ) ) {
		if ( '' === $display_name ) {
			$errors->add( 'display_name', __( 'You have to specify a display name.' ) );
		}
	}

	if ( isset( $_POST['override_display_name'] ) && 'account' === $avatar_source ) {
		$errors->add( 'avatar', __( 'The account avatar cannot be used with a site-specific display name.' ) );
	}

	if ( 'image' === $avatar_source ) {
		if ( $can_upload_avatar_images ) {
			$avatar_attachment_id = filter_var( wp_unslash( $_POST['calm_avatar_image_attachement_id'] ), FILTER_VALIDATE_INT );
			if ( false === $avatar_attachment_id || 0 >= $avatar_attachment_id ) {
				$errors->add( 'avatar', __( 'No avatar image was selected.' ) );
			} else {
				$attachment = get_post( $avatar_attachment_id );
				if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment ) ) {
					wp_die( 'The selected avatar is not an image attachment from this site.' );
				}
			}
		} else {
			$attachment = $user->has_avatar_override_for_site( $site )
				? $user->avatar_for_site( $site )->attachment()
				: null;
			if ( ! $attachment ) {
				wp_die( 'The selected avatar source is invalid.', '', [ 'response' => 400 ] );
			}
		}
	}

	// Do not save any site-profile values when validation of any field failed.
	if ( ! $errors->has_errors() ) {

		// Change an active user's role immediately, but preserve the intended role until a pending user activates.
		if ( $can_change_role ) {
			if ( ! isset( $_POST['role'] ) || ! is_string( $_POST['role'] ) ) {
				wp_die( 'The selected role is invalid.', '', [ 'response' => 400 ] );
			}

			$role = wp_unslash( $_POST['role'] );
			if ( ! isset( get_editable_roles()[ $role ] ) ) {
				wp_die( 'The selected role is invalid.', '', [ 'response' => 400 ] );
			}

			if ( $is_pending_activation ) {
				$user->set_role_after_activation( $role );
			} else {
				$user->set_role( $role );
			}
		}

		// Store a site-specific display name only when the form explicitly enables the override.
		if ( isset( $_POST['override_display_name'] ) ) {
			$user->set_display_name_for_site( $site, $display_name );
		} else {
			$user->remove_display_name_for_site( $site );
		}

		// Any user may remove an image override, while selecting a new image requires upload permission.
		if ( 'image' === $avatar_source ) {
			if ( $can_upload_avatar_images ) {
				$user->set_avatar_for_site( $site, $attachment );
			}
		} elseif ( 'generated' === $avatar_source ) {
			$user->set_generated_avatar_for_site( $site );
		} elseif ( 'account' === $avatar_source ) {
			$user->remove_avatar_for_site( $site );
		} else {
			wp_die( 'The selected avatar source is invalid.', '', [ 'response' => 400 ] );
		}
	}

	// Only users editing their own profile may change how their account temporarily behaves.
	if ( ! $errors->has_errors() && $is_profile_page && isset( $_POST['mock_role'] ) ) {
		if ( ! is_string( $_POST['mock_role'] ) ) {
			wp_die( 'The selected temporary role is invalid.', '', [ 'response' => 400 ] );
		}

		$mocked_role = wp_unslash( $_POST['mock_role'] );
		if ( array_intersect( [ 'administrator', 'editor' ], $user->roles ) ) {
			$allowed_roles = in_array( 'administrator', $user->roles, true ) ? [ '', 'editor', 'author' ] : [ '', 'author' ];
			if ( ! in_array( $mocked_role, $allowed_roles, true ) ) {
				wp_die( 'The selected temporary role is invalid.', '', [ 'response' => 400 ] );
			}
		} else {

			// Remove temporary behavior when the newly assigned role cannot use it.
			$mocked_role = '';
		}

		$user->set_mocked_role( $mocked_role );
	}

	// Redirect only after every requested change was saved, preserving the edited user when it is not the active user.
	if ( ! $errors->has_errors() ) {
		$redirect_arguments = [ 'updated' => '1' ];
		if ( ! $is_profile_page ) {
			$redirect_arguments['user_id'] = $user->ID;
		}
		wp_safe_redirect( add_query_arg( $redirect_arguments, admin_url( 'site-profile.php' ) ) );
		exit;
	}
}

if ( $can_upload_avatar_images ) {
	wp_enqueue_media();
}
wp_enqueue_script( 'site-profile' );
wp_localize_script(
	'site-profile',
	'site_profile_settings',
	[
		'can_upload_avatar_images' => $can_upload_avatar_images,
	]
);

$title                     = $is_profile_page
	? __( 'Profile for This Site' )
	: sprintf(
		/* translators: %s: User display name. */
		__( 'Profile for %s on This Site' ),
		$user->display_name
	);
$has_display_name_override = $user->has_display_name_override_for_site( $site );
$display_name              = $user->display_name_for_site( $site );
$has_avatar_override       = $user->has_avatar_override_for_site( $site );
$account_avatar            = $user->account_avatar();
$avatar                    = $user->avatar_for_site( $site );
$attachment                = $has_avatar_override ? $avatar->attachment() : null;
$attachment_id             = $attachment ? $attachment->ID : 0;
$avatar_source             = ! $has_avatar_override ? 'account' : ( $attachment ? 'image' : 'generated' );
$avatar_source             = $has_display_name_override && 'account' === $avatar_source ? 'generated' : $avatar_source;
$generated_avatar          = new calmpress\avatar\Text_Based_Avatar( $display_name, $user->user_email );
$current_mocked_role       = $user->mocked_role();
$assigned_roles            = $user->roles;
$assigned_role             = $is_pending_activation ? $user->role_after_activation() : reset( $assigned_roles );
$parent_file               = $is_profile_page ? 'my-profile' : 'edited-user';
$submenu_file              = $is_profile_page ? null : 'site-profile.php?user_id=' . $user->ID;

if ( $errors->get_error_messages( 'display_name' ) ) {
	$has_display_name_override = true;
	$display_name              = trim( wp_unslash( $_POST['display_name'] ) );
}
if ( $errors->get_error_messages( 'avatar' ) ) {
	$avatar_source = wp_unslash( $_POST['avatar_source'] );
}

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap" id="site-profile-page">
	<h1><?php echo esc_html( $title ); ?></h1>
	<p><?php echo esc_html( $is_profile_page ? __( 'Customize how your account appears and behaves on this site.' ) : __( 'Customize how this account appears and behaves on this site.' ) ); ?></p>
	<p><?php echo esc_html( $is_profile_page ? __( 'Your display name and avatar are taken from your main account profile unless you choose site-specific values below.' ) : __( 'The display name and avatar are taken from the main account profile unless you choose site-specific values below.' ) ); ?></p>

	<?php
	if ( $errors->has_errors() ) {
		wp_admin_notice(
			'<p>' . implode( "</p>\n<p>", array_map( 'esc_html', $errors->get_error_messages() ) ) . '</p>',
			[
				'type'           => 'error',
				'dismissible'    => true,
				'paragraph_wrap' => false,
			]
		);
	} elseif ( isset( $_GET['updated'] ) ) {
		wp_admin_notice(
			esc_html__( 'Site profile updated.' ),
			[
				'type'        => 'success',
				'dismissible' => true,
			]
		);
	}
	?>

	<form method="post" class="calm-validate" action="<?php echo esc_url( add_query_arg( $is_profile_page ? [] : [ 'user_id' => $user->ID ], admin_url( 'site-profile.php' ) ) ); ?>" novalidate>
		<?php wp_nonce_field( 'update-site-profile_' . $user->ID ); ?>
		<input type="hidden" id="calm_avatar_image_attachement_id" name="calm_avatar_image_attachement_id" value="<?php echo esc_attr( $attachment_id ); ?>">

		<table class="form-table" role="presentation">
			<?php if ( $can_manage_role ) { ?>
			<tr>
				<th><label for="role"><?php esc_html_e( 'Role' ); ?></label></th>
				<td>
					<?php if ( $can_change_role ) { ?>
					<select name="role" id="role">
						<?php wp_dropdown_roles( $assigned_role ); ?>
					</select>
					<?php } else { ?>
						<?php echo esc_html( translate_user_role( ucfirst( $assigned_role ) ) ); ?>
						<p class="description"><?php esc_html_e( 'The user receiving system notifications cannot be demoted.' ); ?></p>
					<?php } ?>
				</td>
			</tr>
			<?php } ?>
			<?php if ( $is_profile_page && array_intersect( [ 'administrator', 'editor' ], $user->roles ) ) : ?>
				<tr id="mock-role-wrap">
					<th><label for="mock-role"><?php esc_html_e( 'Behave as' ); ?></label></th>
					<td>
						<?php
						$assigned_role = in_array( 'administrator', $user->roles, true ) ? 'Administrator' : 'Editor';

						/* translators: %s: The user's assigned role. */
						$assigned_role_label = sprintf( __( 'Use my assigned role (%s)' ), translate_user_role( $assigned_role ) );
						?>
						<select name="mock_role" id="mock-role">
							<option value="" <?php selected( '', $current_mocked_role ); ?>><?php echo esc_html( $assigned_role_label ); ?></option>
							<?php if ( in_array( 'administrator', $user->roles, true ) ) : ?>
								<option value="editor" <?php selected( 'editor', $current_mocked_role ); ?>><?php echo esc_html( translate_user_role( 'Editor' ) ); ?></option>
							<?php endif; ?>
							<option value="author" <?php selected( 'author', $current_mocked_role ); ?>><?php echo esc_html( translate_user_role( 'Author' ) ); ?></option>
						</select>
						<?php
						$expiry = (int) get_user_option( WP_User::SITE_MOCKED_ROLE_EXPIRY_OPTION, $user->ID );
						if ( '' !== $current_mocked_role && $expiry > time() ) {

							/* translators: %s: Time until behavior expires. */
							$remaining_time = sprintf( __( 'The lower-role behavior will last for another %s.' ), human_time_diff( $expiry, time() ) );
							echo '<p class="description">' . esc_html( $remaining_time ) . '</p>';
						}
						?>
						<p class="description"><?php esc_html_e( 'Choosing a lower role temporarily uses its permissions without changing your assigned role.' ); ?></p>
						<p class="description"><?php esc_html_e( 'This can reduce administration clutter when the account is used for editing content.' ); ?></p>
						<p class="description"><?php esc_html_e( 'The lower-role behavior ends after 14 days or when you select your assigned role.' ); ?></p>
						<p class="description"><?php esc_html_e( 'This setting affects only this site.' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
			<tr>
				<th><label for="display_name"><?php esc_html_e( 'Display name' ); ?></label></th>
				<td>
					<input type="checkbox" name="override_display_name" id="override_display_name" value="1" <?php checked( $has_display_name_override ); ?>><label for="override_display_name"><?php esc_html_e( 'Use a site-specific display name' ); ?></label>
					<div class="site-profile-field">
						<input type="text" class="regular-text<?php echo $errors->get_error_messages( 'display_name' ) ? ' validation-warning' : ''; ?>" name="display_name" id="display_name" value="<?php echo esc_attr( $display_name ); ?>" data-account-value="<?php echo esc_attr( $user->account_display_name() ); ?>" data-site-value="<?php echo esc_attr( $display_name ); ?>" data-text-based-avatar-preview="generated_avatar_preview" data-text-based-avatar-color-factor="<?php echo esc_attr( $user->user_email ); ?>" validate="pattern" validate-on="input" validation-pattern="\S" validation-failue-class="validation-warning" aria-describedby="display_name_error display_name_description" <?php wp_readonly( ! $has_display_name_override ); ?>>
						<p class="validate-failure-message" id="display_name_error" aria-live="polite"><?php esc_html_e( 'You have to specify a display name.' ); ?></p>
						<p class="description" id="display_name_description"><?php esc_html_e( 'Otherwise, the display name from your main account profile is used on this site.' ); ?></p>
					</div>
				</td>
			</tr>
			<tr>
				<th><label for="avatar_source"><?php esc_html_e( 'Avatar on this site' ); ?></label></th>
				<td>
					<select name="avatar_source" id="avatar_source">
						<option value="account" <?php selected( $avatar_source, 'account' ); ?> <?php disabled( $has_display_name_override ); ?>><?php esc_html_e( 'Use account avatar' ); ?></option>
						<option value="generated" <?php selected( $avatar_source, 'generated' ); ?>><?php esc_html_e( 'Generate from site display name' ); ?></option>
						<?php if ( $can_upload_avatar_images || $attachment ) { ?>
							<option value="image" <?php selected( $avatar_source, 'image' ); ?>><?php esc_html_e( 'Use a site-specific image' ); ?></option>
						<?php } ?>
					</select>
					<div id="calm_avatar_container">
						<div id="account_avatar_preview"<?php echo 'account' === $avatar_source ? '' : ' hidden'; ?>>
							<p><?php echo $account_avatar->html( 50 ); ?></p>
						</div>
						<div id="generated_avatar_preview"<?php echo 'generated' === $avatar_source ? '' : ' hidden'; ?>>
							<p><?php echo $generated_avatar->html( 50 ); ?></p>
						</div>
						<div id="site_avatar_preview"<?php echo 'image' === $avatar_source ? '' : ' hidden'; ?>>
							<p>
								<span id="avatar_image_preview"<?php echo $attachment ? '' : ' style="display:none"'; ?>>
									<?php echo $attachment ? $avatar->html( 50 ) : '<img class="avatar" src="" alt="" width="50" height="50">'; ?>
								</span>
							</p>
						</div>
					<?php if ( $can_upload_avatar_images ) { ?>
						<button type="button" class="button" id="select_avatar_image" <?php disabled( 'image' !== $avatar_source ); ?>><?php esc_html_e( 'Select Image' ); ?></button>
					<?php } ?>
						<p class="description"><?php esc_html_e( 'Choose whether this site uses your account avatar, a generated avatar, or its own image.' ); ?></p>
					</div>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Update Site Profile' ) ); ?>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
