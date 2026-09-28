<?php
/**
 * Backup Administration Screen
 *
 * @package calmPress
 * @since 1.0.0
 */

/** Load WordPress Admin Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'backup' ) ) {
	wp_die( __( 'Sorry, you are not allowed to manage backups at this site.' ) );
}

$title = __( 'Backups' );

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Display available backups and their management actions.
 *
 * @since 1.0.0
 */
class Backup_List extends WP_List_Table {

	/**
	 * Construct the table object.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		parent::__construct(
			[
				'singular' => 'backup',
				'plural'   => 'backups',
				'screen'   => 'backups',
				'ajax'     => false,
			]
		);
	}

	/**
	 * Gets the name of the default primary column.
	 *
	 * @since 1.0.0
	 *
	 * @return string Name of the date column.
	 */
	protected function get_default_primary_column_name(): string {
		return 'date';
	}

	/**
	 * Gets the columns description.
	 *
	 * @since 1.0.0
	 *
	 * @return array.
	 */
	protected function get_column_info() {
		return array(
			[
				'date'        => __( 'Date' ),
				'description' => __( 'Description' ),
				'type'        => __( 'Type' ),
			],
			array(),
			array(),
			'date',
		);
	}

	/**
	 * Text displayed when no backups are found.
	 * 
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No backups available.' );
	}

	/**
	 * Gets the list of columns.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	public function get_columns() {
		return [
			'date'        => esc_html__( 'Date' ),
			'description' => esc_html__( 'Description' ),
			'type'        => esc_html__( 'Type' ),
		];
	}

	/**
	 * Prepares the list of backups.
	 *
	 * @since 1.0.0
	 */
	public function prepare_items() {
		$manager = new \calmpress\backup\Backup_Manager();
		$this->items = $manager->existing_backups();
	}

	/**
	 * Generates backup action links for the primary column.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup $item        Backup being acted upon.
	 * @param string                   $column_name Current column name.
	 * @param string                   $primary     Primary column name.
	 *
	 * @return string Action links and the responsive toggle, or an empty string for other columns.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = [];

		$details_url = add_query_arg( 'backup', $item->identifier(), admin_url( 'backup-details.php' ) );
		$actions['fullinfo'] = '<a href="' . esc_url( $details_url ) . '">' . esc_html__( 'Info' ) . '</a>';
		$restore_url = add_query_arg( 'action', 'restore', $details_url );
		$actions['restore'] = '<a href="' . esc_url( $restore_url ) . '">' . esc_html__( 'Restore' ) . '</a>';

		$delete_url = add_query_arg( 'action', 'delete', $details_url );

		$actions['delete'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $delete_url ),
			/* translators: %s: Buckup's description. */
			esc_attr( sprintf( __( 'Delete &#8220;%s&#8221;' ), $item->description() ) ),
			esc_html__( 'Delete' )
		);

		return $this->row_actions( $actions ) . parent::handle_row_actions( $item, $column_name, $primary );
	}

	/**
	 * Handles the date column output.
	 * 
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup $item The current backup item.
	 */
	public function column_date( \calmpress\backup\Backup $item ) {
		/* translators: 1: Backup date, 2: Backup time. */
		$text = sprintf(
			/* translators: 1: Backup date, 2: Backup time. */
			__( '%1$s at %2$s' ),
			/* translators: Backup date format. See https://www.php.net/manual/datetime.format.php */
			wp_date( __( 'Y/m/d' ), $item->time_created() ),
			/* translators: Backup time format. See https://www.php.net/manual/datetime.format.php */
			wp_date( __( 'g:i a' ), $item->time_created() )
		);
		$details_url = add_query_arg( 'backup', $item->identifier(), admin_url( 'backup-details.php' ) );
		echo '<a class="row-title" href="' . esc_url( $details_url ) . '">' . esc_html( $text ) . '</a>';
	}

	/**
	 * Handles the description column output.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup $item The current backup item.
	 */
	public function column_description( \calmpress\backup\Backup $item ) {
		$details_url = add_query_arg( 'backup', $item->identifier(), admin_url( 'backup-details.php' ) );
		if ( '' === trim( $item->description() ) ) {
			echo '<a class="row-title" href="' . esc_url( $details_url ) . '"><span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No description' ) . '</span></a>';
			return;
		}
		echo '<a class="row-title" href="' . esc_url( $details_url ) . '">' . esc_html( $item->description() ) . '</a>';
	}

	/**
	 * Handles the type column output. Displays the list of engines which were used
	 * in the backup creation.
	 *
	 * @param \calmpress\backup\Backup $item The backup item for which to output the list of engines.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The current backup item.
	 */
	public function column_type( \calmpress\backup\Backup $item ) {
		$engines = $item->engines();
		$manager = new \calmpress\backup\Backup_Manager();
		
		$backup_engines = [];
		foreach ( $engines as $engine ) {
			$engine_class  = $manager->registered_engine_by_id( $engine );
			if ( '' === $engine_class ) {
				/* translators: 1: The backup engine identifier. */
				$backup_engines[] = esc_html( sprintf( __( 'Unregistered backup type of: %s', $engine ) ) );
			} else {
				$backup_engines[] = esc_html( $engine_class::description() );
			}
		}

		echo implode( '<br>', $backup_engines );
	}

	/**
	 * Handles the storage column output.
	 *
	 * @param \calmpress\backup\Backup $item The backup item for which to output the storage name.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The current backup item.
	 */
	public function column_storage( array $item ) {
		echo esc_html( $item->storage->description() );
	}

}

$parent_file = 'backups.php';
$submenu_file = 'backups.php';
$backups_list_table = new Backup_List();
$backups_list_table->prepare_items();
\calmpress\utils\display_previous_action_results();
require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="backup-new.php" class="page-title-action"><?php esc_html_e( 'Create Backup' ); ?></a>
	<hr class="wp-header-end">
	<p><?php esc_html_e( 'Choose a backup to review or restore its saved software and configuration.' ); ?></p>
		<div class="backups-list-table-wrapper">
			<?php
			$backups_list_table->display();
			?>
		</div>
</div>
<?php
	require_once ABSPATH . 'wp-admin/admin-footer.php';
