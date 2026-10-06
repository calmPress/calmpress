<?php
/**
 * Test the local backup storage API.
 *
 * @package calmPress
 * @since 1.0.0
 */

/**
 * Test the local backup storage API.
 *
 * @since 1.0.0
 */
class Local_Backup_Storage_Test extends WP_UnitTestCase {
	/**
	 * Root directory used by the test.
	 *
	 * @since 1.0.0
	 */
	private string $root;

	/**
	 * Local storage under test.
	 *
	 * @since 1.0.0
	 */
	private \calmpress\backup\Local_Backup_Storage $storage;

	/**
	 * Locate a committed section inside the local storage for storage implementation tests.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup_Section $section Section to locate.
	 *
	 * @return string Absolute path to the stored section directory.
	 */
	private function stored_section_directory( \calmpress\backup\Backup_Section $section ): string {
		$property = new ReflectionProperty( \calmpress\backup\Local_Backup_Section::class, 'directory' );

		return $property->getValue( $section );
	}

	/**
	 * Locate the metadata file of a committed local section for storage implementation tests.
	 *
	 * @since 1.0.0
	 *
	 * @param \calmpress\backup\Backup_Section $section Section to locate.
	 *
	 * @return string Absolute path to the section metadata file.
	 */
	private function stored_section_metadata( \calmpress\backup\Backup_Section $section ): string {
		$property = new ReflectionProperty( \calmpress\backup\Local_Backup_Section::class, 'metadata_file' );

		return $property->getValue( $section );
	}

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
	 * Verify construction rejects a storage root which cannot be used as a directory.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_rejects_unusable_root() {
		$unusable_root = $this->root . '/unusable-storage';
		file_put_contents( $unusable_root, 'not a directory' );
		$this->expectException( RuntimeException::class );
		new \calmpress\backup\Local_Backup_Storage( $unusable_root );
	}

	/**
	 * Verify a local section cannot represent unavailable content or metadata.
	 *
	 * @since 1.0.0
	 */
	public function test_local_section_rejects_unavailable_files() {
		$this->expectException( RuntimeException::class );
		new \calmpress\backup\Local_Backup_Section( new \calmpress\backup\Backup_Section_Identity( 'plugin', 'missing', '1.0' ), time(), 'Missing Plugin', $this->root . '/missing', $this->root . '/missing.json' );
	}

	/**
	 * Create and commit a section for a storage API test.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type      Section type.
	 * @param string $name      Section name.
	 * @param string $version   Section version.
	 * @param int    $created      Creation timestamp to place in the test manifest.
	 * @param string $display_name Human-readable section name.
	 *
	 * @return \calmpress\backup\Backup_Section Committed section.
	 */
	private function create_section( string $type, string $name, string $version, int $created, string $display_name ): \calmpress\backup\Backup_Section {
		$identity = new \calmpress\backup\Backup_Section_Identity( $type, $name, $version );
		$pending  = $this->storage->create_section( $identity, $display_name );
		$pending->file_put_contents( 'file', 'contents' );
		$pending->commit();
		$section = $this->storage->section( $identity );
		$manifest = $this->stored_section_metadata( $section );
		$data = json_decode( file_get_contents( $manifest ), true );
		$data['creation_time'] = $created;
		file_put_contents( $manifest, json_encode( $data ) );

		return $section;
	}

	/**
	 * Verify creating an existing section reports the specific duplicate-section exception.
	 *
	 * @since 1.0.0
	 */
	public function test_create_section_rejects_an_existing_identity() {
		$identity = new \calmpress\backup\Backup_Section_Identity( 'plugin', 'example', '1.0' );
		$this->storage->create_section( $identity, 'Example Plugin' )->commit();

		$this->expectException( \calmpress\backup\Backup_Section_Already_Exists_Exception::class );
		$this->storage->create_section( $identity, 'Example Plugin' );
	}

	/**
	 * Verify committing an existing section directory publishes its metadata without replacing its files.
	 *
	 * @since 1.0.0
	 */
	public function test_commit_adopts_existing_section_directory() {
		$identity = new \calmpress\backup\Backup_Section_Identity( 'core', 'calmPress', '1.0.0-alpha26' );
		$directory = $this->root . '/storage/core/1.0.0-alpha26';
		mkdir( $directory, 0755, true );
		file_put_contents( $directory . '/existing', 'stored files' );
		$temporary = $this->storage->create_section( $identity, '' );
		$temporary->file_put_contents( 'replacement', 'staged files' );

		$temporary->commit();

		$section = $this->storage->section( $identity );
		$this->assertNotNull( $section );
		$this->assertSame( $directory, $this->stored_section_directory( $section ) );
		$this->assertSame( 'stored files', file_get_contents( $directory . '/existing' ) );
		$this->assertFileDoesNotExist( $directory . '/replacement' );
	}

