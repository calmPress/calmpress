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
}
