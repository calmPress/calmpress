<?php
/**
 * Unit tests covering Blank_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;
use calmpress\avatar\Blank_Avatar_HTML_Mutator;
use calmpress\avatar\Blank_Avatar;

require_once __DIR__ . '/html_parameter_validation.php';

class Mock_Mutator implements Blank_Avatar_HTML_Mutator {

	public static int $size;

	public function notification_dependency_with( Observer $observer ): Observer_Priority	{
		return Observer_Priority::NONE;
	}

	public function mutate( string $html, int $size): string {
		self::$size  = $size;
		return 'tost';
	}

}

class Blank_Avatar_Test extends WP_UnitTestCase {
	use Html_Parameter_Validation_Test;

	private Blank_Avatar $avatar;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Parameter_Validation_Test trait.
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

		$this->assertStringContainsString( 'display:inline-block', $html );
		$this->assertStringContainsString( 'width:50px', $html );
		$this->assertStringContainsString( 'height:50px', $html );
	}

	/**
	 * Test that the mutator is executed as part of blank avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_mutator() {

		Blank_Avatar::register_generated_HTML_mutator( new Mock_Mutator() );		
		$html = $this->avatar->html( 50 );

		$this->assertSame( 'tost', $html );
		$this->assertSame( 50, Mock_Mutator::$size );
	}
}
