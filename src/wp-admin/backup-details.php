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
		'<h1>' . esc_html__( 'You do not have permissions to view this.' ) . '</h1>' .
		403
	);
}

$backup  = null;
$manager = new \calmpress\backup\Backup_Manager();

if ( isset( $_GET['backup'] ) ) {
	try {
		$backup = $manager->backup_by_id( $_GET['backup'] );
	} catch ( \Exception $e ) {
		// No such backup.
	}
}

if ( null === $backup ) {
	wp_die(
		'<h1>' . __( 'Unknown error, try again.' ) . '</h1>' .
		403
	);
}

/* translators: 1: The backup description. */
$title       = esc_html( sprintf( __( 'Backup Details of %s' ), $backup->description() ) );
$parent_file = 'backups.php';

$help = '<p>' . esc_html__( 'At this screen you can see information on a specific backup, delete it and restore from it.' ) . '</p>';

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
	esc_html_e( 'Backup Details' );
?>
</h1>
<div id="notifications"><p></p></div>

<h2><?php esc_html_e( 'Time Created' ); ?></h2>
<p>
<?php
	$text = sprintf(
		/* translators: 1: Backup date, 2: Backup time. */
		__( '%1$s at %2$s' ),
		/* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
		wp_date( __( 'Y/m/d' ), $backup->time_created() ),
		/* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
		wp_date( __( 'g:i a' ), $backup->time_created() )
	);
		/* translators: 1: time of backup creation. */
		echo esc_html( sprintf( __( 'Created at %s ' ), $text ) );
	?>
</p>
<h2><?php esc_html_e( 'Description' ); ?></h2>
<p>
<?php 
	echo esc_html( $backup->description() );
?>
</p>
<?php
foreach ( $backup->engines_data() as $engine_id => $data ) {
	$engine_class = $manager->registered_engine_by_id( $engine_id );
	echo '<h2>';
	if ( '' === $engine_class ) {
		/* translators: 1: The backup engine identifier. */
		echo '<h2>' . esc_html( sprintf( __( 'Unregistered backup type of: %s', $engine_id ) ) ) . '</h2>';
	} else {
		/* translators: 1: The backup engine descrption. */
		echo '<h2>' . esc_html( sprintf( __( ' Details related to %s' ), $engine_class::description() ) ) . '</h2>';
		echo $engine_class::data_description( $data );
	}
}
?>
<form method="post" action="admin-post.php">
	<?php submit_button( __( 'Create' ) ); ?>
</form>
</div>

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
