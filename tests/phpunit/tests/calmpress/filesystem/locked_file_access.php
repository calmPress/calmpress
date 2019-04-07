<?php
/**
 * Unit tests covering Locked_File_Access functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem;

/**
 * Implementation of an the Locked_File_Access class that help in testing
 * how it uses the abstract functions.
 *
 * @since 1.0.0
 */
class abstract_test extends calmpress\filesystem\Locked_File_Access {

	/**
	 * Holds a value indicating which abstract implementation was called.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $abstract_called = '';

	/**
	 * Implementation of the abstract function that indicates it was called.
	 *
	 * @since 1.0.0
	 */
	public function file_copy( string $destination ) {
		$this->abstract_called = 'copy';
	}

	/**
	 * Implementation of the abstract function that indicates it was called.
	 *
	 * @since 1.0.0
	 */
	public function file_rename( string $destination ) {
		$this->abstract_called = 'rename';
	}

	/**
	 * Implementation of the abstract function that indicates it was called.
	 *
	 * @since 1.0.0
	 */
	public function file_unlink() {
		$this->abstract_called = 'unlink';
	}

	/**
	 * Implementation of the abstract function that indicates it was called.
	 *
	 * @since 1.0.0
	 */
	public function put_contents( string $contents ) {
		$this->abstract_called = 'put';
	}

	/**
	 * Implementation of the abstract function that indicates it was called.
	 *
	 * @since 1.0.0
	 */
	public function append_contents( string $contents ) {
		$this->abstract_called = 'append';
	}

	/**
	 * Return the file used for locking.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path of the file.
	 */
	public function lockfile() {
		$hash     = md5( $this->file_path );
		$filename = get_temp_dir() . 'calmpress-filelock-' . $hash;
		return $filename;
	}
}

/**
 * Implementation of an the Locked_File_Access class that help in testing
 * how it reacts to abstract functions raising exceptions.
 *
 * @since 1.0.0
 */
class abstract_exceptions_test extends calmpress\filesystem\Locked_File_Access {

	/**
	 * Implementation of the abstract copy function that raises exception.
	 *
	 * @since 1.0.0
	 */
	public function file_copy( string $destination ) {
		throw new calmpress\filesystem\Locked_File_Exception( 'test', calmpress\filesystem\Locked_File_Exception::OPERATION_FAILED );
	}

	/**
	 * Implementation of the abstract rename function that raises exception.
	 *
	 * @since 1.0.0
	 */
	public function file_rename( string $destination ) {
		throw new calmpress\filesystem\Locked_File_Exception( 'test', calmpress\filesystem\Locked_File_Exception::OPERATION_FAILED );
	}

	public function file_unlink() {}
	public function put_contents( string $contents) {}
	public function append_contents( string $contents) {}
}

class WP_Test_Locked_File_access extends WP_UnitTestCase {

	/**
	 * Data provider of non absolute paths to test against.
	 *
	 * @since 1.0.0
	 */
	public function non_absolute_paths() {
		return [
			[''],
			['.'],
			['..'],
			['../foo'],
			['../'],
			['../foo.bar'],
			['foo/bar'],
			['foo'],
			['FOO'],
			['~foo'],
			['..\\WINDOWS'],
		];
	}

	/**
	 * Data provider of valid absolute paths to test against.
	 *
	 * @since 1.0.0
	 */
	public function absolute_paths() {
		return [
			['/'],
			['/foo/'],
			['/foo'],
			['/FOO/bar'],
			['/foo/bar/'],
			['/foo/../bar/'],
			['\\WINDOWS'],
			['C:\\'],
			['C:\\WINDOWS'],
			['\\\\sambashare\\foo'],
		];
	}

	/**
	 * Test constructor raise exception on non absolute paths.
	 *
	 * @dataProvider non_absolute_paths
	 *
	 * @since 1.0.0
	 */
	function test_constructor_exception_non_absulute_path( $path ) {

		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$this->expectExceptionCode( calmpress\filesystem\Locked_File_Exception::PATH_NOT_ABSOLUTE );
		$this->expectExceptionMessage( '"' . $path . '"' );

		new abstract_test( $path );
	}

	/**
	 * Test constructor work with absolute paths.
	 *
	 * @dataProvider absolute_paths
	 *
	 * @since 1.0.0
	 */
	function test_constructor_absulute_path( $path ) {
		$this->assertNotEmpty( new abstract_test( $path ) );
	}

