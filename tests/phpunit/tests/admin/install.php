<?php
/**
 * Tests preparation of installation runtime directories.
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\filesystem\Filesystem_Access;
use calmpress\filesystem\Local_Filesystem_Access;

require_once ABSPATH . 'wp-admin/includes/install-utilities.php';

/**
 * Covers directory creation and PHP access independently of database installation.
 *
 * @since 1.0.0
 */
class Tests_Prepare_Installation_Directories extends PHPUnit\Framework\TestCase {

	/**
	 * Isolated directory used by each test.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $root;

	/**
	 * Failed removal identifies the directory that needs manual removal.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_reports_failed_removal(): void {
		$directory = $this->root . '/.private';
		mkdir( $directory );
		file_put_contents( $directory . '/old.txt', 'old site' );
		chmod( $directory, 0500 );

		try {
			if ( is_writable( $directory ) ) {
				$this->markTestSkipped( 'The process can write despite read-only permissions.' );
			}

			$errors = create_directories_writable_by_web_server( new Local_Filesystem_Access(), $this->root, [ '.private', 'uploads' ] );

			$this->assertSame( 'runtime_directory_removal_failed', $errors->get_error_code() );
			$this->assertStringContainsString( $directory, $errors->get_error_message() );
		} finally {
			chmod( $directory, 0700 );
		}
	}

	/**
	 * Recreating runtime directories removes old site contents and leaves fresh writable directories.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_recreates_old_runtime_directories(): void {
		foreach ( [ '.private', 'uploads' ] as $name ) {
			mkdir( $this->root . '/' . $name . '/nested', 0755, true );
			file_put_contents( $this->root . '/' . $name . '/nested/old.txt', 'old site' );
		}

		$errors = create_directories_writable_by_web_server( new Local_Filesystem_Access(), $this->root, [ '.private', 'uploads' ] );

		$this->assertFalse( $errors->has_errors() );

		foreach ( [ '.private', 'uploads' ] as $name ) {
			$this->assertSame( [ '.', '..' ], scandir( $this->root . '/' . $name ) );
			$this->assertTrue( is_writable( $this->root . '/' . $name ) );
		}
	}

	/**
	 * Create an isolated filesystem fixture.
	 *
	 * @since 1.0.0
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/calmpress-runtime-' . wp_generate_uuid4();
		mkdir( $this->root, 0700 );
	}

	/**
	 * Remove only the current test's filesystem fixture.
	 *
	 * @since 1.0.0
	 */
	protected function tearDown(): void {
		$directories = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );

		foreach ( $directories as $entry ) {

			if ( $entry->isDir() ) {
				chmod( $entry->getPathname(), 0700 );
			}

		}

