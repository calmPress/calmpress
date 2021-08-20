<?php
/**
 * Unit tests covering manipulation and extraction of marked text in files.
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

require_once ABSPATH . 'wp-admin\includes\misc.php';
require_once ABSPATH . 'wp-admin\includes\file.php';

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
