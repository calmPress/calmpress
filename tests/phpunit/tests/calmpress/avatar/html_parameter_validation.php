<?php
/**
 * Unit tests trait to test the Html_Parameter_Validation trait.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Trait to test the html_parameter_validation trait functions. The tests assume
 * that the property "avatar" is set to the object to be tested.
 */
trait Html_Parameter_Validation_Test {

	/**
	 * Test that invalid parameters to html method generate warnings, and that
	 * the _html method is properly called when there are no errors.
	 *
	 * @since 1.0.0
	 */
	public function test_parameter_validation() {

		// Test non positive width.
		$this->expectException( '\PHPUnit\Framework\Error\Warning' );
		$v = $this->avatar->html( 0, 100 );
		$this->assertEquals( '', $v );

		// Test non positive height.
		$this->expectException( '\PHPUnit\Framework\Error\Warning' );
		$v = $this->avatar->html( 50, -100 );
		$this->assertEquals( '', $v );

		// Test non positive width and height.
		$this->expectException( '\PHPUnit\Framework\Error\Warning' );
		$v = $this->avatar->html( 0, -50 );
		$this->assertEquals( '', $v );

		// Test valid parameters call _html.
		$this->avatar->assertEquals( $this->html( 50, 50 ), $this->_html( 50, 50 ) );
	}
}

?>
