<?php
/**
 * Unit tests covering Text_Based_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/html_parameter_validation.php';

use calmpress\avatar\Text_Based_Avatar_HTML_Mutator;
use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;
use \calmpress\avatar\Text_Based_Avatar;

class Mock_Text_Mutator implements Text_Based_Avatar_HTML_Mutator {

	public static int $size;
	public static string $text;
	public static string $color_factor;

	public function notification_dependency_with( Observer $observer ): Observer_Priority	{
		return Observer_Priority::NONE;
	}

	public function mutate( string $html, string $text, string $color_factor, int $size ): string {
		self::$size        = $size;
		self::$text         = $text;
		self::$color_factor = $color_factor;
		return 'tost';
	}

}

class Text_Based_Avatar_Test extends WP_UnitTestCase {
	use Html_Parameter_Validation_Test;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Parameter_Validation_Test trait.
	 *
	 * @since 1.0.0
	 */
	function setUp(): void {
		$this->avatar = new \calmpress\avatar\Text_Based_Avatar( 'test for best', 't@test.com' );
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

		// Check text.
		$this->assertStringContainsString( '>TB<', $html );

		// Test different color factor result with different color (indirectly via html).
		$avatar2 = new \calmpress\avatar\Text_Based_Avatar( 'test for best', 't@calm.com' );
		$this->assertNotEquals( $this->avatar->html( 50 ), $avatar2->html( 50 ) );

		// Test text on width smaller than 40 px.
		$html = $this->avatar->html( 30 );
		$this->assertStringContainsString( '>T<', $html );

		// Test blank avatar html is returned when no primary text is given.
		$avatar2 = new \calmpress\avatar\Text_Based_Avatar( '', 't@testi.com' );
		$blank = new \calmpress\avatar\Blank_Avatar();
		$this->assertEquals( $blank->html( 50 ), $avatar2->html( 50 ) );
	}

	/**
	 * Test that the filter is executed as part of text avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_filter() {
		Text_Based_Avatar::register_generated_HTML_mutator( new Mock_Text_Mutator() );		

		$html = $this->avatar->html( 50 );

		$this->assertSame( 'tost', $html );
		$this->assertSame( 50, Mock_Text_Mutator::$size );
		$this->assertSame( 'test for best', Mock_Text_Mutator::$text );
		$this->assertSame( 't@test.com', Mock_Text_Mutator::$color_factor );
	}
}
