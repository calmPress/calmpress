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

wp_enqueue_script( 'calm-backup' );

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
<h1 id="create-backup">
<?php
	esc_html_e( 'Create Backup' );
?>
</h1>
<div id="notifications"><p></p></div>

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

<?php
	$backup_manager = new \calmpress\backup\Backup_Manager();

	// A form is used here but the actual request is handled by sending a REST API message
	// based on the data in the form.
?>
<form method="post" action="admin-post.php">
	<?php wp_nonce_field( 'wp_rest' ); ?>

	<table class="form-table" role="presentation">
		<tr class="form-field">
			<th scope="row"><label for="description"><?php esc_html_e( 'Description' ); ?></label></th>
			<td>
				<textarea name="description" id="description"  rows="5" cols="50" class="large-text"></textarea>
				<p class="description"><?php esc_html_e( 'A general description of the reason for the backup, that will be associated with the backup when displayed in the backups list' ); ?></p>
			</td>
		</tr>
		<tr  class="form-field">
			<th scope="row"><label for="storage"><?php esc_html_e( 'Store at' ); ?></label></th>
			<td>
				<?php
				$available_storage = $backup_manager->available_storages();
				if ( count( $available_storage ) === 1 ) {
					$storage = reset( $available_storage );
					echo '<input type="hidden" id="storage" value="' . esc_attr( $storage->identifier() ) . '" />';
					echo esc_html( $storage->description() );
				} else {
					echo '<select id="storage">';
					foreach ( $available_storage as $storage ) {
						echo '<option value="' . esc_attr( $storage->identifier() ) . '">' . esc_html( $storage->description() ) . '</option>'; 
					}
					echo '</select>';
				}
				?>
				<p class="description"><?php esc_html_e( 'The storage meduim on which the backup will be stored.' ); ?></p>
			</td>
		</tr>
		<tr  class="form-field">
			<th scope="row"><label for="storage"><?php esc_html_e( 'What to backup' ); ?></label></th>
			<td>
				<?php
				$available_engines = $backup_manager->available_engines();
				if ( count( $available_engines ) === 1 ) {
					$engine = reset( $available_engines );
					echo '<input type="hidden" id="engines" value="' . esc_attr( $engine::identifier() ) . '" />';
					echo esc_html( $engine::description() );
				} else {
					echo '<select id="storage" multiple="multiple">';
					foreach ( $available_engines as $engine ) {
						echo '<option value="' . esc_attr( $engine::identifier() ) . '">' . esc_html( $engine::description() ) . '</option>'; 
					}
					echo '</select>';
				}
				?>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Create' ) ); ?>
</form>
</div>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
