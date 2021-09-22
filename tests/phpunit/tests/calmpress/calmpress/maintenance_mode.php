<?php
/**
 * Unit tests covering Maintenance_Mode functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\calmpress\Maintenance_Mode;

class WP_Test_Maintenance_Mode extends WP_UnitTestCase {

	/**
	 * Test activation and status reporting
	 *
	 * @since 1.0.0
	 */
	function test_is_active() {
		// After a boot with virgin DB, maintenance mode is inactive.
		$this->assertFalse( Maintenance_Mode::is_active() );

		// After activation, it is active
		Maintenance_Mode::activate();
		$this->assertTrue( Maintenance_Mode::is_active() );
	}

	/**
	 * Test deactivation
	 *
	 * @since 1.0.0
	 */
	function test_deactivate() {
		Maintenance_Mode::activate();
		Maintenance_Mode::deactivate();
		$this->assertFalse( Maintenance_Mode::is_active() );
	}

	/**
	 * Test each activation generates a new bypass code
	 *
	 * @since 1.0.0
	 */
	function test_bypasscode() {
		Maintenance_Mode::activate();
		$code1 = Maintenance_Mode::bypass_code();
		Maintenance_Mode::deactivate();
		Maintenance_Mode::activate();
		$code2 = Maintenance_Mode::bypass_code();
		$this->assertTrue( $code1 !== $code2 );
	}

	/**
	 * Error handler use to supress headers sent errors and use as indication that cookies
	 * were sent.
	 *
	 * @since 1.0.p
	 */
	public function error_handler( int $errno, string $errstr ) {
		if ( false !== strpos( $errstr, 'headers already sent') ) {
			$this->headers_sent = true;
			return true;
		}

		return false;
	}

	/**
	 * Test if a current user is getting a maintenance page (blocked) which may happen if
	 * - Maintenance mode is on and the user do not have maintenance_mode capabilioty
	 *   and do not use the bypass code in the URL or coockie
	 * - maintenance mode is off, but a preview url is used
	 *
	 * @since 1.0.0
	 */
	function test_current_user_blocked() {
		Maintenance_Mode::activate();
		// by default testing uses anonymous user which should be blocked.
		$this->assertTrue( Maintenance_Mode::current_user_blocked() );

		// unless it has a bypass in the URL

		// If the cookie is set we should get an headers sent error due to how wordpress
		// tests are run. Take control of the error handler to 1. prevent it from failing the test
		// 2 used as indication that the cookie is sent.
		$this->headers_sent = false;
		$previous_handler = set_error_handler( [ $this, 'error_handler' ] );
		$_GET[ Maintenance_Mode::BYPASS_NAME ] = Maintenance_Mode::bypass_code();
		$this->assertFalse( Maintenance_Mode::current_user_blocked() );
		$this->assertTrue( $this->headers_sent );
		set_error_handler( $previous_handler );
		unset( $_GET[ Maintenance_Mode::BYPASS_NAME ] );

		// or cookie 
		$_COOKIE[ Maintenance_Mode::BYPASS_NAME ] = Maintenance_Mode::bypass_code();

		$this->assertFalse( @Maintenance_Mode::current_user_blocked() );
		unset( $_COOKIE[ Maintenance_Mode::BYPASS_NAME ] );

		// Add maintenance mode capability to non admin user.
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$user = get_user_by( 'id', $user_id );
		$user->add_cap( 'maintenance_mode' );
		wp_set_current_user( $user_id );

		// Has the capability, not blocked.
		$this->assertFalse( Maintenance_Mode::current_user_blocked() );

		// but is blocked on the preview url which uses none on a parameter
		$_GET[ Maintenance_Mode::PREVIEW_PARAM ] = wp_create_nonce( Maintenance_Mode::PREVIEW_PARAM );
		$this->assertTrue( Maintenance_Mode::current_user_blocked() );
		unset( $_GET[ Maintenance_Mode::PREVIEW_PARAM ] );
	}

	/**
	 * test set and get of the of the projected end time.
	 *
	 * @since 1.0.0
	 */
	public function test_projected_end_time() {
		// set to an hour in the future
		$end_time = time() + 3600;
		Maintenance_Mode::set_projected_end_time( $end_time );
		// To be on the safe side allow 5 seconds to pass between setting the time and the next line.
		$this->assertTrue( 3595 < Maintenance_Mode::projected_time_till_end() );

		// When time is set to less than 10 minute projected_time_till_end should return 10 minutes.
		Maintenance_Mode::set_projected_end_time( time() );
		$this->assertSame( 600, Maintenance_Mode::projected_time_till_end() );
	}

	/**
	 * test set and get of the of the text title.
	 *
	 * @since 1.0.0
	 */
	public function test_set_text_title() {
		// test handling of slashes as well
		Maintenance_Mode::set_text_title( 'test \\title' );
		$this->assertSame( 'test \\title', Maintenance_Mode::text_title() );
	}

	/**
	 * test set and get of the of the content.
	 *
	 * @since 1.0.0
	 */
	public function test_set_content() {
		// test handling of slashes as well
		Maintenance_Mode::set_content( 'test \\content' );
		$this->assertSame( 'test \\content', Maintenance_Mode::content() );
	}

	/**
	 * test set and get of the of the page title.
	 *
	 * @since 1.0.0
	 */
	public function test_set_page_title() {
		// test handling of slashes as well
		Maintenance_Mode::set_page_title( 'test \\page' );
		$this->assertSame( 'test \\page', Maintenance_Mode::page_title() );
	}

	/**
	 * test set and get of the of the theme used switch.
	 *
	 * @since 1.0.0
	 */
	public function test_set_theme_used() {
		// test handling of slashes as well
		Maintenance_Mode::set_use_theme_frame( false );
		$this->assertFalse( Maintenance_Mode::theme_frame_used() );

		Maintenance_Mode::set_use_theme_frame( true );
		$this->assertTrue( Maintenance_Mode::theme_frame_used() );
	}

	/**
	 * test the maintenance_left shortcode.
	 *
	 * @since 1.0.0
	 */
	public function test_maintenance_left_shortcode() {
		// set to an hour and 10 minutes in the future
		$end_time = time() + 4200;
		Maintenance_Mode::set_projected_end_time( $end_time );
		$result   = Maintenance_Mode::maintenance_left_shortcode( [] );

		$this->assertSame( '1 hour, 10 minutes', $result );
	}

	/**
	 * Test the creation and retrival of the maintenance mode post.
	 *
	 * @since 1.0.0
	 */
	function test_text_holder_post() {

		$post = Maintenance_Mode::text_holder_post();
		$this->assertSame( 'maintenance_mode', $post->post_type );
	}
}
