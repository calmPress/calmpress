<?php
/**
 * Test the temporary local backup storage API.
 *
 * @package calmPress
 * @since 1.0.0
 */

/**
 * Test the temporary local backup storage API.
 *
 * @since 1.0.0
 */
class Local_Storage_Temporary_Backup_Storage_Test extends WP_UnitTestCase {
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
	 * Verify empty files can be stored and committing twice preserves the stored file.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_file_and_repeated_commit() {
		$staging = $this->storage->section_working_area_storage( 'section' );
		$staging->file_put_contents( 'empty', '' );
		$staging->store();
		$staging->store();
		$this->assertSame( '', file_get_contents( $this->root . '/storage/section/empty' ) );
	}
}
