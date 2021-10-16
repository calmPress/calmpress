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

$title       = esc_html__( 'Create Backup' );
$parent_file = 'backups.php';

$help = '<p>' . esc_html__( 'To create a new backup, fill in the form on this screen and click the Create button at the bottom.' ) . '</p>';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' => $help,
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
<?php settings_errors(); ?>
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

<form method="post" action="admin-post.php">
<input name="action" type="hidden" value="new_backup" />
	<?php wp_nonce_field( 'new_backup' ); ?>

<table class="form-table" role="presentation">
	<tr class="form-field">
		<th scope="row"><label for="description"><?php esc_html_e( 'Description' ); ?></label></th>
		<td>
			<textarea name="description" id="description"  rows="5" cols="50" class="large-text"></textarea>
			<p class="description"><?php esc_html_e( 'A general description of the reason for the backup, that will be associated with the backup when displayed in the backups list' ); ?></p>
		</td>
	</tr>
</table>
	<?php submit_button( __( 'Create' ) ); ?>
</form>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
