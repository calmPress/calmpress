<?php
/**
 * Backup information and restore or deletion confirmation screen.
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'backup' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage backups at this site.' ), '', [ 'response' => 403 ] );
}

$manager = new \calmpress\backup\Backup_Manager();
try {
	$id = isset( $_GET['backup'] ) && is_string( $_GET['backup'] ) ? wp_unslash( $_GET['backup'] ) : '';
	$backup = $manager->backup_by_id( $id );
} catch ( \Exception $exception ) {
	wp_die(
		esc_html__( 'This backup could not be found.' ) . ' <a href="' . esc_url( admin_url( 'backups.php' ) ) . '">' . esc_html__( 'Show available backups' ) . '</a>',
		'',
		[ 'response' => 404 ]
	);
}

$backup_action = isset( $_GET['action'] ) && is_string( $_GET['action'] ) ? wp_unslash( $_GET['action'] ) : '';
if ( ! in_array( $backup_action, [ '', 'restore', 'delete' ], true ) ) {
	wp_die( 'Unknown backup action.', '', [ 'response' => 400 ] );
}
$info_url = add_query_arg( 'backup', $backup->identifier(), admin_url( 'backup-details.php' ) );

if ( 'delete' === $backup_action && 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
	check_admin_referer( 'delete_backup_' . $backup->identifier() );
	$notices = new \calmpress\admin\Admin_Notices_Handler();
	try {
		$manager->delete_backup( $backup->identifier() );
		$notices->add_success_message( esc_html__( 'Delete completed' ) );
	} catch ( \Exception $exception ) {
		$notices->add_error_message(
			/* translators: %s: Error message. */
			sprintf( esc_html__( 'Delete had failed. The reported reason is: %s' ), esc_html( $exception->getMessage() ) )
		);
	}
	\calmpress\utils\redirect_admin_with_action_results( admin_url( 'backups.php' ), $notices );
}

$parent_file = 'backups.php';
$submenu_file = 'backups.php';
$description = trim( $backup->description() );

$title = match ( $backup_action ) {
	/* translators: %s: Backup description. */
	'restore' => '' === $description ? __( 'Restore backup' ) : sprintf( __( 'Restore backup %s' ), $description ),
	/* translators: %s: Backup description. */
	'delete' => '' === $description ? __( 'Delete backup' ) : sprintf( __( 'Delete backup %s' ), $description ),
	/* translators: %s: Backup description. */
	default => '' === $description ? __( 'Backup information' ) : sprintf( __( 'Backup information for backup %s' ), $description ),
};
$created = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $backup->time_created() );
$backup_types = [];
foreach ( $backup->engines() as $engine_id ) {
	$engine_class = $manager->registered_engine_by_id( $engine_id );
	if ( '' === $engine_class ) {
		/* translators: %s: Backup engine identifier. */
		$backup_types[] = sprintf( __( 'Unregistered backup type: %s' ), $engine_id );
	} else {
		$backup_types[] = $engine_class::description();
	}
}
require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>
	<table class="backup-summary">
		<tbody>
			<tr>
				<th scope="row"><?php esc_html_e( 'Date created:' ); ?></th>
				<td><?php echo esc_html( $created ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Backup type:' ); ?></th>
				<td><?php echo esc_html( implode( ', ', $backup_types ) ); ?></td>
			</tr>
		</tbody>
	</table>
	<?php
	switch ( $backup_action ) {
		case 'restore':
			?>
			<p><?php esc_html_e( 'Restoring this backup will return the software and configuration covered by the backup to their saved state. Later changes within that scope will be removed. Content tables and uploaded media are outside this restore.' ); ?></p>
			<div class="notice notice-info inline"><p><?php esc_html_e( 'Restore is not available yet.' ); ?></p></div>
			<p>
				<button type="button" class="button button-primary" disabled><?php esc_html_e( 'Restore this backup' ); ?></button>
				<a class="button" href="<?php echo esc_url( $info_url ); ?>"><?php esc_html_e( 'Show backup information' ); ?></a>
			</p>
			<?php
			break;
		case 'delete':
			?>
			<p><?php esc_html_e( 'Delete this backup? It will no longer be available for restore. This cannot be undone and does not change the current site.' ); ?></p>
			<form action="<?php echo esc_url( add_query_arg( 'action', 'delete', $info_url ) ); ?>" method="post">
				<?php wp_nonce_field( 'delete_backup_' . $backup->identifier() ); ?>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Delete this backup' ); ?></button>
					<a class="button" href="<?php echo esc_url( $info_url ); ?>"><?php esc_html_e( 'Show backup information' ); ?></a>
				</p>
			</form>
			<?php
			break;
		default:
			?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( add_query_arg( 'action', 'restore', $info_url ) ); ?>"><?php esc_html_e( 'Restore' ); ?></a>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'action', 'delete', $info_url ) ); ?>"><?php esc_html_e( 'Delete' ); ?></a>
			</p>
			<h2><?php esc_html_e( 'Backup contents' ); ?></h2>
			<p><?php esc_html_e( 'The plugin and theme lists include the installed versions captured by this backup, whether active or inactive.' ); ?></p>
			<?php
			foreach ( $backup->engines_data() as $engine_id => $data ) {
				$engine_class = $manager->registered_engine_by_id( $engine_id );
				if ( '' === $engine_class ) {
					/* translators: %s: Backup engine identifier. */
					echo '<p>' . esc_html( sprintf( __( 'Information is unavailable because backup engine %s is not installed.' ), $engine_id ) ) . '</p>';
				} else {
					echo '<h3>' . esc_html( $engine_class::description() ) . '</h3>';
					echo $engine_class::data_description( $data );
				}
			}
			break;
	}
	?>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
