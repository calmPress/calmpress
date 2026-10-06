<?php
/**
 * Unit tests covering function in the utils.php file
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\utils;

use function calmpress\utils\base64URL_decode;
use function calmpress\utils\base64URL_encode;
use function calmpress\utils\insert_style_into_html_head;

class WP_Test_Utils extends WP_UnitTestCase {

	/**
	 * Verify empty_directory removes nested contents while preserving the root directory.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_directory_preserves_root() {
		$directory = get_temp_dir() . 'empty-directory-' . wp_generate_uuid4();
		mkdir( $directory . '/nested', 0777, true );
		file_put_contents( $directory . '/file', 'contents' );
		file_put_contents( $directory . '/nested/file', 'contents' );

		utils\empty_directory( $directory );

		$this->assertDirectoryExists( $directory );
		$this->assertSame( array(), glob( $directory . '/*' ) );
		rmdir( $directory );
	}

	/**
	 * Verify delete_directory removes a complete nested directory tree.
	 *
	 * @since 1.0.0
	 */
	public function test_delete_directory_removes_root() {
		$directory = get_temp_dir() . 'delete-directory-' . wp_generate_uuid4();
		mkdir( $directory . '/nested', 0777, true );
		file_put_contents( $directory . '/nested/file', 'contents' );

		utils\delete_directory( $directory );

		$this->assertDirectoryDoesNotExist( $directory );
	}

	/**
	 * Verify directory deletion utilities reject paths which are not directories.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_directory_rejects_non_directory() {
		$file = get_temp_dir() . 'empty-directory-file-' . wp_generate_uuid4();
		file_put_contents( $file, 'contents' );

		try {
			utils\empty_directory( $file );
			$this->fail( 'Emptying a file path must fail.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'The path is not a directory: ' . $file, $exception->getMessage() );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Verify empty_directory removes a symbolic link without touching its target.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_directory_does_not_follow_symbolic_links() {
		$directory = get_temp_dir() . 'empty-directory-links-' . wp_generate_uuid4();
		$target    = get_temp_dir() . 'empty-directory-target-' . wp_generate_uuid4();
		mkdir( $directory );
		mkdir( $target );
		file_put_contents( $target . '/file', 'contents' );
		if ( ! @symlink( $target, $directory . '/link' ) ) {
			rmdir( $directory );
			unlink( $target . '/file' );
			rmdir( $target );
			$this->markTestIncomplete( 'Symbolic links are unavailable on this system.' );
		}

		utils\empty_directory( $directory );

		$this->assertFileExists( $target . '/file' );
		$this->assertFileDoesNotExist( $directory . '/link' );
		rmdir( $directory );
		unlink( $target . '/file' );
		rmdir( $target );
	}

	/**
	 * Test the enqueue_inline_style_once function.
	 *
	 * @since 1.0.0
	 */
	function test_enqueue_inline_style_once() {

		utils\enqueue_inline_style_once( 'handle', 'a {color:red}' );

		// Inspect that the inline style was enqueued
		$wp_styles = wp_styles();
		$this->assertTrue( wp_style_is( 'handle', 'enqueued' ) );

		// Check that the inline style is added
		$this->assertNotEmpty( $wp_styles->get_data( 'handle', 'after' ) );

		// Call the function again to ensure the style is not enqueued twice
		utils\enqueue_inline_style_once( 'handle', 'a {color:red}' );

		// Confirm that it's still only enqueued once
		$inline_styles = $wp_styles->get_data( 'handle', 'after' );
		$this->assertCount( 1, $inline_styles );

		wp_dequeue_style( 'handle' );
	}

	/**
	 * Test base64URL decode an encode
	 * 
	 * @since 1.0.0
	 */
	function test_base64url() {

		// simple string
		$en = base64URL_encode( 'dummy' );
		$this->assertSame( 'dummy', base64URL_decode( $en ) );

		// A string with some invalid URL characters when base64 encoded.
		$en = base64URL_encode( "\xFA\xFB\xF" );
		$this->assertSame( "\xFA\xFB\xF", base64URL_decode( $en ) );

		// String not matching base64URL format.
		$de = base64URL_decode( 'pQECAyYgASFYIF6t9Oa1Z3ZL3mYjiHU5j9z6Bk3j+8m5UwxyZZ9S_ZUIlgg==INVALID==' );
		$this->assertFalse( $de );
	}

	/**
	 * test insert_style_into_html_head actually inserts enqueued styles
	 */
	function test_insert_style_into_html_head() {
		wp_register_style( 'test', false ); // 'false' means no external file, just inline
		wp_add_inline_style( 'test', 'a {color:red}' );
		wp_enqueue_style( 'test' );

		$html = '<head></head><body></body>';
		$t = insert_style_into_html_head( $html );
		$this->assertStringContainsString( 'a {color:red}', $t );
		wp_dequeue_style( 'test' );
	}
}
