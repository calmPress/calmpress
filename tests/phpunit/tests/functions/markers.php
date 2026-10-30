<?php
/**
 * Unit tests covering manipulation and extraction of marked text in files.
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Marker_File_Exception;

require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/file.php';

class Tests_Functions_Markers extends WP_UnitTestCase {

	/**
	 * Test the insert_with_markers_into_array function.
	 * @dataProvider data_insert_with_markers_into_array
	 *
	 * @since 1.0.0
	 *
	 * @param string[] $orig      Array to alter.
	 * @param string   $marker    The marker.
	 * @param string[] $insertion The new content to insert.
	 * @param string   $prefix    The line prefix.
	 * @param string[] $res       the expected result.
	 */
	function test_insert_with_markers_into_array( array $orig, string $marker, array $insertion, string $prefix, array $res ) {
		$this->assertEquals( $res, insert_with_markers_into_array( $orig, $marker, $insertion, $prefix) );
	}

	/**
	 * Test that a local file can be used.
	 * 
	 * @since 1.0.0
	 */
	function test_insert_with_markers_file_path() {
		$filename = wp_tempnam();
		$ret = insert_with_markers( $filename, 'test', 'test string' );
		$this->assertTrue( $ret );
		$this->assertSame( "\n# BEGIN test\ntest string\n# END test", file_get_contents( $filename ) );
		unlink( $filename );
	}

	/**
	 * Test that insert_with_markers writes marked content through a file stream URL.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers_stream_url() {
		$filename = wp_tempnam();

		try {
			$this->assertTrue( insert_with_markers( 'file://' . wp_normalize_path( $filename ), 'test', 'test string' ) );
			$this->assertSame( "\n# BEGIN test\ntest string\n# END test", file_get_contents( $filename ) );
		} finally {
			unlink( $filename );
		}
	}

	/**
	 * Test that insert_with_markers reports a failed stream read.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers_reports_stream_read_failure() {
		$filename = wp_tempnam();
		unlink( $filename );

		$this->expectException( Marker_File_Exception::class );
		$this->expectExceptionCode( Marker_File_Exception::READ_FAILED );

		insert_with_markers( 'file://' . wp_normalize_path( $filename ), 'test', 'test string' );
	}

	/**
	 * Test that insert_with_markers reports a failed local file read.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers_reports_local_read_failure() {
		$filename = wp_tempnam();
		unlink( $filename );

		$this->expectException( Marker_File_Exception::class );
		$this->expectExceptionCode( Marker_File_Exception::READ_FAILED );

		insert_with_markers( $filename, 'test', 'test string' );
	}

	/**
	 * Test that insert_with_markers reports a failed stream write.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers_reports_stream_write_failure() {
		$this->expectException( Marker_File_Exception::class );
		$this->expectExceptionCode( Marker_File_Exception::WRITE_FAILED );

		insert_with_markers( 'data://text/plain,original', 'test', 'test string' );
	}

	/**
	 * Test the insert_with_markers function with default line prefix.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers_default_prefix() {
		$filename = wp_tempnam();

		// Test markers with empty content, default prefix.
		$ret = insert_with_markers( $filename, 'test', 'test string' );
		$this->assertTrue( $ret );
		$this->assertEquals( "\n# BEGIN test\ntest string\n# END test", file_get_contents( $filename ) );

		// Test markers with some content (from previous test).
		$ret = insert_with_markers( $filename, 'test', 'another string' );
		$this->assertTrue( $ret );
		$this->assertEquals( "\n# BEGIN test\nanother string\n# END test", file_get_contents( $filename ) );
		unlink( $filename );
	}

	/**
	 * Test the insert_with_markers function with explicit line prefix.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers_explicit_prefix() {
		$filename = wp_tempnam();

		// Test markers with empty content, non default prefix.
		$ret = insert_with_markers( $filename, 'test', 'test string', '//' );
		$this->assertTrue( $ret );
		$this->assertEquals( "\n// BEGIN test\ntest string\n// END test", file_get_contents( $filename ) );

		// Test markers with some content (from previous test).
		$ret = insert_with_markers( $filename, 'test', 'another string', '//' );
		$this->assertTrue( $ret );
		$this->assertEquals( "\n// BEGIN test\nanother string\n// END test", file_get_contents( $filename ) );
		unlink( $filename );
	}

	/**
	 * Data or the test_insert_with_markers_into_array test.
	 *
	 * @since 1.0.0
	 */
	function data_insert_with_markers_into_array() {
		return [
			[ [], 'mark', ['test'], '#', [
				'# BEGIN mark',
				'test',
				'# END mark',
				],
			],
			[ [], 'mark', ['test', 'more test'], '//', [
				'// BEGIN mark',
				'test',
				'more test',
				'// END mark',
				],
			],
			[ [
				'# BEGIN mark',
				'tost',
				'more tost',
				'# END mark',
			], 'mark', ['test', 'more test'], '#', [
				'# BEGIN mark',
				'test',
				'more test',
				'# END mark',
				],
			],
			[ [
				'// BEGIN mirk',
				'tost',
				'more tost',
				'// END mirk',
			], 'mark', ['test', 'more test'], '//', [
				'// BEGIN mirk',
				'tost',
				'more tost',
				'// END mirk',
				'// BEGIN mark',
				'test',
				'more test',
				'// END mark',
				],
			],
			[ [
				'# BEGIN mark',
				'tost',
				'more tost',
				'# END mark',
			], 'mark', [], '#', [],
			],
			[ [
				'text',
				'# BEGIN mark',
				'tost',
				'more tost',
				'# END mark',
				'more text',
			], 'mark', [], '#', [
				'text',
				'more text'
			],
			],
		];
	}
}
