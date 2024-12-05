<?php
/**
 * Unit tests covering User_Of_Device class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\webauthn\User_Of_Device;

class User_Of_Device_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set all properties.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {

		$date  = new \DateTime();
		$user = new \WP_User();

		// common use.
		$t = new User_Of_Device( 'public_key', 'just a test', $date, $user );
		$this->assertSame( 'public_key', $t->public_key );
		$this->assertSame( 'just a test', $t->description );
		$this->assertSame( $date, $t->last_autheticated_at );
		$this->assertSame( $user, $t->user );
	}

	/**
	 * Test serialization and unserialization
	 *
	 * @since 1.0.0
	 */
	public function test_full_address() {
		$date = new \DateTime();
		$user = new \WP_User();

		// serialize unserialize should give equal objects.
		$t = new User_Of_Device( 'public_key', 'just a test', $date, $user );
		$json = $t->serialize();

		$u = User_Of_Device::unserialize( $json, $user );
		$this->assertSame( 'public_key', $u->public_key );
		$this->assertSame( 'just a test', $u->description );
		$this->assertEquals( $date->getTimestamp(), $u->last_autheticated_at->getTimestamp() );
		$this->assertSame( $user, $u->user );
	}

}