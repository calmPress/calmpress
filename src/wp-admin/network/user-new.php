<?php
/**
 * Add User network administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'create_users' ) ) {
	wp_die( __( 'Sorry, you are not allowed to add users to this network.' ) );
}

if ( isset( $_REQUEST['action'] ) && 'add-user' == $_REQUEST['action'] ) {
	check_admin_referer( 'add-user', '_wpnonce_add-user' );

	if ( ! current_user_can( 'manage_network_users' ) ) {
		wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
	}

	if ( ! is_array( $_POST['user'] ) ) {
		wp_die( __( 'Cannot create an empty user.' ) );
	}

	$user = wp_unslash( $_POST['user'] );

	if ( ! isset( $user['display_name'] ) || ! is_string( $user['display_name'] ) ) {
		wp_die( 'The add-user form did not submit a display name.' );
	}

	if ( ! isset( $user['locale'] ) || ! is_string( $user['locale'] ) ) {
		wp_die( 'The add-user form did not submit a preferred language.' );
	}

	if ( 'site-default' !== $user['locale'] && 'en_US' !== $user['locale'] && ! in_array( $user['locale'], get_available_languages(), true ) ) {
		wp_die( 'The add-user form submitted an invalid preferred language.' );
	}

	try {
		$email_address = new calmpress\email\Email_Address( $user['email'] );
		$display_name  = trim( $user['display_name'] );
		$locale        = 'site-default' === $user['locale'] ? '' : $user['locale'];

		$network      = get_network();
		$invited_user = get_user_by( 'email', $email_address->address );

		if ( $invited_user ) {
			$user_id = $invited_user->ID;
		} else {
			$user_id = wp_insert_user(
				[
					'user_login'   => md5( $email_address->address ),
					'user_email'   => $email_address->address,
					'user_pass'    => wp_generate_password( 32, true, true ),
					'display_name' => $display_name,
					'locale'       => $locale,
				]
			);

			if ( ! is_wp_error( $user_id ) ) {

				// A network invitation creates an account without granting capabilities on a site.
				remove_user_from_blog( $user_id, (int) $network->site_id );
				$invited_user = get_userdata( $user_id );
				$invited_user->mark_as_created_for_network_invitation();
			}
		}

		if ( ! is_wp_error( $user_id ) ) {
			if ( $network->has_user( $invited_user ) ) {
				wp_redirect( add_query_arg( 'update', 'existing', 'user-new.php' ) );
				exit;
			} else {
				if ( ! $invited_user->has_network_invite( $network ) ) {
					$invited_user->invite_to_network( $network );
				}

				$invitation_email = new calmpress\email\User_Invitation_Email(
					$invited_user,
					$network->site_name,
					wp_login_url()
				);
				$invitation_email->send();

				wp_redirect( add_query_arg( 'update', 'invited', 'user-new.php' ) );
				exit;
			}
		} else {
			$add_user_errors = $user_id;
		}
	} catch ( InvalidArgumentException ) {
		$add_user_errors = new WP_Error( 'invalid_email', __( 'The email address is invalid.' ) );
	}
}

$message      = '';
$message_type = 'success';
if ( isset( $_GET['update'] ) ) {
	if ( 'invited' === $_GET['update'] ) {
		$message = esc_html__( 'Invitation sent.' );
	} elseif ( 'existing' === $_GET['update'] ) {
		$message      = esc_html__( 'This user is already part of the network.' );
		$message_type = 'info';
	}
}

// Used in the HTML title tag.
$title       = __( 'Add User' );
$parent_file = 'users.php';

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
<h1 id="add-new-user"><?php _e( 'Add User' ); ?></h1>
<?php
if ( '' !== $message ) {
	wp_admin_notice(
		$message,
		array(
			'type'        => $message_type,
			'dismissible' => true,
			'id'          => 'message',
		)
	);
}

if ( isset( $add_user_errors ) && is_wp_error( $add_user_errors ) ) {
	$error_messages = '';
	foreach ( $add_user_errors->get_error_messages() as $error ) {
		$error_messages .= "<p>$error</p>";
	}

	wp_admin_notice(
		$error_messages,
		array(
			'type'           => 'error',
			'dismissible'    => true,
			'id'             => 'message',
			'paragraph_wrap' => false,
		)
	);
}
?>
	<form action="<?php echo esc_url( network_admin_url( 'user-new.php?action=add-user' ) ); ?>" id="adduser" method="post" novalidate="novalidate">
		<p><?php echo wp_required_field_message(); ?></p>
		<table class="form-table" role="presentation">
			<tr class="form-field form-required">
				<th scope="row"><label for="email"><?php esc_html_e( 'Email' ); ?> <?php echo wp_required_field_indicator(); ?></label></th>
				<td><input type="email" class="regular-text" name="user[email]" id="email" value="<?php echo isset( $user['email'] ) ? esc_attr( $user['email'] ) : ''; ?>" required="required" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="display-name"><?php esc_html_e( 'Display Name' ); ?></label></th>
				<td><input type="text" class="regular-text" name="user[display_name]" id="display-name" value="<?php echo isset( $user['display_name'] ) ? esc_attr( $user['display_name'] ) : ''; ?>" /></td>
			</tr>
			<tr class="form-field">
				<th scope="row"><label for="locale"><?php esc_html_e( 'Preferred Language' ); ?></label></th>
				<td>
					<?php
					$selected_locale = isset( $user['locale'] ) ? $user['locale'] : 'site-default';
					wp_dropdown_languages(
						[
							'name'                     => 'user[locale]',
							'id'                       => 'locale',
							'selected'                 => $selected_locale,
							'languages'                => get_available_languages(),
							'show_option_site_default' => true,
						]
					);
					?>
				</td>
			</tr>
			<tr class="form-field">
				<td colspan="2" class="td-full"><?php esc_html_e( 'An invitation to authenticate will be sent to the user via email.' ); ?></td>
			</tr>
		</table>
	<?php
	/**
	 * Fires at the end of the new user form in network admin.
	 *
	 * @since 4.5.0
	 */
	do_action( 'network_user_new_form' );

	wp_nonce_field( 'add-user', '_wpnonce_add-user' );
	submit_button( __( 'Add User' ), 'primary', 'add-user' );
	?>
	</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
