<?php
/**
 * Create Backup Administration Screen.
 *
 * @package calmPress
 */

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'backup' ) ) {
	wp_die(
		'<h1>' . __( 'You do not have permissions to create backup.' ) . '</h1>' .
		403
	);
}

if ( isset( $_POST['action'] ) && 'createbackup' === $_POST['action'] ) {
	check_admin_referer( 'new-backup', '_wpnonce_new-backup' );

	$description = wp_unslash( $_POST['description'] );

	$storage = new \calmpress\backup\Local_Backup_Storage();
	$storage->Backup( $description );
	$redirect = add_query_arg( array( 'update' => 'new' ), 'backup-new.php' );
	wp_redirect( $redirect );
	die();
}

$title       = __( 'Create Backup' );
$parent_file = 'backups.php';

$help = '<p>' . __( 'To create a new backup, fill in the form on this screen and click the Create button at the bottom.' ) . '</p>';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $help,
	)
);

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
			case 'addnoconfirmation':
				$message = __( 'User has been added to your site.' );

				if ( $edit_link ) {
					$message .= sprintf( ' <a href="%s">%s</a>', $edit_link, __( 'Edit user' ) );
				}

				$messages[] = $message;
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
<h1 id="create-backup">
<?php
	esc_html_e( 'Create Backup' );
?>
</h1>

<?php if ( isset( $errors ) && is_wp_error( $errors ) ) : ?>
	<div class="error">
		<ul>
		<?php
		foreach ( $errors->get_error_messages() as $err ) {
			echo "<li>$err</li>\n";
		}
		?>
		</ul>
	</div>
	<?php
endif;

if ( ! empty( $messages ) ) {
	foreach ( $messages as $msg ) {
		echo '<div id="message" class="updated notice is-dismissible"><p>' . $msg . '</p></div>';
	}
}
?>

<?php if ( isset( $add_user_errors ) && is_wp_error( $add_user_errors ) ) : ?>
	<div class="error">
		<?php
		foreach ( $add_user_errors->get_error_messages() as $message ) {
			echo "<p>$message</p>";
		}
		?>
	</div>
<?php endif; ?>

<form method="post" name="newbackup" id="newbackup" class="validate" novalidate="novalidate">
<input name="action" type="hidden" value="createbackup" />
	<?php wp_nonce_field( 'new-backup', '_wpnonce_new-backup' ); ?>

<table class="form-table" role="presentation">
	<tr class="form-field">
		<th scope="row"><label for="description"><?php esc_html_e( 'Description' ); ?></label></th>
		<td>
			<textarea name="description" id="description"  rows="5" cols="50" class="large-text"></textarea>
			<p class="description"><?php esc_html_e( 'A general description of the reason for the backup, that will be associated with the backup when displayed in the backups list' ); ?></p>
		</td>
	</tr>
</table>
	<?php submit_button( __( 'Create' ), 'primary', 'newbackupsub', true, array( 'id' => 'newbackupsub' ) ); ?>
</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
