<?php
/**
 * Unit tests covering Text_Based_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/html_parameter_validation.php';

class Text_Based_Avatar_Test extends WP_UnitTestCase {
	use Html_Parameter_Validation_Test;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Parameter_Validation_Test trait.
	 *
	 * @since 1.0.0
	 */
	function setUp() {
		$this->avatar = new \calmpress\avatar\Text_Based_Avatar( 'test for best', 't@test.com'  );
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

		// Check text.
		$this->assertContains( '>TB<', $html );

		// Test different color factor result with different color (indirectly via html).
		$avatar2 = new \calmpress\avatar\Text_Based_Avatar( 'test for best', 't@calm.com' );
		$this->assertNotEquals( $this->avatar->html( 50, 50 ), $avatar2->html( 50, 50 ) );

		// Test text on width smaller than 40 px.
		$html = $this->avatar->html( 30, 60 );
		$this->assertContains( '>T<', $html );

		// Test blank avatar html is returned when no primary text is given.
		$avatar2 = new \calmpress\avatar\Text_Based_Avatar( '', 't@testi.com' );
		$blank = new \calmpress\avatar\Blank_Avatar();
		$this->assertEquals( $blank->html( 50, 50 ), $avatar2->html( 50, 50 ) );
	}

	/**
	 * Test that the filter is executed as part of text avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_filter() {
		$ret_array = [];
		$html = $this->avatar->html( 50, 60 );

		add_filter( 'calm_text_based_avatar_html', function ( $html, $text, $color_factor, $width, $height ) use ( &$ret_array ) {
			$ret_array['html']         = $html;
			$ret_array['text']         = $text;
			$ret_array['color_factor'] = $color_factor;
			$ret_array['width']        = $width;
			$ret_array['height']       = $height;
			return 'tost';
		}, 10, 5 );

		$ret = $this->avatar->html( 50, 60 );

		$this->assertEquals( 'tost', $ret );
		$this->assertEquals( $html, $ret_array['html'] );
		$this->assertEquals( 'test for best', $ret_array['text'] );
		$this->assertEquals( 't@test.com', $ret_array['color_factor'] );
		$this->assertEquals( 50, $ret_array['width'] );
		$this->assertEquals( 60, $ret_array['height'] );
	}
}
