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

$user                     = wp_get_current_user();
$site                     = calmpress\site\Site::current();
$display_name_error       = '';
$avatar_error             = '';
$can_upload_avatar_images = current_user_can( 'upload_files' );

if ( ! is_user_member_of_blog( $user->ID, (int) $site->blog_id ) ) {
	wp_die( 'The current user is not a member of this site.' );
}

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	check_admin_referer( 'update-site-profile_' . $user->ID );

	// Save the values which control how the current user appears and works on this site.
	if ( ! isset( $_POST['display_name'] )
		|| $can_upload_avatar_images && ! isset( $_POST['avatar_source'], $_POST['calm_avatar_image_attachement_id'] )
	) {
		wp_die( 'The site profile form is incomplete.' );
	}

	if ( isset( $_POST['override_display_name'] ) ) {
		$display_name = trim( wp_unslash( $_POST['display_name'] ) );
		if ( '' === $display_name ) {
			$display_name_error = __( 'Enter a display name to use on this site.' );
		}
	}

	if ( $can_upload_avatar_images ) {
		$avatar_source = wp_unslash( $_POST['avatar_source'] );
		$attachment    = null;
		if ( '' === $display_name_error && isset( $_POST['override_display_name'] ) && 'account' === $avatar_source ) {
			wp_die( 'The account avatar cannot be used with a site-specific display name.' );
		}

		if ( '' === $display_name_error && 'image' === $avatar_source ) {
			$avatar_attachment_id = filter_var( wp_unslash( $_POST['calm_avatar_image_attachement_id'] ), FILTER_VALIDATE_INT );
			if ( false === $avatar_attachment_id || 0 >= $avatar_attachment_id ) {
				$avatar_error = __( 'No avatar image was selected.' );
			} else {
				$attachment = get_post( $avatar_attachment_id );
				if ( ! $attachment instanceof WP_Post || 'attachment' !== $attachment->post_type || ! wp_attachment_is_image( $attachment ) ) {
					wp_die( 'The selected avatar is not an image attachment from this site.' );
				}
			}
		}
	}

	if ( '' === $display_name_error && '' === $avatar_error ) {
		if ( isset( $_POST['override_display_name'] ) ) {
			$user->set_display_name_for_site( $site, $display_name );
		} else {
			$user->remove_display_name_for_site( $site );
		}

		if ( $can_upload_avatar_images ) {
			if ( 'image' === $avatar_source ) {
				$user->set_avatar_for_site( $site, $attachment );
			} elseif ( 'generated' === $avatar_source ) {
				$user->set_generated_avatar_for_site( $site );
			} elseif ( 'account' === $avatar_source ) {
				$user->remove_avatar_for_site( $site );
			} else {
				wp_die( 'The selected avatar source is invalid.' );
			}
		} elseif ( isset( $_POST['override_display_name'] ) ) {
			$user->set_generated_avatar_for_site( $site );
		} else {
			$user->remove_avatar_for_site( $site );
		}
	}

	if ( '' === $display_name_error && '' === $avatar_error && array_intersect( [ 'administrator', 'editor' ], $user->roles ) ) {
		$allowed_roles = in_array( 'administrator', $user->roles, true ) ? [ '', 'editor', 'author' ] : [ '', 'author' ];
		$mocked_role   = isset( $_POST['mock_role'] ) ? wp_unslash( $_POST['mock_role'] ) : null;
		if ( ! in_array( $mocked_role, $allowed_roles, true ) ) {
			wp_die( 'The selected temporary role is invalid.' );
		}

		$user->set_mocked_role( $mocked_role );
	}

	if ( '' === $display_name_error && '' === $avatar_error ) {
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'site-profile.php' ) ) );
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

$title                     = __( 'Profile for This Site' );
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

