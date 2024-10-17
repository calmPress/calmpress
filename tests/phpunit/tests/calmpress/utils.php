<?php
/**
 * Unit tests covering function in the utils.php file
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\utils;

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
}