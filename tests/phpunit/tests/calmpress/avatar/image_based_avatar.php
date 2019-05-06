<?php
/**
 * Unit tests covering the Image_Based_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/html_parameter_validation.php';

class Image_Based_Avatar_Test extends WP_UnitTestCase {
	use Html_Parameter_Validation_Test;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Parameter_Validation_Test trait.
	 *
	 * @since 1.0.0
	 */
	function setUp() {
		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$this->attachment = $attachment_id;
		$this->avatar = new \calmpress\avatar\Image_Based_Avatar( get_post( $attachment_id ) );
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

		$this->assertContains( "width='50'", $html );
		$this->assertContains( "height='60'", $html );

		// Check image is there.
		$this->assertContains( 'canola', $html );

		// Test blank avatar html is returned when there is no image.
		// To do that delete the original image.
		wp_delete_post( $this->attachment );
		$blank = new \calmpress\avatar\Blank_Avatar();
		$this->assertEquals( $blank->html( 50, 50 ), $this->avatar->html( 50, 50 ) );
	}

	/**
	 * Test that the filter is executed as part of image avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_filter() {
		$ret_array = [];
		$html = $this->avatar->html( 50, 60 );

		add_filter( 'calm_image_based_avatar_html', function ( $html, $attachment_id, $width, $height ) use ( &$ret_array ) {
			$ret_array['html']          = $html;
			$ret_array['attachment_id'] = $attachment_id;
			$ret_array['width']         = $width;
			$ret_array['height']        = $height;
			return 'tost';
		}, 10, 4 );

		$ret = $this->avatar->html( 50, 60 );

		$this->assertEquals( 'tost', $ret );
		$this->assertEquals( $html, $ret_array['html'] );
		$this->assertEquals( $this->avatar->attachment()->ID, $ret_array['attachment_id'] );
		$this->assertEquals( 50, $ret_array['width'] );
		$this->assertEquals( 60, $ret_array['height'] );
	}
}
