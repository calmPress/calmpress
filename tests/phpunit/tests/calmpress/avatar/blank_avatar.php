<?php
/**
 * Unit tests covering Blank_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;
use calmpress\avatar\Blank_Avatar_Attributes_Mutator;
use calmpress\avatar\Blank_Avatar;

require_once __DIR__ . '/html_generation_helper.php';

class Mock_Mutator implements Blank_Avatar_Attributes_Mutator {

	public static int $size;

	public function notification_dependency_with( Observer $observer ): Observer_Priority	{
		return Observer_Priority::NONE;
	}

	public function mutate( array $attr, int $size): array {
		self::$size   = $size;
		$attr['tost'] = 1;
		return $attr;
	}

}

class Blank_Avatar_Test extends WP_UnitTestCase {
	use Html_Generation_Helper_Test;

	private Blank_Avatar $avatar;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Generation_Helper_Test trait.
	 *
	 * @since 1.0.0
	 */
	function setUp():void {
		$this->avatar = new Blank_Avatar();
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

		$this->assertStringContainsString( 'src=""', $html );
		$this->assertStringContainsString( 'width="50"', $html );
		$this->assertStringContainsString( 'height="50"', $html );
	}

	/**
	 * Test that the mutator is executed as part of blank avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_mutator() {

		Blank_Avatar::register_generated_attributes_mutator( new Mock_Mutator() );		
		$html = $this->avatar->html( 50 );

		$this->assertStringContainsString( 'tost="1"', $html );
		$this->assertSame( 50, Mock_Mutator::$size );
	}
}
