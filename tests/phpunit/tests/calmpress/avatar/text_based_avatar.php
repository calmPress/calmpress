<?php
/**
 * Unit tests covering Text_Based_Avatar functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require_once __DIR__ . '/html_generation_helper.php';

use calmpress\avatar\Text_Based_Avatar_Attributes_Mutator;
use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;
use \calmpress\avatar\Text_Based_Avatar;

class Mock_Text_Mutator implements Text_Based_Avatar_Attributes_Mutator {

	public static int $size;
	public static string $text;
	public static string $color_factor;

	public function notification_dependency_with( Observer $observer ): Observer_Priority	{
		return Observer_Priority::NONE;
	}

	public function mutate( array $attr, string $text, string $color_factor, int $size ): array {
		self::$size         = $size;
		self::$text         = $text;
		self::$color_factor = $color_factor;
		$attr['tost']       = 1;
		return $attr;
	}

}

class Text_Based_Avatar_Test extends WP_UnitTestCase {
	use Html_Generation_Helper_Test;

	/**
	 * Set up the avatar attribute to the object being tested as required by the
	 * Html_Generation_Helper_Test trait.
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

		$this->assertStringContainsString( 'width="50"', $html );
		$this->assertStringContainsString( 'height="50"', $html );

		// Check text.
		preg_match('/<img[^>]+src="([^"]+)"/', $html, $matches);
		$src = str_replace('data:image/svg+xml;base64,', '', $matches[1]) ;
		$svg = base64_decode( $src );
		$this->assertStringContainsString( '>tfb<', $svg );

		// Test different color factor result with different color (indirectly via html).
		$avatar2 = new \calmpress\avatar\Text_Based_Avatar( 'test for best', 't@calm.com' );
		$this->assertNotEquals( $this->avatar->html( 50 ), $avatar2->html( 50 ) );

		// Test blank avatar html is returned when no primary text is given.
		$avatar2 = new \calmpress\avatar\Text_Based_Avatar( '', 't@testi.com' );
		$blank = new \calmpress\avatar\Blank_Avatar();
		$this->assertEquals( $blank->html( 50 ), $avatar2->html( 50 ) );
	}

	/**
	 * Test that the mutator is executed as part of text avatar generation.
	 *
	 * @since 1.0.0
	 */
	function test_filter() {
		Text_Based_Avatar::register_generated_attributes_mutator( new Mock_Text_Mutator() );		

		$html = $this->avatar->html( 50 );

		$this->assertStringContainsString( 'tost="1"', $html );
		$this->assertSame( 50, Mock_Text_Mutator::$size );
		$this->assertSame( 'test for best', Mock_Text_Mutator::$text );
		$this->assertSame( 't@test.com', Mock_Text_Mutator::$color_factor );
	}

	/**
	 * Test avatar_text
	 * 
	 * @since 1.0.0
	 * 
	 * @dataProvider avatar_text_data
	 */
	function test_avatar_text( $source, $avatar ) {
		$this->assertSame( $avatar, Text_Based_Avatar::avatar_text( $source ) );
	}

	function avatar_text_data() {
		return [
			['', ''],
			[' ', ''],
			['._-', ''],
			['ar', 'a'],
			['a r', 'ar'],
			['ab cd ef', 'ace'],
			['ab cd ef gh', 'acg'],
			['ab c-d', 'acd'],
			['ab c-d', 'acd'],
			['_king-lion', 'kl'],
			['John.Doe', 'JD'],
		];
	}
}
