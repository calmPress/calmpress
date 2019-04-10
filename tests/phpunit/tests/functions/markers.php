<?php
/**
 * Unit tests covering manipulation and extraction of marked text in files.
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * A simplistic Locaked_File_Access that is tailored for the needs of the tests.
 *
 * @since 1.0.0
 */
class Dummy_Locked_File_Access extends \calmpress\filesystem\Locked_File_Access {
	/**
	 * Used to simulate the content of the file if there was one.
	 *
	 * var string.
	 *
	 * @since 1.0.0
	 */
	public $content = '';

	public function __construct() {}
	public function __destruct() {}
	protected function file_copy( string $destination ) {}
	protected function file_rename( string $destination ) {}
	protected function file_unlink() {}

	public function get_contents() {
		return $this->content;
	}

	public function put_contents( string $contents ) {
		$this->content = $contents;
	}

	public function append_contents( string $contents ) {
		$this->content .= $contents;
	}
}

/**
 * Dummy class that generates exceptions.
 *
 * @since 1.0.0
 */
class Exception_Dummy_Locked_File_Access extends Dummy_Locked_File_Access {
	public function put_contents( string $contents ) {
		throw new calmpress\filesystem\Locked_File_Exception( 'exception message', 0, '/foo' );
	}
}

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
	 * Test the insert_with_markers function.
	 *
	 * @since 1.0.0
	 */
	function test_insert_with_markers() {
		$locked_file = new Dummy_Locked_File_Access();
		add_filter( 'calm_insert_with_markers_locked_file_getter', function () use ($locked_file ) {
			return function ( $filename ) use ($locked_file ) {
				return $locked_file;
			};
		} );

		// Test markers with empty content.
		$ret = insert_with_markers( 'testfile', 'test', 'test string' );
		$this->assertTrue( $ret );
		$this->assertEquals( "\n# BEGIN test\ntest string\n# END test", $locked_file->content );

		// Test markers with some content (from previous test).
		$ret = insert_with_markers( 'testfile', 'test', 'another string' );
		$this->assertTrue( $ret );
		$this->assertEquals( "\n# BEGIN test\nanother string\n# END test", $locked_file->content );
	}

	/**
	 * Test that the action calm_insert_with_markers_exception is triggered when
	 * exception happens in insert_with_markers.
	 *
	 * @since 1.0.0
	 */
	function test_calm_insert_with_markers_exception() {
		$locked_file = new Exception_Dummy_Locked_File_Access();
		add_filter( 'calm_insert_with_markers_locked_file_getter', function () use ($locked_file ) {
			return function ( $filename ) use ($locked_file ) {
				return $locked_file;
			};
		} );

		$called = false;
		add_action( 'calm_insert_with_markers_exception', function ( $exception ) use ( &$called ) {
			$called = true;
			echo 'ooo';
		}, 10, 1 );

		// Test markers with empty content.
		$ret = insert_with_markers( 'testfile', 'test', 'test string' );
		$this->assertFalse( $ret );
		$this->assertTrue( $called );
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
