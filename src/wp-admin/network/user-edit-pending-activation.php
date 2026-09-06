<?php
/**
 * Edit a user pending network activation.
 *
 * @package calmPress
 * @subpackage Multisite
 * @since calmPress 1.0.0
 */

$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
$user = get_userdata( $user_id );

if ( ! $user || ! $user->has_network_invite( get_network() ) ) {
	wp_die( __( 'Invalid user ID.' ) );
}

if ( ! current_user_can( 'edit_user', $user->ID ) ) {
	wp_die( __( 'Sorry, you are not allowed to edit this user.' ) );
}

$network = get_network();
$errors  = new WP_Error();

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
	check_admin_referer( 'update-user_' . $user->ID );

	if ( ! isset( $_POST['email'], $_POST['display_name'], $_POST['locale'] )
		|| ! is_string( $_POST['email'] )
		|| ! is_string( $_POST['display_name'] )
		|| ! is_string( $_POST['locale'] )
	) {
		wp_die( 'The pending-user form did not submit all expected values.' );
	}

	$email        = wp_unslash( $_POST['email'] );
	$display_name = trim( wp_unslash( $_POST['display_name'] ) );
	$locale       = wp_unslash( $_POST['locale'] );

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
		try {
			$user->update_pending_network_user(
				$network,
				$email_address,
				$display_name,
				'site-default' === $locale ? '' : $locale
			);
			$user = get_userdata( $user->ID );

			wp_redirect(
				add_query_arg(
					[
						'user_id' => $user->ID,
						'updated' => 'true',
					],
					network_admin_url( 'user-edit.php' )
				)
			);
			exit;
		} catch ( RuntimeException $exception ) {
			$errors->add( 'user_update_failed', $exception->getMessage() );
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
		esc_html_e( 'This user is pending activation. You can change the email address, display name, and preferred language. Changing the email address will send an invitation to the new address.' );
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

	<form method="post" action="<?php echo esc_url( add_query_arg( 'user_id', $user->ID, network_admin_url( 'user-edit.php' ) ) ); ?>" novalidate="novalidate">
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
				<th scope="row">
					<label for="locale"><?php esc_html_e( 'Language' ); ?><span class="dashicons dashicons-translation" aria-hidden="true"></span></label>
				</th>
				<td>
					<?php
					$selected_locale = '' === $user->locale ? 'site-default' : $user->locale;
					wp_dropdown_languages(
						[
							'name'                     => 'locale',
							'id'                       => 'locale',
							'selected'                 => $selected_locale,
							'languages'                => get_available_languages(),
							'show_option_site_default' => true,
							'explicit_option_en_us'    => true,
						]
					);
					?>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'update-user_' . $user->ID ); ?>
		<?php submit_button( __( 'Update User' ) ); ?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
