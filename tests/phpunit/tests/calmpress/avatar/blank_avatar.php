<?php
/**
 * Unit tests covering Blank_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/html_parameter_validation.php';

class Blank_Avatar_Test extends WP_UnitTestCase {
	use Html_Parameter_Validation_Test;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Parameter_Validation_Test trait.
	 *
	 * @since 1.0.0
	 */
	function setUp() {
		$this->avatar = new \calmpress\avatar\Blank_Avatar;
	}

	/**
	 * Test the _html function indirectly by invoking html.
	 *
	 * @since 1.0.0
	 */
	function test_html_generation() {
		$html = $this->avatar->html( 50, 60 );

		/*
		 * Compare strings in a way that will keep the test passing if order changes.
		 */

		$this->assertContains( 'display:inline-block', $html );
		$this->assertContains( 'width:50px', $html );
		$this->assertContains( 'height:60px', $html );
	}

	/**
	 * Test that the filter is executed as part of blank avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_filter() {
		$ret_array = [];
		$html = $this->avatar->html( 50, 60 );

		add_filter( 'calm_blank_avatar_html', function ( $html, $width, $height ) use ( &$ret_array ) {
			$ret_array['html']   = $html;
			$ret_array['width']  = $width;
			$ret_array['height'] = $height;
			return 'tost';
		}, 10, 3 );

		$ret = $this->avatar->html( 50, 60 );

		$this->assertEquals( 'tost', $ret );
		$this->assertEquals( $html, $ret_array['html'] );
		$this->assertEquals( 50, $ret_array['width'] );
		$this->assertEquals( 60, $ret_array['height'] );
	}
}
