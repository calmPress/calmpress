<?php
/**
 * Unit tests covering Locked_File_Direct_Access functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem;

class WP_Test_Locked_File_Direct_Access extends WP_UnitTestCase {

	/**
	 * Make sure files that might have been generated during the tests are removed.
	 *
	 * @since 1.0.0
	 */
	function setUp() {
		parent::setUp();

		$filename = get_temp_dir() . 'calmpress-test';
		@chmod( $filename, 0777 );
		@unlink( $filename );

		$filename .= '-2';
		@chmod( $filename, 0777 );
		@unlink( $filename );
	}

	/**
	 * Test file_copy.
	 *
	 * @since 1.0.0
	 */
	function test_file_copy() {
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );

		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$t->copy( $filename . '-2' );
		$this->assertTrue( file_exists( $filename ) );
		$this->assertEquals( 'test', file_get_contents( $filename . '-2' ) );

		// Test the exception is raised when copy fails.
		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$this->expectExceptionCode( calmpress\filesystem\Locked_File_Exception::OPERATION_FAILED );
		$t->copy( $filename . '/2' );
	}

	/**
	 * Test file_rename.
	 *
	 * @since 1.0.0
	 */
	function test_file_rename() {
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );

		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$renamed = $t->rename( $filename . '-2' );
		$this->assertFalse( file_exists( $filename ) );
		$this->assertEquals( 'test', file_get_contents( $filename . '-2' ) );

		// Test the exception is raised when rename fails.
		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$this->expectExceptionCode( calmpress\filesystem\Locked_File_Exception::OPERATION_FAILED );
		$renamed->rename( $filename . '/2' );
	}

	/**
	 * Test file_unlink.
	 *
	 * @since 1.0.0
	 */
	function test_file_unlink() {
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );

		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$t->unlink();
		$this->assertFalse( file_exists( $filename ) );

		// Test the exception is raised when unlink fails.
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );

		// Effectively avoid the test when chmod behaving funny (travis, looking at you).
		if ( chmod( $filename, 0444 ) ) {
			$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
			$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
			$this->expectExceptionCode( calmpress\filesystem\Locked_File_Exception::OPERATION_FAILED );
			$t->unlink();
		}
	}

	/**
	 * Test file_put_contents.
	 *
	 * @since 1.0.0
	 */
	function test_put_contents() {

		// Test a new file is create where there was no file.
		$filename = get_temp_dir() . 'calmpress-test';
		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$t->put_contents( 'direct put_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'direct put_contents', file_get_contents( $filename ) );

		// Test a old content is overwritten.
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );
		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$t->put_contents( 'direct put_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'direct put_contents', file_get_contents( $filename ) );
	}

	/**
	 * Test file_append_contents.
	 *
	 * @since 1.0.0
	 */
	function test_append_contents() {

		// Test a new file is create where there was no file.
		$filename = get_temp_dir() . 'calmpress-test';
		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$t->append_contents( 'direct append_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'direct append_contents', file_get_contents( $filename ) );

		// Test a old content is overwritten.
		$filename = get_temp_dir() . 'calmpress-test';
		file_put_contents( $filename, 'test' );
		$t = new calmpress\filesystem\Locked_File_Direct_Access( $filename );
		$t->append_contents( 'direct append_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'testdirect append_contents', file_get_contents( $filename ) );
	}

}
