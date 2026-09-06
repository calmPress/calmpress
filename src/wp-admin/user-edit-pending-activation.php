<?php
/**
 * Edit a user pending activation on a standalone site.
 *
 * @package calmPress
 * @since calmPress 1.0.0
 */

$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
$user    = get_userdata( $user_id );

if ( ! $user || ! in_array( 'pending_activation', $user->roles, true ) ) {
	wp_die( __( 'Invalid user ID.' ) );
}

if ( ! current_user_can( 'edit_user', $user->ID ) ) {
	wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
}

$errors = new WP_Error();

$editable_roles = get_editable_roles();

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	check_admin_referer( 'update-user_' . $user->ID );

	if ( ! isset( $_POST['email'], $_POST['display_name'], $_POST['role'], $_POST['locale'] )
		|| ! is_string( $_POST['email'] )
		|| ! is_string( $_POST['display_name'] )
		|| ! is_string( $_POST['role'] )
		|| ! is_string( $_POST['locale'] )
	) {
		wp_die( 'The pending-user form did not submit all expected values.' );
	}

	$email        = wp_unslash( $_POST['email'] );
	$display_name = trim( wp_unslash( $_POST['display_name'] ) );
	$role         = wp_unslash( $_POST['role'] );
	$locale       = wp_unslash( $_POST['locale'] );

	if ( ! isset( $editable_roles[ $role ] ) ) {
		wp_die( 'The pending-user form submitted an invalid role.' );
	}

	if ( 'site-default' !== $locale && 'en_US' !== $locale && ! in_array( $locale, get_available_languages(), true ) ) {
		wp_die( 'The pending-user form submitted an invalid preferred language.' );
	}

	try {
		$email_address = new calmpress\email\Email_Address( $email );
	} catch ( InvalidArgumentException ) {
		$errors->add( 'invalid_email', __( 'The email address is invalid.' ) );
	}

	// Save valid values, and send an invitation to the new email address if it changed.
	if ( ! $errors->has_errors() ) {
		$email_changed = $email_address->address !== $user->user_email;
		$result        = wp_update_user(
			[
				'ID'               => $user->ID,
				'user_email'       => $email_address->address,
				'display_name'     => $display_name,
				'activate_to_role' => $role,
				'locale'           => 'site-default' === $locale ? '' : $locale,
			]
		);

		if ( is_wp_error( $result ) ) {
			$errors = $result;
		} else {
			$user = get_userdata( $result );

			if ( $email_changed ) {
				$invitation_email = new calmpress\email\User_Invitation_Email(
					$user,
					get_option( 'blogname' ),
					wp_login_url()
				);
				$invitation_email->send();
			}

			wp_redirect(
				add_query_arg(
					[
						'user_id' => $user->ID,
						'updated' => 'true',
					],
					admin_url( 'user-edit.php' )
				)
			);
			exit;
		}
	}
}

/* translators: %s: User's display name. */
$title       = sprintf( __( 'Edit User Account %s' ), $user->display_name );
$parent_file = 'users.php';

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>
	<p>
		<?php
		esc_html_e( 'This user is pending activation. You can change the email address, display name, intended role, and preferred language. The role will be assigned when the user activates the account. Changing the email address will send an invitation to the new address.' );
		?>
	</p>

	<?php
	if ( isset( $_GET['updated'] ) ) {
		wp_admin_notice(
			esc_html__( 'User updated.' ),
			[
				'type'        => 'success',
				'dismissible' => true,
			]
		);
	}

	if ( $errors->has_errors() ) {
		wp_admin_notice(
			implode( "</p>\n<p>", array_map( 'esc_html', $errors->get_error_messages() ) ),
			[
				'type'           => 'error',
				'paragraph_wrap' => false,
			]
		);
	}
	?>

	<form method="post" action="<?php echo esc_url( add_query_arg( 'user_id', $user->ID, admin_url( 'user-edit.php' ) ) ); ?>" novalidate="novalidate">
		<table class="form-table" role="presentation">
			<tr class="form-field form-required">
				<th scope="row"><label for="email"><?php esc_html_e( 'Email' ); ?> <?php echo wp_required_field_indicator(); ?></label></th>
				<td><input type="email" class="regular-text" name="email" id="email" value="<?php echo esc_attr( $user->user_email ); ?>" required="required" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="display-name"><?php esc_html_e( 'Display Name' ); ?></label></th>
				<td><input type="text" class="regular-text" name="display_name" id="display-name" value="<?php echo esc_attr( $user->display_name ); ?>" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="role"><?php esc_html_e( 'Role' ); ?></label></th>
				<td>
					<select name="role" id="role">
						<?php wp_dropdown_roles( get_user_meta( $user->ID, 'activate_to_role', true ) ); ?>
					</select>
					<p class="description"><?php esc_html_e( 'The role assigned when the user activates the account.' ); ?></p>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row">
					<label for="locale"><?php esc_html_e( 'Language' ); ?><span class="dashicons dashicons-translation" aria-hidden="true"></span></label>
				</th>
				<td>
					<?php
					$selected_locale = '' === $user->locale ? 'site-default' : $user->locale;
					wp_dropdown_languages(
						[
							'name'                        => 'locale',
							'id'                          => 'locale',
							'selected'                    => $selected_locale,
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

		<?php wp_nonce_field( 'update-user_' . $user->ID ); ?>
		<?php submit_button( __( 'Update User' ) ); ?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
