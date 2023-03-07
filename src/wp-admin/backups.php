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

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => esc_html__( 'Overview' ),
		'content' => '<p>' . __( 'This screen lists backups of the site.  You can delete unneeded ones.') . '</p>'
	)
);

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

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
	 * @return string Name of the default primary column, in this case, 'date'.
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
				'cb'          => '<input type="checkbox" />',
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
	 * Generate the bulk actions dropdown.
	 *
	 * Use the parents implementation but overide the name of the select input so it
	 * will not collide with other inputs on the page.
	 *
	 * @see WP_List_Table::bulk_actions
	 *
	 * @since 1.0.0
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 */
	protected function bulk_actions( $which = '' ) {
		ob_start();
		parent::bulk_actions( $which );
		$output = ob_get_clean();
		echo str_replace( '<select name="action', '<select name="subaction', $output );
	}

	/**
	 * Retrieves the list of bulk actions available for this table.
	 *
	 * @see WP_List_Table::get_bulk_actions
	 *
	 * @since 1.0.0
	 * 
	 * @return array Where key is the value of the option and the value is the human text.
	 */
	protected function get_bulk_actions() {
		return [ 'delete' => __( 'Delete' ) ];
	}

	/**
	 * Text displayed when no backups are found.
	 * 
	 * @since 1.0.0
	 */
	public function no_items() {
		esc_html_e( 'No Backups avaliable.' );
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
	 * Generates and displays row action links.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $item        Site being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for sites in Multisite, or an empty string
	 *                if the current column is not the primary column.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$actions = [];

		$details_url = add_query_arg( 'backup', $item->identifier(), admin_url( 'backup-details.php' ) );
		$actions['fullinfo'] = '<a href="' . $details_url . '">' . esc_html__( 'Full info' ) . '</a>';
		$actions['restore'] = '<a href="#">' . esc_html__( 'Restore' ) . '</a>';

		$delete_url = add_query_arg( 'action', 'delete_backup', admin_url( 'admin-post.php' ) );

		// Add the ID.
		$delete_url = add_query_arg( 'backup', $item->identifier(), $delete_url );

		$actions['delete'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			wp_nonce_url( $delete_url , 'delete_backup' ),
			/* translators: %s: Buckup's description. */
			esc_attr( sprintf( __( 'Delete &#8220;%s&#8221;' ), $item->description() ) ),
			esc_html__( 'Delete' )
		);

		return $this->row_actions( $actions );
	}

	/**
	 * Handles the checkbox column output.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The current backup item.
	 */
	public function column_cb( $item ) {
		?>
		<label class="screen-reader-text" for="cb_<?php echo esc_attr( $item->identifier() ); ?>">
			<?php
			/* translators: 1: Post date, 2: Post time. */
			$text = sprintf(
				/* translators: 1: Post date, 2: Post time. */
				__( '%1$s at %2$s' ),
				/* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
				wp_date( __( 'Y/m/d' ), $item->time_created() ),
				/* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
				wp_date( __( 'g:i a' ), $item->time_created() )
			);
			printf( esc_html__( 'Select %s' ), $text );
			?>
		</label>
		<input type="checkbox" id="cb_<?php echo esc_attr( $item->identifier() ); ?>" name="backups[]" value="<?php echo esc_attr( $item->identifier() ); ?>" />
		<?php
	}

	/**
	 * Handles the date column output.
	 * 
	 * @param \calmpress\backup\Backup $item The backup item for which to output the date.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The current backup item.
	 */
	public function column_date( \calmpress\backup\Backup $item ) {
		/* translators: 1: Post date, 2: Post time. */
		$text = sprintf(
			/* translators: 1: Post date, 2: Post time. */
			__( '%1$s at %2$s' ),
			/* translators: Post date format. See https://www.php.net/manual/datetime.format.php */
			wp_date( __( 'Y/m/d' ), $item->time_created() ),
			/* translators: Post time format. See https://www.php.net/manual/datetime.format.php */
			wp_date( __( 'g:i a' ), $item->time_created() )
		);
		$details_url = add_query_arg( 'backup', $item->identifier(), admin_url( 'backup-details.php' ) );
		echo '<a class="row-title" href="' . $details_url . '" aria-label=' . esc_attr( sprintf( __( 'Full details of backup create at %s' ), $text ) ) . '">' . esc_html( $text ) . '</a>';
	}

	/**
	 * Handles the description column output.
	 *
	 * @param \calmpress\backup\Backup $item The backup item for which to output the dadescriptionte.
	 *
	 * @since 1.0.0
	 *
	 * @param array $item The current backup item.
	 */
	public function column_description( \calmpress\backup\Backup $item ) {
		$text = $item->description();
		$details_url = add_query_arg( 'backup', $item->identifier(), admin_url( 'backup-details.php' ) );
		echo '<a class="row-title" href="' . $details_url . '" aria-label=' . esc_attr( sprintf( __( 'Full details of %s' ), $text ) ) . '">' . esc_html( $text ) . '</a>';
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

		echo esc_html( join( '<br>', $backup_engines ) );
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

\calmpress\utils\display_previous_action_results();

require_once ABSPATH . 'wp-admin/admin-header.php';
$parent_file = 'backups.php';
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="backup-new.php" class="page-title-action"><?php echo esc_html_x( 'Add New', 'backup' ); ?></a>
	<hr class="wp-header-end">
	<form id="backup-list" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="bulk_backup">
		<div class="backups-list-table-wrapper">
			<?php
			$backups_list_table = new Backup_List();
			$backups_list_table->prepare_items();
			$backups_list_table->display();
			?>
		</div>
	</form>
</div>
<?php
	require_once ABSPATH . 'wp-admin/admin-footer.php';