		chmod( $this->root, 0700 );
		calmpress\utils\delete_directory( $this->root );
		parent::tearDown();
	}

	/**
	 * Missing runtime directories are created directly by PHP when it already has write access.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_creates_directly_when_writable(): void {
		$private = $this->root . '/.private';
		$uploads = $this->root . '/uploads';
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->expects( $this->never() )->method( 'stream_url_from_path' );
		$credentials->expects( $this->never() )->method( 'change_permissions' );

		$errors = create_directories_writable_by_web_server( $credentials, $this->root, [ '.private', 'uploads' ] );

		$this->assertFalse( $errors->has_errors() );
		$this->assertDirectoryExists( $private );
		$this->assertDirectoryExists( $uploads );
		$this->assertSame( [ '.', '..' ], scandir( $private ) );
		$this->assertSame( [ '.', '..' ], scandir( $uploads ) );
	}

	/**
	 * Nested relative paths are created by changing only the supplied root's permissions.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_uses_explicit_root_for_nested_path(): void {
		$root        = $this->protected_parent_directory();
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->expects( $this->exactly( 2 ) )->method( 'change_permissions' )
			->with( $root, $this->anything() )->willReturnCallback( 'chmod' );

		$errors = create_directories_writable_by_web_server( $credentials, $root . '/', [ 'uploads/nested' ] );

		$this->assertFalse( $errors->has_errors() );
		$this->assertDirectoryExists( $root . '/uploads/nested' );
		$this->assertSame( 0555, fileperms( $root ) & 07777 );
	}

	/**
	 * A nonexistent root raises a domain exception before changing permissions.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_requires_existing_root(): void {
		$root        = $this->root . '/missing';
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->expects( $this->never() )->method( 'change_permissions' );

		$this->expectException( DomainException::class );
		$this->expectExceptionMessage( $root );

		create_directories_writable_by_web_server( $credentials, $root, [ 'uploads' ] );
	}

	/**
	 * A file occupying a required directory path produces an error without overwriting it.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_rejects_existing_file(): void {
		$path = $this->root . '/uploads';
		file_put_contents( $path, 'keep' );

		$errors = create_directories_writable_by_web_server( new Local_Filesystem_Access(), $this->root, [ 'uploads' ] );

		$this->assertSame( 'runtime_path_not_directory', $errors->get_error_code() );
		$this->assertSame( 'keep', file_get_contents( $path ) );
	}

	/**
	 * An existing non-directory path stops preparation of later runtime directories.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_stops_after_invalid_path(): void {
		$occupied = $this->root . '/occupied';
		$uploads  = $this->root . '/uploads';
		file_put_contents( $occupied, 'keep' );

		$errors = create_directories_writable_by_web_server( new Local_Filesystem_Access(), $this->root, [ 'occupied', 'uploads' ] );

		$this->assertSame( 'runtime_path_not_directory', $errors->get_error_code() );
		$this->assertSame( 'keep', file_get_contents( $occupied ) );
		$this->assertDirectoryDoesNotExist( $uploads );
	}

	/**
	 * An unexpected mkdir failure raises an exception instead of an installer error.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_throws_after_mkdir_failure(): void {
		$blocked = $this->protected_parent_directory();
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( $blocked . '/uploads' );

		create_directories_writable_by_web_server( new Local_Filesystem_Access(), $this->root, [ 'content/uploads', 'uploads' ] );
	}

	/**
	 * An unexpected mkdir failure restores protected root permissions before propagating.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_restores_root_after_mkdir_exception(): void {
		$root = $this->protected_parent_directory();
		chmod( $root, 0755 );
		mkdir( $root . '/blocked', 0555 );
		chmod( $root, 0555 );
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( 'chmod' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( $root . '/blocked/uploads' );

		try {
			create_directories_writable_by_web_server( $credentials, $root, [ 'blocked/uploads' ] );
		} finally {
			$this->assertSame( 0555, fileperms( $root ) & 07777 );
		}
	}

	/**
	 * Connection failures are reported without creating local runtime directories.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_reports_connection_failure(): void {
		$parent = $this->protected_parent_directory();
		$directory = $parent . '/uploads';
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( static function ( string $path, int $mode ): bool {

			if ( 0777 === $mode ) {
				throw new \calmpress\filesystem\FTP_Connection_Exception( 'Connection failed.', \calmpress\filesystem\FTP_Connection_Exception::CONNECTION_FAILED );
			}

			return chmod( $path, $mode );
		} );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ 'uploads' ] );

		$this->assertSame( 'runtime_parent_permissions_change_failed', $errors->get_error_code() );
		$this->assertDirectoryDoesNotExist( $directory );
	}

	/**
	 * An unreadable directory reports a read-access error even when PHP can write to it.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_check_directory_writable_by_web_server_rejects_unreadable_directory(): void {
		$directory = $this->root . '/unreadable';
		mkdir( $directory, 0300 );

		try {

			if ( is_readable( $directory ) ) {
				$this->markTestSkipped( 'The process can read directories despite read permissions.' );
			}

			$this->assertTrue( is_writable( $directory ) );
			$errors = check_directory_writable_by_web_server( $directory );

			$this->assertSame( 'runtime_directory_not_readable', $errors->get_error_code() );
			$this->assertStringContainsString( $directory, $errors->get_error_message() );
		} finally {
			chmod( $directory, 0700 );
		}
	}

	/**
	 * Create a parent directory for which this PHP process lacks write access.
	 *
	 * @since 1.0.0
	 *
	 * @return string Protected parent path.
	 */
	private function protected_parent_directory(): string {
		$parent = $this->root . '/content';
		mkdir( $parent, 0555 );

		if ( is_writable( $parent ) ) {
			$this->markTestSkipped( 'The process can write despite read-only permissions.' );
		}

		return $parent;
	}

	/**
	 * Root permissions are changed once for multiple directories and restored before returning.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_restores_parent_permissions_after_creation(): void {
		$parent = $this->protected_parent_directory();
		$directory = $parent . '/uploads';
		$credentials = $this->createMock( Filesystem_Access::class );
		$modes = [];
		$credentials->method( 'change_permissions' )->willReturnCallback( static function ( string $path, int $mode ) use ( &$modes ): bool {
			$modes[] = $mode;

			return chmod( $path, $mode );
		} );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ '.private', 'uploads' ] );

		$this->assertFalse( $errors->has_errors() );
		$this->assertDirectoryExists( $parent . '/.private' );
		$this->assertDirectoryExists( $directory );
		$this->assertSame( [ 0777, 0555 ], $modes );
		$this->assertSame( 0555, fileperms( $parent ) & 07777 );
		$this->assertSame( 0755, fileperms( $directory ) & 07777 );
	}

	/**
	 * PHP access is checked after the root's original permissions have been restored.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_validates_access_after_restoration(): void {
		$root = $this->protected_parent_directory();
		chmod( $root, 0000 );
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( 'chmod' );

		try {
			$errors = create_directories_writable_by_web_server( $credentials, $root, [ 'uploads' ] );

			$this->assertSame( 'runtime_directory_not_readable', $errors->get_error_code() );
			$this->assertSame( 0000, fileperms( $root ) & 07777 );
		} finally {
			chmod( $root, 0700 );
		}
	}

	/**
	 * An existing file encountered after granting access still allows the root's permissions to be restored.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_restores_parent_permissions_after_creation_failure(): void {
		$parent = $this->protected_parent_directory();
		$directory = $parent . '/uploads';
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( static function ( string $path, int $mode ) use ( $directory ): bool {
			$changed = chmod( $path, $mode );

			if ( 0777 === $mode ) {
				file_put_contents( $directory, 'occupied' );
			}

			return $changed;
		} );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ 'uploads' ] );

		$this->assertSame( 'runtime_path_not_directory', $errors->get_error_code() );
		$this->assertSame( 0555, fileperms( $parent ) & 07777 );
		$this->assertSame( 'occupied', file_get_contents( $directory ) );
	}

	/**
	 * A failed permission change returns without attempting creation or restoration.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_returns_after_permission_exception(): void {
		$parent      = $this->protected_parent_directory();
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->expects( $this->once() )->method( 'change_permissions' )
			->with( $parent, 0777 )
			->willThrowException( new \calmpress\filesystem\FTP_Connection_Exception( 'Connection failed.', \calmpress\filesystem\FTP_Connection_Exception::CONNECTION_FAILED ) );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ 'uploads' ] );

		$this->assertSame( 'runtime_parent_permissions_change_failed', $errors->get_error_code() );
		$this->assertDirectoryDoesNotExist( $parent . '/uploads' );
		$this->assertSame( 0555, fileperms( $parent ) & 07777 );
	}

	/**
	 * Failed restoration blocks installation and explains how to restore the exact original permissions.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_explains_how_to_fix_restore_failure(): void {
		$parent = $this->protected_parent_directory();
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( static function ( string $path, int $mode ): bool {

			if ( 0555 === $mode ) {
				return false;
			}

			return chmod( $path, $mode );
		} );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ '.private', 'uploads' ] );

		$this->assertSame( 'runtime_parent_permissions_restore_failed', $errors->get_error_code() );
		$this->assertDirectoryExists( $parent . '/uploads' );

		$this->assertStringContainsString( $parent, $errors->get_error_message() );
		$this->assertStringContainsString( '0555', $errors->get_error_message() );

	}

	/**
	 * A successful server response is insufficient when the local permission bits were not restored.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_verifies_restored_permissions(): void {
		$parent = $this->protected_parent_directory();
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( static function ( string $path, int $mode ): bool {

			if ( 0777 === $mode ) {
				return chmod( $path, $mode );
			}

			return true;
		} );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ 'uploads' ] );

		$this->assertSame( 'runtime_parent_permissions_restore_failed', $errors->get_error_code() );
	}

	/**
	 * Refused write access restores permissions without creating runtime directories.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directories_writable_by_web_server_reports_refused_permissions(): void {
		$parent = $this->protected_parent_directory();
		$credentials = $this->createMock( Filesystem_Access::class );
		$credentials->method( 'change_permissions' )->willReturnCallback( static function ( string $path, int $mode ): bool {

			if ( 0777 === $mode ) {
				return false;
			}

			return chmod( $path, $mode );
		} );

		$errors = create_directories_writable_by_web_server( $credentials, $parent, [ 'uploads' ] );

		$this->assertSame( 'runtime_parent_permissions_change_failed', $errors->get_error_code() );
		$this->assertSame( 0555, fileperms( $parent ) & 07777 );
		$this->assertDirectoryDoesNotExist( $parent . '/uploads' );
	}

	/**
	 * Languages creation uses the supplied access and sets permissions explicitly.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directory_protected_from_web_server_writes_uses_supplied_access(): void {
		$directory = $this->root . '/.languages';
		$access    = $this->createMock( Filesystem_Access::class );
		$access->expects( $this->once() )->method( 'stream_url_from_path' )->with( $directory )->willReturn( $directory );
		$access->expects( $this->once() )->method( 'stream_context' )->willReturn( null );
		$access->expects( $this->once() )->method( 'change_permissions' )->with( $directory, 0755 )->willReturnCallback( 'chmod' );

		$errors = create_directory_protected_from_web_server_writes( $access, $directory );

		$this->assertFalse( $errors->has_errors() );
		$this->assertDirectoryExists( $directory );
		$this->assertSame( 0755, fileperms( $directory ) & 0777 );
	}

	/**
	 * Existing language directories retain their contents and permissions.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directory_protected_from_web_server_writes_preserves_existing_directory(): void {
		$directory = $this->root . '/.languages';
		mkdir( $directory, 0700 );
		file_put_contents( $directory . '/existing.mo', 'existing translation' );
		$access = $this->createMock( Filesystem_Access::class );
		$access->expects( $this->never() )->method( 'stream_url_from_path' );
		$access->expects( $this->never() )->method( 'change_permissions' );

		$errors = create_directory_protected_from_web_server_writes( $access, $directory );

		$this->assertFalse( $errors->has_errors() );
		$this->assertSame( 'existing translation', file_get_contents( $directory . '/existing.mo' ) );
		$this->assertSame( 0700, fileperms( $directory ) & 0777 );
	}

	/**
	 * A writable leftover directory beneath a protected root requires corrected ownership or permissions.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_create_directory_protected_from_web_server_writes_rejects_writable_existing_directory(): void {
		$root      = $this->protected_parent_directory();
		$directory = $root . '/.languages';
		chmod( $root, 0755 );
		mkdir( $directory, 0755 );
		file_put_contents( $directory . '/existing.mo', 'existing translation' );
		chmod( $root, 0555 );
		$access = $this->createMock( Filesystem_Access::class );
		$access->expects( $this->never() )->method( 'stream_url_from_path' );
		$access->expects( $this->never() )->method( 'change_permissions' );

		$errors = create_directory_protected_from_web_server_writes( $access, $directory );

		$this->assertSame( 'protected_directory_writable', $errors->get_error_code() );
		$this->assertStringContainsString( $directory, $errors->get_error_message() );
		$this->assertSame( 'existing translation', file_get_contents( $directory . '/existing.mo' ) );
		$this->assertSame( 0755, fileperms( $directory ) & 0777 );
	}

	/**
	 * A file occupying the languages path blocks creation without being overwritten.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directory_protected_from_web_server_writes_reports_creation_failure(): void {
		$directory = $this->root . '/.languages';
		file_put_contents( $directory, 'keep' );
		$access = $this->createMock( Filesystem_Access::class );
		$access->method( 'stream_url_from_path' )->willReturn( $directory );
		$access->expects( $this->never() )->method( 'change_permissions' );

		$errors = create_directory_protected_from_web_server_writes( $access, $directory );

		$this->assertSame( 'protected_directory_creation_failed', $errors->get_error_code() );
		$this->assertSame( 'keep', file_get_contents( $directory ) );
	}

	/**
	 * Refused permission changes explain how to set the required directory permissions.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directory_protected_from_web_server_writes_reports_permissions_failure(): void {
		$directory = $this->root . '/.languages';
		$access    = $this->createMock( Filesystem_Access::class );
		$access->method( 'stream_url_from_path' )->willReturn( $directory );
		$access->method( 'change_permissions' )->willReturn( false );

		$errors = create_directory_protected_from_web_server_writes( $access, $directory );

		$this->assertSame( 'protected_directory_permissions_failed', $errors->get_error_code() );
		$this->assertStringContainsString( $directory, $errors->get_error_message() );
		$this->assertStringContainsString( 'Ownership and/or permissions', $errors->get_error_message() );
	}

	/**
	 * Filesystem access exceptions propagate before directory creation.
	 *
	 * @since 1.0.0
	 */
	public function test_create_directory_protected_from_web_server_writes_propagates_connection_failure(): void {
		$directory = $this->root . '/.languages';
		$access    = $this->createMock( Filesystem_Access::class );
		$access->method( 'stream_url_from_path' )->willReturn( $directory );
		$access->method( 'stream_context' )->willThrowException( new RuntimeException( 'Connection failed.' ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Connection failed.' );

		try {
			create_directory_protected_from_web_server_writes( $access, $directory );
		} finally {
			$this->assertDirectoryDoesNotExist( $directory );
		}
	}

}
