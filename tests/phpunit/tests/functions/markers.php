<?php
/**
 * Unit tests covering manipulation and extraction of marked text in files.
 * @package calmPress
 * @since 1.0.0
 */

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
	 * @param string[] $res       the expected result.
	 */
	function test_insert_with_markers_into_array( array $orig, string $marker, array $insertion, array $res ) {
		$this->assertEquals( $res, insert_with_markers_into_array( $orig, $marker, $insertion ) );
	}

	/**
	 * Data or the test_insert_with_markers_into_array test.
	 *
	 * @since 1.0.0
	 */
	function data_insert_with_markers_into_array() {
		return [
			[ [], 'mark', ['test'], [
				'# BEGIN mark',
				'test',
				'# END mark',
				],
			],
			[ [], 'mark', ['test', 'more test'], [
				'# BEGIN mark',
				'test',
				'more test',
				'# END mark',
				],
			],
			[ [
				'# BEGIN mark',
				'tost',
				'more tost',
				'# END mark',
			], 'mark', ['test', 'more test'], [
				'# BEGIN mark',
				'test',
				'more test',
				'# END mark',
				],
			],
			[ [
				'# BEGIN mirk',
				'tost',
				'more tost',
				'# END mirk',
			], 'mark', ['test', 'more test'], [
				'# BEGIN mirk',
				'tost',
				'more tost',
				'# END mirk',
				'# BEGIN mark',
				'test',
				'more test',
				'# END mark',
				],
			],
			[ [
				'# BEGIN mark',
				'tost',
				'more tost',
				'# END mark',
			], 'mark', [], [],
			],
			[ [
				'text',
				'# BEGIN mark',
				'tost',
				'more tost',
				'# END mark',
				'more text',
			], 'mark', [], [
				'text',
				'more text'
			],
			],
		];
	}
}
