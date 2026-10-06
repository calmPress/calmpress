<?php
/**
 * Test the temporary backup section API.
 *
 * @package calmPress
 * @since 1.0.0
 */

/**
 * Test assembling and committing temporary backup sections.
 *
 * @since 1.0.0
 */
class Temporary_Backup_Section_Test extends WP_UnitTestCase {

	/**
	 * Root directory used by the test.
	 *
	 * @since 1.0.0
	 */
	private string $root;

	/**
	 * Local storage used to exercise the temporary section API.
	 *
	 * @since 1.0.0
	 */
	private \calmpress\backup\Local_Backup_Storage $storage;

	/**
	 * Read the temporary directory for cleanup verification.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Temporary_Backup_Section $section Temporary section to inspect.
	 *
	 * @return string Absolute temporary directory path.
	 */
	private function temporary_directory( \calmpress\backup\Temporary_Backup_Section $section ): string {
		$property = new ReflectionProperty( \calmpress\backup\Temporary_Backup_Section::class, 'temporary_directory' );

		return $property->getValue( $section );
	}

	/**
	 * Create isolated storage for each test.
	 *
	 * @since 1.0.0
	 */
	public function set_up() {
		parent::set_up();
		$this->root = get_temp_dir() . 'temporary-backup-section-' . wp_generate_uuid4();
		mkdir( $this->root );
		$this->storage = new \calmpress\backup\Local_Backup_Storage( $this->root . '/storage' );
	}

	/**
	 * Remove files created by each test.
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
	 * Verify files and content are assembled before commit and preserved in the committed section.
	 *
	 * @since 1.0.0
	 */
	public function test_commit_preserves_files_and_section_identity() {
		$source = $this->root . '/source';
		file_put_contents( $source, 'copied content' );
		$temporary = $this->storage->create_section( new \calmpress\backup\Backup_Section_Identity( 'plugin', 'example', '1.2' ), 'Example Plugin' );
		$temporary->copy_file( $source, 'directory/copied' );
		$temporary->file_put_contents( 'generated', 'generated content' );
		$temporary_directory = $this->temporary_directory( $temporary );

		$temporary->commit();
		$section = $this->storage->section( new \calmpress\backup\Backup_Section_Identity( 'plugin', 'example', '1.2' ) );
		$root = $this->root . '/fetched';
		mkdir( $root );
		$section->fetch( $root );

		$this->assertSame( 'plugin', $section->identity->type );
		$this->assertSame( 'example', $section->identity->location );
		$this->assertSame( '1.2', $section->identity->version );
		$this->assertSame( 'Example Plugin', $section->display_name() );
		$this->assertSame( 'copied content', file_get_contents( $root . '/directory/copied' ) );
		$this->assertSame( 'generated content', file_get_contents( $root . '/generated' ) );
		$this->assertDirectoryDoesNotExist( $temporary_directory );
	}

	/**
	 * Verify construction initializes identity and creates an empty temporary directory.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_initializes_identity_and_temporary_directory() {
		$temporary = $this->storage->create_section( new \calmpress\backup\Backup_Section_Identity( 'plugin', 'constructed', '2.0' ), 'Constructed Plugin' );
		$directory = $this->temporary_directory( $temporary );

		$this->assertSame( 'plugin', $temporary->type );
		$this->assertSame( 'constructed', $temporary->location );
		$this->assertSame( '2.0', $temporary->version );
		$property = new ReflectionProperty( \calmpress\backup\Temporary_Backup_Section::class, 'display_name' );
		$this->assertSame( 'Constructed Plugin', $property->getValue( $temporary ) );
		$this->assertDirectoryExists( $directory );
		$this->assertSame( array(), glob( $directory . '*' ) );
	}

	/**
	 * Verify a temporary section rejects file changes after commit.
	 *
	 * @since 1.0.0
	 */
	public function test_file_changes_after_commit_are_rejected() {
		$temporary = $this->storage->create_section( new \calmpress\backup\Backup_Section_Identity( 'theme', 'immutable', '1.0' ), 'Immutable Theme' );
		$temporary->commit();

		$this->expectException( RuntimeException::class );
		$temporary->file_put_contents( 'file', 'contents' );
	}

	/**
	 * Verify copying a file after commit is rejected.
	 *
	 * @since 1.0.0
	 */
	public function test_file_copy_after_commit_is_rejected() {
		$source = $this->root . '/source';
		file_put_contents( $source, 'contents' );
		$temporary = $this->storage->create_section( new \calmpress\backup\Backup_Section_Identity( 'theme', 'immutable-copy', '1.0' ), 'Immutable Copy Theme' );
		$temporary->commit();

		$this->expectException( RuntimeException::class );
		$temporary->copy_file( $source, 'file' );
	}

	/**
	 * Verify a temporary section cannot be committed more than once.
	 *
	 * @since 1.0.0
	 */
	public function test_repeated_commit_is_rejected() {
		$temporary = $this->storage->create_section( new \calmpress\backup\Backup_Section_Identity( 'theme', 'immutable', '1.0' ), 'Immutable Theme' );
		$temporary->commit();

		$this->expectException( RuntimeException::class );
		$temporary->commit();
	}

	/**
	 * Verify a failed commit identifies its section and preserves the storage error.
	 *
	 * @since 1.0.0
	 */
	public function test_commit_failure_identifies_section() {
		$identity = new \calmpress\backup\Backup_Section_Identity( 'plugin', 'example', '1.2' );
		$blocked_directory = $this->root . '/storage/plugins';
		file_put_contents( $blocked_directory, 'existing content' );
		$temporary = $this->storage->create_section( $identity, 'Example Plugin' );
		$temporary->file_put_contents( 'file', 'new content' );

		try {
			$temporary->commit();
			$this->fail( 'Committing without a usable destination directory must fail.' );
		} catch ( RuntimeException $exception ) {
			$this->assertStringContainsString( 'type: plugin, location: example, version: 1.2', $exception->getMessage() );
			$this->assertStringContainsString( 'plugins', $exception->getMessage() );
			$this->assertInstanceOf( RuntimeException::class, $exception->getPrevious() );
			$this->assertSame( 'existing content', file_get_contents( $blocked_directory ) );
		}
	}

	/**
	 * Verify destruction removes the directory of an abandoned temporary section.
	 *
	 * @since 1.0.0
	 */
	public function test_destructor_removes_abandoned_temporary_directory() {
		$temporary = $this->storage->create_section( new \calmpress\backup\Backup_Section_Identity( 'plugin', 'abandoned', '1.0' ), 'Abandoned Plugin' );
		$temporary->file_put_contents( 'nested/file', 'contents' );
		$directory = $this->temporary_directory( $temporary );
		$this->assertDirectoryExists( $directory );

		unset( $temporary );

		$this->assertDirectoryDoesNotExist( $directory );
	}
}
