<?php
/**
 * Unit tests covering One_Time_password class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\utils\One_Time_Password;

class One_Time_Password_Test extends WP_UnitTestCase {

	/**
	 * Test that the new generates object that has 6 digits password and
	 * matching the password
	 *
	 * @since 1.0.0
	 */
	public function test_new() {

		$password = One_Time_Password::new( 60 );
		$this->assertSame( 1, preg_match( '/^\d{6}$/', $password->password ) );
		$this->assertTrue( $password->is_matching( $password->password ) );
	}

	/**
	 * Test that the is_matching
	 *
	 * @since 1.0.0
	 */
	public function test_is_matching() {

		$password = One_Time_Password::new( 60 );

		// Mat hing itself when expiry in the futre.
		$password = One_Time_Password::new( 60 );
		$this->assertTrue( $password->is_matching( $password->password ) );

		// Fails matching itself when expiry in the past 
		$password = One_Time_Password::new( -60 );
		$this->assertFalse( $password->is_matching( $password->password ) );

		// Fails different password value
		$password = One_Time_Password::new( 60 );
		$this->assertFalse( $password->is_matching( '0000000' ) ); // Too many digits
	}

	/**
	 * Test serialize and unserialise return same object
	 *
	 * @since 1.0.0
	 */
	public function test_serialize() {
		$password = One_Time_Password::new( 60 );

		$ser = $password->serialize();
		$pass = One_Time_Password::unserialize( $ser );

		$this->assertTrue( $pass->is_matching( $password->password ) );
	}

	/**
	 * Test throws one data of expired password
	 *
	 * @since 1.0.0
	 */
	public function test_unserialize() {
		$password = One_Time_Password::new( -60 );

		$ser = $password->serialize();
		$thrown = false;
		try {
			$pass = One_Time_Password::unserialize( $ser );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}

		$this->assertTrue( $thrown );
	}
}