	/**
	 * Test locked raise exception on non absolute paths.
	 *
	 * @dataProvider non_absolute_paths
	 *
	 * @since 1.0.0
	 */
	function test_locked_exception_non_absulute_path( $path ) {

		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$this->expectExceptionCode( calmpress\filesystem\Locked_File_Exception::PATH_NOT_ABSOLUTE );
		$this->expectExceptionMessage( '"' . $path . '"' );

		calmpress\filesystem\Locked_File_Access::locked( $path );
	}

	/**
	 * Test locked accept absolute paths, and that it reports they have no lock.
	 *
	 * @dataProvider absolute_paths
	 *
	 * @since 1.0.0
	 */
	function test_locked_absulute_path( $path ) {

		$this->assertFalse( calmpress\filesystem\Locked_File_Access::locked( $path ) );
	}

	/**
	 * Test constructor creates a lock file.
	 *
	 * @since 1.0.0
	 */
	function test_constructor_creates_lock() {
		// Check if the file is created.
		$t = new abstract_test( '/foo/foo' );
		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/foo' ) );

		// Check that it is locked.
		$lockfile = $t->lockfile();
		$fp = fopen( $lockfile, 'r' );
		flock( $fp, LOCK_EX | LOCK_NB, $wouldblock );
		flock( $fp, LOCK_UN );
		fclose( $fp );
		$this->assertEquals( 1, $wouldblock );
	}

	/**
	 * Test destructor destroys a lock file.
	 *
	 * @since 1.0.0
	 */
	function test_destructor_destroys_lock() {
		$t = new abstract_test( '/foo/foo' );
		$t = null;
		$this->assertFalse( calmpress\filesystem\Locked_File_Access::locked( '/foo/foo' ) );
	}

	/**
	 * Test that abstract functions are called appropriately.
	 *
	 * @since 1.0.0
	 */
	function test_abstract_called() {
 		$t = new abstract_test( '/foo/foo' );

		$t->copy( '/foo1' );
 		$this->assertEquals( 'copy', $t->abstract_called );

		$t->rename( '/foo2' );
 		$this->assertEquals( 'rename', $t->abstract_called );

		$t->put_contents( 'test' );
 		$this->assertEquals( 'put', $t->abstract_called );

		$t->append_contents( 'test' );
 		$this->assertEquals( 'append', $t->abstract_called );

		// When file do not exist file_unlink should not be called.
		$t->abstract_called = '';
		$t->unlink();
 		$this->assertEquals( '', $t->abstract_called );

		// ... but it should be called when a file exists.
		$filename = get_temp_dir() . 'calmpress-test';
		$fp = fopen( $filename , 'w+' );
		fclose( $fp );
		$t = new abstract_test( $filename );
		$t->unlink();
 		$this->assertEquals( 'unlink', $t->abstract_called );
 	}

	/**
	 * Test that copy creates a new lock file.
	 *
	 * @since 1.0.0
	 */
	function test_copy_creates_locked_file() {
		$t = new abstract_test( '/foo/bar' );
		$t2 = $t->copy( '/foo/bar2' );
		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar' ) );
		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar2' ) );

	}

	/**
	 * Test that rename creates a new locked file
	 *
	 * @since 1.0.0
	 */
	function test_rename_locked_file() {
		$t = new abstract_test( '/foo/bar' );
		$t2 = $t->rename( '/foo/bar2' );
		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar' ) );
		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar2' ) );

	}

	/**
	 * Test get_contents
	 *
	 * @since 1.0.0
	 */
	function test_get_contents() {
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );

		$t = new abstract_test( $filename );
 		$this->assertEquals( 'test', $t->get_contents() );
	}

	/**
	 * Test copy fail handling.
	 *
	 * @since 1.0.0
	 */
	function test_copy_fail_handling() {
		// Test there is no looking for the new file.
		$t = new abstract_exceptions_test( '/foo/bar' );
		try {
			$t2 = $t->copy( '/foo/bar2' );
		} catch ( \Exception $e ) {
		}

		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar' ) );
		$this->assertFalse( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar2' ) );

		// Test the exception is raised.
		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$t2 = $t->copy( '/foo/bar2' );
	}

	/**
	 * Test rename fail handling.
	 *
	 * @since 1.0.0
	 */
	function test_rename_fail_handling() {
		// Test there is no looking for the new file.
		$t = new abstract_exceptions_test( '/foo/bar' );
		try {
			$t2 = $t->rename( '/foo/bar2' );
		} catch ( \Exception $e ) {
		}

		$this->assertTrue( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar' ) );
		$this->assertFalse( calmpress\filesystem\Locked_File_Access::locked( '/foo/bar2' ) );

		// Test the exception is raised.
		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$t2 = $t->rename( '/foo/bar2' );
	}
}
