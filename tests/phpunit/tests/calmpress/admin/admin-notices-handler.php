<?php
/**
 * Unit tests covering Admin_Notices_Handler functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

/**
 * Test cases to test the Admin_Notices_Handler class.
 *
 * @since 1.0.0
 */
class Admin_Notices_Handler_Test extends WP_UnitTestCase {

	/**
	 * Test the constructor.
	 * 
	 * @since 1.0.0
	 */
	function test_constructor() {

		// No initial data, no output.
		$notices = new \calmpress\admin\Admin_Notices_Handler();
		ob_start();
		$notices->output_notices();
		$this->assertSame( '', ob_get_clean() );

		// Initiate with some valid data.
		$obj = new \stdClass();
		$obj->success=[];
		$obj->error=[];
		$obj->info=[];

		// On failure an exception will be generated that will fail the test.
		$notices = new \calmpress\admin\Admin_Notices_Handler( json_encode( $obj ) );

		// Fail on some invalid initiation data.

		// Make the three array to not exists, and not be arrays, and test for exception
		foreach ( ['success', 'error', 'info'] as $type ) {
			$t = clone( $obj );
			unset( $t->$type );
			try {
				new \calmpress\admin\Admin_Notices_Handler( json_encode( $t ) );
				$this->assertTrue( false, $type . ' not present, but object created' );
			} catch ( \Exception  $e ) {
			}

			$t = clone( $obj );
			$t->$type = 5;
			try {
				new \calmpress\admin\Admin_Notices_Handler( json_encode( $t ) );
				$this->assertTrue( false, $type . ' not an array, but object created' );
			} catch ( \Exception  $e ) {
			}
		}
	}

	/**
	 * Test the add_success_message method and relevant output.
	 * 
	 * @since 1.0.0
	 */
	function test_add_success_message() {

		// One message.
		$notices = new \calmpress\admin\Admin_Notices_Handler();
		$notices->add_success_message( 'success message' );
		ob_start();
		$notices->output_notices();
		$this->assertSame( "<div class='notice notice-success'><p>success message</p></div>", ob_get_clean() );

		// Multiple.
		$notices->add_success_message( 'another success message' );
		ob_start();
		$notices->output_notices();

		// Not the greatest test as it assumes specific order which is not realy important,
		// but easier to test like that.
		$this->assertSame(
			"<div class='notice notice-success'><p>success message</p></div>" .
			"<div class='notice notice-success'><p>another success message</p></div>",
			ob_get_clean()
		);
	}


	/**
	 * Test the add_error_message method and relevant output.
	 * 
	 * @since 1.0.0
	 */
	function test_add_error_message() {

		// One message.
		$notices = new \calmpress\admin\Admin_Notices_Handler();
		$notices->add_error_message( 'error message' );
		ob_start();
		$notices->output_notices();
		$this->assertSame( "<div class='notice notice-error'><p>error message</p></div>", ob_get_clean() );

		// Multiple.
		$notices->add_error_message( 'another error message' );
		ob_start();
		$notices->output_notices();

		// Not the greatest test as it assumes specific order which is not realy important,
		// but easier to test like that.
		$this->assertSame(
			"<div class='notice notice-error'><p>error message</p></div>" .
			"<div class='notice notice-error'><p>another error message</p></div>",
			ob_get_clean()
		);
	}

	/**
	 * Test the add_info_message method and relevant output.
	 * 
	 * @since 1.0.0
	 */
	function test_add_info_message() {

		// One message.
		$notices = new \calmpress\admin\Admin_Notices_Handler();
		$notices->add_info_message( 'info message' );
		ob_start();
		$notices->output_notices();
		$this->assertSame( "<div class='notice notice-info'><p>info message</p></div>", ob_get_clean() );

		// Multiple.
		$notices->add_info_message( 'another info message' );
		ob_start();
		$notices->output_notices();

		// Not the greatest test as it assumes specific order which is not realy important,
		// but easier to test like that.
		$this->assertSame(
			"<div class='notice notice-info'><p>info message</p></div>" .
			"<div class='notice notice-info'><p>another info message</p></div>",
			ob_get_clean()
		);
	}

	/**
	 * Test the output_notices method.
	 * Verify it outputs errors first, info after and success last.
	 * 
	 * @since 1.0.0
	 */
	function test_output_notices() {

		$notices = new \calmpress\admin\Admin_Notices_Handler();
		$notices->add_success_message( 'success message' );
		$notices->add_error_message( 'error message' );
		$notices->add_info_message( 'info message' );
		$notices->add_error_message( 'another error message' );
		$notices->add_info_message( 'another info message' );
		$notices->add_success_message( 'another success message' );
		ob_start();
		$notices->output_notices();
		$this->assertSame(
			"<div class='notice notice-error'><p>error message</p></div>" .
			"<div class='notice notice-error'><p>another error message</p></div>" .
			"<div class='notice notice-info'><p>info message</p></div>" .
			"<div class='notice notice-info'><p>another info message</p></div>" .
			"<div class='notice notice-success'><p>success message</p></div>" .
			"<div class='notice notice-success'><p>another success message</p></div>",
			ob_get_clean()
		);
	}

	/**
	 * Test the json method.
	 * Verify that the json generated can be used to construct an object
	 * with the same output.
	 * 
	 * @since 1.0.0
	 */
	function test_json() {

		$notices = new \calmpress\admin\Admin_Notices_Handler();
		$notices->add_success_message( 'success message' );
		$notices->add_error_message( 'error message' );
		$notices->add_info_message( 'info message' );
		$notices->add_error_message( 'another error message' );
		$notices->add_info_message( 'another info message' );
		$notices->add_success_message( 'another success message' );
		$json = $notices->json();
		$t = new \calmpress\admin\Admin_Notices_Handler( $json );
		ob_start();
		$t->output_notices();
		$this->assertSame(
			"<div class='notice notice-error'><p>error message</p></div>" .
			"<div class='notice notice-error'><p>another error message</p></div>" .
			"<div class='notice notice-info'><p>info message</p></div>" .
			"<div class='notice notice-info'><p>another info message</p></div>" .
			"<div class='notice notice-success'><p>success message</p></div>" .
			"<div class='notice notice-success'><p>another success message</p></div>",
			ob_get_clean()
		);
	}

}
