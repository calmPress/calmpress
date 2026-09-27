<?php
/**
 * Test the local backup storage API.
 *
 * @package CalmPress
 * @since 1.0.0
 */

/**
 * Test the local backup storage API.
 *
 * @since 1.0.0
 */
class Local_Backup_Storage_Test extends WP_UnitTestCase {
	private string $root;
	private \calmpress\backup\Local_Backup_Storage $storage;

	/**
	 * Create isolated local storage for each test.
	 *
	 * @since 1.0.0
	 */
	public function set_up() {
		parent::set_up();
		$this->root = get_temp_dir() . 'backup-storage-' . wp_generate_uuid4();
		mkdir( $this->root );
		$this->storage = new \calmpress\backup\Local_Backup_Storage( $this->root . '/storage' );
	}

	/**
	 * Remove the files and directories created by each test.
	 *
	 * @since 1.0.0
	 */
	public function tear_down() {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}
		rmdir( $this->root );
		parent::tear_down();
	}

	/**
	 * Verify successive metadata writes retain both discoverable backups without temporary files.
	 *
	 * @since 1.0.0
	 */
	public function test_metadata_is_discoverable_and_does_not_overwrite_previous_backup() {
		foreach ( array( 'first', 'second' ) as $description ) {
			$this->storage->store_backup_meta( \calmpress\backup\Backup::new_backup_meta( $description, array() ) );
		}
		$backups = $this->storage->backups()->as_array();
		$this->assertCount( 2, $backups );
		$descriptions = array_map( static function ( $backup ) { return $backup->description(); }, $backups );
		sort( $descriptions );
		$this->assertSame( array( 'first', 'second' ), $descriptions );
		$this->assertSame( array(), glob( $this->root . '/storage/*.tmp' ) );
	}

	/**
	 * Verify an unusable storage directory raises a runtime exception when saving metadata.
	 *
	 * @since 1.0.0
	 */
	public function test_metadata_failure_throws() {
		file_put_contents( $this->root . '/storage', 'not a directory' );
		$this->expectException( RuntimeException::class );
		$this->storage->store_backup_meta( '{}' );
	}

	/**
	 * Verify direct file copies create destination directories beneath the storage root.
	 *
	 * @since 1.0.0
	 */
	public function test_direct_copy_uses_storage_root() {
		file_put_contents( $this->root . '/source', 'contents' );
		$this->storage->copy_file( $this->root . '/source', 'nested/destination' );
		$this->assertSame( 'contents', file_get_contents( $this->root . '/storage/nested/destination' ) );
	}
}
