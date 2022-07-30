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
	wp_die( __( 'Sorry, you are not allowed to backup this site.' ) );
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
	public function __construct() {

		parent::__construct(
			[
				'singular' => __( 'Backup' ),
				'plural'   => __( 'Backups' ),
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
	protected function get_default_primary_column_name() {
		return 'date';
	}

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

	protected function get_bulk_actions() {
		return [ 'delete' => __( 'Delete' ) ];
	}

	public function delete_backup() {}

	/**
	 * Text displayed when no backups are found.
	 * 
	 * @since 1.0.0
	 */
	public function no_items() {
		_e( 'No Backups avaliable.' );
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
			'date'        => __( 'Date' ),
			'description' => __( 'Description' ),
			'type'        => __( 'Type' ),
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

		$actions['fullinfo'] = '<a href="#">' . esc_html__( 'Full info' ) . '</a>';
		$actions['restore'] = '<a href="#">' . esc_html__( 'Restore' ) . '</a>';
		$actions['delete'] = '<a href="#">' . esc_html__( 'Delete' ) . '</a>';

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
		echo esc_html( $text );
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
		echo esc_html( $item->description());
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

require_once ABSPATH . 'wp-admin/admin-header.php';
$parent_file = 'backups.php';
?>

<div class="wrap">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="backup-new.php" class="page-title-action"><?php echo esc_html_x( 'Add New', 'backup' ); ?></a>
	<hr class="wp-header-end">
	<div class="backups-list-table-wrapper">
		<?php
		$backups_list_table = new Backup_List();
		$backups_list_table->prepare_items();
		$backups_list_table->display();
		?>
	</div>
</div>
<?php
	require_once ABSPATH . 'wp-admin/admin-footer.php';