if ( '' !== $display_name_error ) {
	$has_display_name_override = true;
	$display_name              = trim( wp_unslash( $_POST['display_name'] ) );
} elseif ( '' !== $avatar_error ) {
	$avatar_source = wp_unslash( $_POST['avatar_source'] );
}

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap" id="site-profile-page">
	<h1><?php echo esc_html( $title ); ?></h1>
	<p><?php esc_html_e( 'Customize how your account appears and behaves on this site.' ); ?></p>
	<p><?php esc_html_e( 'Your display name and avatar are taken from your main account profile unless you choose site-specific values below.' ); ?></p>

	<?php
	if ( '' !== $display_name_error ) {
		wp_admin_notice(
			esc_html__( 'You have to specify a display name.' ),
			[
				'type'        => 'error',
				'dismissible' => true,
			]
		);
	} elseif ( '' !== $avatar_error ) {
		wp_admin_notice(
			esc_html( $avatar_error ),
			[
				'type'        => 'error',
				'dismissible' => true,
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

	<form method="post" class="calm-validate" action="<?php echo esc_url( admin_url( 'site-profile.php' ) ); ?>" novalidate>
		<?php wp_nonce_field( 'update-site-profile_' . $user->ID ); ?>
		<input type="hidden" id="calm_avatar_image_attachement_id" name="calm_avatar_image_attachement_id" value="<?php echo esc_attr( $attachment_id ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="display_name"><?php esc_html_e( 'Display name' ); ?></label></th>
				<td>
					<input type="checkbox" name="override_display_name" id="override_display_name" value="1" <?php checked( $has_display_name_override ); ?>><label for="override_display_name"><?php esc_html_e( 'Use a site-specific display name' ); ?></label>
					<div class="site-profile-field">
						<input type="text" class="regular-text<?php echo '' !== $display_name_error ? ' validation-warning' : ''; ?>" name="display_name" id="display_name" value="<?php echo esc_attr( $display_name ); ?>" data-account-value="<?php echo esc_attr( $user->account_display_name() ); ?>" data-site-value="<?php echo esc_attr( $display_name ); ?>"<?php if ( $can_upload_avatar_images ) : ?> data-text-based-avatar-preview="generated_avatar_preview" data-text-based-avatar-color-factor="<?php echo esc_attr( $user->user_email ); ?>"<?php endif; ?> validate="pattern" validate-on="input" validation-pattern="\S" validation-failue-class="validation-warning" aria-describedby="display_name_error display_name_description" <?php wp_readonly( ! $has_display_name_override ); ?>>
						<p class="validate-failure-message" id="display_name_error" aria-live="polite"><?php esc_html_e( 'You have to specify a display name.' ); ?></p>
						<p class="description" id="display_name_description"><?php esc_html_e( 'Otherwise, the display name from your main account profile is used on this site.' ); ?></p>
					</div>
				</td>
			</tr>
			<?php if ( $can_upload_avatar_images ) : ?>
			<tr>
				<th><label for="avatar_source"><?php esc_html_e( 'Avatar on this site' ); ?></label></th>
				<td>
					<select name="avatar_source" id="avatar_source">
						<option value="account" <?php selected( $avatar_source, 'account' ); ?> <?php disabled( $has_display_name_override ); ?>><?php esc_html_e( 'Use account avatar' ); ?></option>
						<option value="generated" <?php selected( $avatar_source, 'generated' ); ?>><?php esc_html_e( 'Generate from site display name' ); ?></option>
						<option value="image" <?php selected( $avatar_source, 'image' ); ?>><?php esc_html_e( 'Use a site-specific image' ); ?></option>
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
						<button type="button" class="button" id="select_avatar_image" <?php disabled( 'image' !== $avatar_source ); ?>><?php esc_html_e( 'Select Image' ); ?></button>
						<p class="description"><?php esc_html_e( 'Choose whether this site uses your account avatar, a generated avatar, or its own image.' ); ?></p>
					</div>
				</td>
			</tr>
			<?php endif; ?>
			<?php if ( array_intersect( [ 'administrator', 'editor' ], $user->roles ) ) : ?>
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
		</table>

		<?php submit_button( __( 'Update Site Profile' ) ); ?>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
