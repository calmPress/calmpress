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
	 * Test the creation and retrival of the maintenance mode post.
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path() {

		$post = Maintenance_Mode::text_holder_post();
		$this->assertSame( 'maintenance_mode', $post->post_type );
	}
}
