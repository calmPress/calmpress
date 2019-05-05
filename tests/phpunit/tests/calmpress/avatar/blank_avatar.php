<?php
/**
 * Unit tests covering Locked_File_Access functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

require __DIR__ . '/html_parameter_validation.php';

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
}
