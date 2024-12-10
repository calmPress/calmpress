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

class WP_Test_Utils extends WP_UnitTestCase {

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
}