	/**
	 * Verify cleanup deletes only expired directories that no backup references.
	 *
	 * @since 1.0.0
	 */
	public function test_cleanup_deletes_only_expired_orphaned_sections() {
		$referenced = $this->create_section( 'plugin', 'referenced', '1.0', 100, 'Referenced Plugin' );
		$old_orphan = $this->create_section( 'plugin', 'old-orphan', '1.0', 100, 'Old Plugin' );
		$new_orphan = $this->create_section( 'plugin', 'new-orphan', '2.0', time(), 'New Plugin' );
		$referenced_path = $this->stored_section_directory( $referenced );
		$old_orphan_path = $this->stored_section_directory( $old_orphan );
		$new_orphan_path = $this->stored_section_directory( $new_orphan );
		$this->storage->cleanup( time() - WEEK_IN_SECONDS, array( $referenced->identity ) );

		$this->assertDirectoryExists( $referenced_path );
		$this->assertDirectoryDoesNotExist( $old_orphan_path );
		$this->assertDirectoryExists( $new_orphan_path );
	}

	/**
	 * Verify committed section metadata and whole-section removal are exposed by storage.
	 *
	 * @since 1.0.0
	 */
	public function test_sections_returns_committed_immutable_units() {
		$this->create_section( 'theme', 'example', '3.2', 1234, 'Example Theme' );

		$sections = $this->storage->sections();

		$this->assertCount( 1, $sections );
		$this->assertSame( 'theme', $sections[0]->identity->type );
		$this->assertSame( 'example', $sections[0]->identity->location );
		$this->assertSame( '3.2', $sections[0]->identity->version );
		$this->assertSame( 'Example Theme', $sections[0]->display_name() );
		$this->assertSame( 1234, $sections[0]->creation_time );
		$metadata = json_decode( file_get_contents( $this->stored_section_metadata( $sections[0] ) ), true );
		$this->assertSame( 'themes/example/3.2', $metadata['directory'] );
		$sections[0]->remove();
		$this->assertDirectoryDoesNotExist( $this->root . '/storage/themes/example/3.2' );
		$this->assertFileDoesNotExist( $this->stored_section_metadata( $sections[0] ) );
	}

	/**
	 * Verify a single-file plugin and a plugin directory cannot share a storage path.
	 *
	 * @since 1.0.0
	 */
	public function test_plugin_file_and_directory_have_distinct_storage_paths() {
		$directory = $this->create_section( 'plugin', 'example.php', '1.0', time(), 'Directory Plugin' );
		$file = $this->create_section( 'plugin-file', 'example.php', '1.0', time(), 'Single-file Plugin' );

		$this->assertSame( $this->root . '/storage/plugins/example.php/1.0', $this->stored_section_directory( $directory ) );
		$this->assertSame( $this->root . '/storage/single-file-plugins/example.php/1.0', $this->stored_section_directory( $file ) );
		$this->assertDirectoryExists( $this->stored_section_directory( $directory ) );
		$this->assertDirectoryExists( $this->stored_section_directory( $file ) );
	}

	/**
	 * Verify fetching copies section contents without exposing storage metadata.
	 *
	 * @since 1.0.0
	 */
	public function test_fetch_copies_only_section_contents() {
		$identity  = new \calmpress\backup\Backup_Section_Identity( 'plugin', 'example', '1.0' );
		$temporary = $this->storage->create_section( $identity, 'Example Plugin' );
		$temporary->file_put_contents( 'directory/file', 'contents' );
		$temporary->commit();
		$section     = $this->storage->section( $identity );
		$destination = $this->root . '/fetched';
		mkdir( $destination );

		$section->fetch( $destination );

		$this->assertSame( 'contents', file_get_contents( $destination . '/directory/file' ) );
		$this->assertFileDoesNotExist( $destination . '/.section.json' );
	}

	/**
	 * Verify fetching rejects a nonempty destination without changing it.
	 *
	 * @since 1.0.0
	 */
	public function test_fetch_rejects_nonempty_destination() {
		$section = $this->create_section( 'plugin', 'example', '1.0', time(), 'Example Plugin' );
		$destination = $this->root . '/existing';
		mkdir( $destination );
		file_put_contents( $destination . '/file', 'existing' );

		try {
			$section->fetch( $destination );
			$this->fail( 'Fetching to a nonempty destination must fail.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'The backup section destination must be empty.', $exception->getMessage() );
			$this->assertSame( 'existing', file_get_contents( $destination . '/file' ) );
		}
	}

	/**
	 * Verify fetching requires an absolute destination path.
	 *
	 * @since 1.0.0
	 */
	public function test_fetch_rejects_relative_destination() {
		$section = $this->create_section( 'plugin', 'example', '1.0', time(), 'Example Plugin' );

		$this->expectException( RuntimeException::class );
		$section->fetch( 'relative/destination' );
	}

}
