<?php
/**
 * Unit tests calmPress code in the WP_User class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

/**
 * Tests for the code in WP_User class.
 * 
 * @since 1.0.0
 */
class WP_User_Test extends WP_UnitTestCase {

	/**
	 * Test encrypt and decrypt cycle for values.
	 *
	 * @since 1.0.0
	 */
	public function test_value_encryption() {

		$encrypt = new ReflectionMethod( 'WP_User', 'encrypt' );
		$decrypt = new ReflectionMethod( 'WP_User', 'decrypt' );

		// proper encrypt and decrypt cycle.
		$encrypted = $encrypt->invoke( null, 42 );
		$this->assertSame( 42, $decrypt->invoke( null, $encrypted ) );

		// random string should fail.
		$this->assertSame( 0, $decrypt->invoke( null, 'this is random' ) );

		// Encryption generate different value for different invocations
		// when the value passed is the same.
		// Theoretically this test might fail once in a very long while
		// but it should not fail in two consecutive runs of the test.
		$encrypted2 = $encrypt->invoke( null, 42 );
		$this->assertNotSame( $encrypted, $encrypted2 );
	}

	/**
	 * Test encrypt and decrypt cycle for user objects.
	 *
	 * @since 1.0.0
	 */
	public function test_user_id_encryption() {

		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// proper encrypt and decrypt cycle.
		$encrypted = $user->encrypted_id();
		$this->assertSame( $user_id, WP_User::user_from_encrypted_string( $encrypted )->ID );

		// Decryption of random string should fail.
		$this->assertSame( null, WP_User::user_from_encrypted_string( 'this is random' ) );
	}
}