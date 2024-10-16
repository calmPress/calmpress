<?php
/**
 * Unit tests covering the Image_Based_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/html_generation_helper.php';
require_once ABSPATH . '/wp-admin/includes/image.php';

use calmpress\avatar\Image_Based_Avatar_Attributes_Mutator;
use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;
use \calmpress\avatar\Image_Based_Avatar;

class Mock_Image_Mutator implements Image_Based_Avatar_Attributes_Mutator {

	public static int $size;
	public static int $attachment_id;

	public function notification_dependency_with( Observer $observer ): Observer_Priority	{
		return Observer_Priority::NONE;
	}

	public function mutate( array $attr, \WP_Post $attachment, int $size ): array {
		self::$size          = $size;
		self::$attachment_id = $attachment->ID;
		$attr['tost'] = 1;
		return $attr;
	}

}

class Image_Based_Avatar_Test extends WP_UnitTestCase {
	use Html_Generation_Helper_Test;

	/**
	 * hold the attachment id to be used in tests.
	 */
	private int $attachment;

	/**
	 * Hold the image based avatar to be used in test.
	 */
	private Image_Based_Avatar $avatar;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Generation_Helper_Test trait.
	 *
	 * @since 1.0.0
	 */
	function set_up() {
		parent::set_up();

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$this->attachment = $attachment_id;
		$this->avatar = new Image_Based_Avatar( get_post( $attachment_id ) );
	}

	function tear_down() {
		wp_delete_post( $this->attachment, true );
		parent::tear_down();
	}

	/**
	 * Test the _html function indirectly by invoking html.
	 *
	 * @since 1.0.0
	 */
	function test_html_generation() {
		$html = $this->avatar->html( 50 );

		/*
		 * Compare strings in a way that will keep the test passing if order changes.
		 */

		$this->assertStringContainsString( 'width="50"', $html );
		$this->assertStringContainsString( 'height="50"', $html );

		// Check image is there.
		$this->assertStringContainsString( 'canola', $html );

		// Test blank avatar html is returned when there is no image.
		// To do that delete the original image.
		wp_delete_post( $this->attachment );
		$blank = new \calmpress\avatar\Blank_Avatar();
		$this->assertEquals( $blank->html( 50 ), $this->avatar->html( 50 ) );
	}

	/**
	 * Test that the mutaturs are executed as part of image avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_mutator() {

		$ret = $this->avatar->html( 50 );

		Image_Based_Avatar::register_generated_attributes_mutator( new Mock_Image_Mutator() );		
		$html = $this->avatar->html( 50 );

		$this->assertStringContainsString( 'tost="1"', $html );
		$this->assertSame( 50, Mock_Image_Mutator::$size );
		$this->assertSame( $this->avatar->attachment()->ID, Mock_Image_Mutator::$attachment_id );
	}
}
