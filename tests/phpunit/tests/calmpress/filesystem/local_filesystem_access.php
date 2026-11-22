<?php
/**
 * Unit tests covering Local_Filesystem_Access functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Local_Filesystem_Access;

class WP_Test_Local_Filesystem_Access extends WP_UnitTestCase {

	/**
	 * Test the stream_context method. Always return null
	 *
	 * @since 1.0.0
	 */
	function test_stream_context() {
		$cred    = new Local_Filesystem_Access();
		$context = $cred->stream_context();
		$this->assertSame( null, $context );
	}

	/**
	 * Test the stream_url_from_path method. Should return the parameter given
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path() {

		$creds = new Local_Filesystem_Access();
		$path  = ABSPATH . 'wp-content/test/path.txt';
		$this->assertSame( $path, $creds->stream_url_from_path( $path ) );
	}

	/**
	 * Tests that a relative path is rejected instead of using PHP's working directory.
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path_rejects_relative_path() {
		$creds = new Local_Filesystem_Access();
		$this->expectException( \DomainException::class );

		$creds->stream_url_from_path( 'wp-config.php' );
	}

	/**
	 * Tests that direct file credentials do not accept another stream wrapper.
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path_rejects_stream_url() {
		$creds = new Local_Filesystem_Access();
		$this->expectException( \DomainException::class );

		$creds->stream_url_from_path( 'ftp://example.test/wp-config.php' );
	}

	/**
	 * Permission changes reject relative paths before calling the local filesystem.
	 *
	 * @since 1.0.0
	 */
	public function test_change_permissions_rejects_relative_path(): void {
		$access = new Local_Filesystem_Access();
		$this->expectException( \DomainException::class );

		$access->change_permissions( 'wp-config.php', 0644 );
	}
}
