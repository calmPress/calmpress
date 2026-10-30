<?php
/**
 * Unit tests covering File_Credentials functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\credentials\File_Credentials;

class WP_Test_File_Credentials extends WP_UnitTestCase {

	/**
	 * Test the stream_context method. Always return null
	 *
	 * @since 1.0.0
	 */
	function test_stream_context() {
		$cred = new File_Credentials();
		$context      = $cred->stream_context();
		$this->assertSame( null, $context );
	}

	/**
	 * Test the stream_url_from_path method. Should return the parameter given
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path() {

		$creds = new File_Credentials();
		$this->assertSame( '/some/test/path.txt', $creds->stream_url_from_path( '/some/test/path.txt') );
	}

	/**
	 * Tests that a relative path is rejected instead of using PHP's working directory.
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path_rejects_relative_path() {
		$creds = new File_Credentials();
		$this->expectException( \DomainException::class );

		$creds->stream_url_from_path( 'wp-config.php' );
	}

	/**
	 * Tests that direct file credentials do not accept another stream wrapper.
	 *
	 * @since 1.0.0
	 */
	function test_stream_url_from_path_rejects_stream_url() {
		$creds = new File_Credentials();
		$this->expectException( \DomainException::class );

		$creds->stream_url_from_path( 'ftp://example.test/wp-config.php' );
	}
}
