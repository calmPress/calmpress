<?php
/**
 * Unit tests covering Public_Key class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\webauthn\Public_Key;

class User_Of_Device_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set all properties.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {

		// not a proper base64URL throws exception.
		$throw = false;
		try {
			new Public_Key( 'pQECAyYgASFYIF6t9Oa1Z3ZL3mYjiHU5j9z6Bk3j+8m5UwxyZZ9S_ZUIlgg==INVALID==' );
		} catch ( \Exception $e ) {
			$throw = true;
		}
		$this->assertTrue( $throw );

		// proper key in base64URI format.
		$base64URL_key = 'pQECAyYgASFYIF6t9Oa1Z3ZL3mYjiHU5j9z6Bk3j-8m5UwxyZZ9S_ZUIlggABy9f5A68MkUy1V3NJMD5hv6v8RZrr9Q68MZ6ThQCkyk';
		$p = new Public_Key( $base64URL_key );
		$this->assertSame( $base64URL_key, $p->base64URL );
	}
}