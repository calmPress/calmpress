<?php
/**
 * Unit tests covering Path_Lock functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Path_Lock;

/**
 * A Path_Lock with utility functions to access data for testing.
 */
class test_lock extends Path_Lock {

	/**
	 * The file on which the lock is implemented
	 * 
	 * @since 1.0.0
	 * 
	 * @return string The lock file path or empty string if there isn't one.
	 */
	public function lock_file() : string {
		if ( null === $this->hash_file_fp ) {
			return '';
		}
		$data = stream_get_meta_data( $this->hash_file_fp );
		return $data['uri'];
	}
}

class WP_Test_Path_Lock extends WP_UnitTestCase {

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

		$this->expectException( '\DomainException' );
		$this->expectExceptionMessage( '"' . $path . '"' );

		new Path_Lock( $path );
	}

	/**
	 * Test constructor work with absolute paths.
	 *
	 * @dataProvider absolute_paths
	 *
	 * @since 1.0.0
	 */
	function test_constructor_absulute_path( $path ) {
		$this->assertNotEmpty( new Path_Lock( $path ) );
	}

	/**
	 * Test constructor creates a lock file.
	 *
	 * @since 1.0.0
	 */
	function test_constructor_creates_lock() {
		// Check if the file is created.
		$t = new test_lock( '/foo/foo' );
		$lockfile = $t->lock_file();

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
		$t = new test_lock( '/foo/foo' );
		$lockfile = $t->lock_file();
		$t = null;
		$this->assertFalse( file_exists( $lockfile ) );
	}
}
