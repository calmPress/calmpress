<?php
/**
 * Unit tests covering Locked_File_FTP_Write_Access functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem;

class WP_Test_Locked_File_FTP_Write_Access extends WP_UnitTestCase {

	/**
	 * Make sure files that might have been generated during the tests are removed.
	 *
	 * @since 1.0.0
	 */
	function setUp() {
		parent::setUp();

		$uploads = wp_get_upload_dir();
		$filename = $uploads['path'] . '/calmpress-test';
		@chmod( $filename, 0777 );
		@unlink( $filename );

		$filename .= '-2';
		@chmod( $filename, 0777 );
		@unlink( $filename );
	}

	/**
	 * Mark tests to be skipped if there is not enough information to connect
	 * to an FTP server.
	 *
	 * @since 1.0.0
	 */
	function maybe_skip_test() {
		if ( ! defined( 'FTP_USER' ) || ! defined( 'FTP_PASS' ) || ! defined( 'FTP_HOST' ) || ! defined( 'FTP_BASE' ) ) {
			$this->markTestSkipped( 'FTP is not configured.' );
		}
	}

	/**
	 * Create test file in the uploads directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name    The name of the file to be created in the uploads directory.
	 * @param string $content The content to populate the file with.
	 *
	 * @return string the path to the created file.
	 */
	function create_test_file( $name, $content ) {
		$uploads = wp_get_upload_dir();
		$file = $uploads['path'] . '/' . $name;
		file_put_contents( $file, $content );
		return $file;
	}

	/**
	 * Helper function to create the object based on the relevant defines.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file_path The path of the file being locked.
	 *
	 * @return Locked_File_FTP_Write_Access Locked file object.
	 */
	function get_object( string $file_path ) {
		$host_parts = explode( ':', FTP_HOST );
		$host = $host_parts[0];
		$port = 0;
		if ( 2 === count( $host_parts ) ) {
			$port = (int) $host_parts[1];
		}
		return new calmpress\filesystem\Locked_File_FTP_Write_Access( $file_path,
			$host,
			$port,
			FTP_USER,
			FTP_PASS,
			FTP_BASE
		);
	}

	/**
	 * Test file_copy.
	 *
	 * @since 1.0.0
	 */
	function test_file_copy() {
		$this->maybe_skip_test();
		$filename = $this->create_test_file( 'calmpress-test', 'test' );

		$t = $this->get_object( $filename );
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
		$this->maybe_skip_test();
		$filename = $this->create_test_file( 'calmpress-test', 'test' );

		$t = $this->get_object( $filename );
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
		$this->maybe_skip_test();
		$filename = $this->create_test_file( 'calmpress-test', 'test' );

		$t = $this->get_object( $filename );
		$t->unlink();
		$this->assertFalse( file_exists( $filename ) );

		// Test the exception is raised when unlink fails.
		$this->maybe_skip_test();
		$filename = $this->create_test_file( 'calmpress-test', 'test' );
		chmod( $filename, 0400 );

		$t = $this->get_object( $filename );
		$this->expectException( 'calmpress\filesystem\Locked_File_Exception' );
		$this->expectExceptionCode( calmpress\filesystem\Locked_File_Exception::OPERATION_FAILED );
		$t->unlink();
	}

	/**
	 * Test file_put_contents.
	 *
	 * @since 1.0.0
	 */
	function test_put_contents() {

		$this->maybe_skip_test();
		// Test a new file is create where there was no file.
		$uploads = wp_get_upload_dir();
		$filename = $uploads['path'] . '/calmpress-test';
		$t = $this->get_object( $filename );
		$t->put_contents( 'direct put_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'direct put_contents', file_get_contents( $filename ) );

		// Test a old content is overwritten.
		$this->maybe_skip_test();
		$filename = $this->create_test_file( 'calmpress-test', 'test' );
		$t = $this->get_object( $filename );
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
		$this->maybe_skip_test();
		$uploads = wp_get_upload_dir();
		$filename = $uploads['path'] . '/calmpress-test';
		$t = $this->get_object( $filename );
		$t->append_contents( 'direct append_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'direct append_contents', file_get_contents( $filename ) );

		// Test a old content is overwritten.
		$this->maybe_skip_test();
		$filename = $this->create_test_file( 'calmpress-test', 'test' );
		file_put_contents( $filename, 'test' );
		$t = $this->get_object( $filename );
		$t->append_contents( 'direct append_contents' );
		// release the lock might be needed for OS which do actually system wide
		// locking.
		$t = null;
		$this->assertEquals( 'testdirect append_contents', file_get_contents( $filename ) );
	}
}
