<?php
/**
 * Unit tests covering User_Of_Device class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\webauthn\Devices_Of_User;
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
		$collection = new Devices_Of_User( $user );

		// common use.
		$t = new User_Of_Device( 'credential', 'public_key', 'just a test', $date, $collection );
		$this->assertSame( 'credential', $t->credential_id );
		$this->assertSame( 'public_key', $t->public_key );
		$this->assertSame( 'just a test', $t->description() );
		$this->assertSame( $date, $t->last_authentication_time() );
		$this->assertSame( $collection, $t->user_devices_collection );
	}

	/**
	 * Test set_description
	 * 
	 * @since 1.0.0
	 */
	function test_set_description() {
		$date  = new \DateTime();
		$user = new \WP_User();
		$collection = new Devices_Of_User( $user );

		// common use.
		$t = new User_Of_Device( 'credential', 'public_key', 'just a test', $date, $collection );
		$t->set_description( 'new desc' );
		$this->assertSame( 'new desc', $t->description() );
	}

	/**
	 * Test set_last_authentication_time
	 * 
	 * @since 1.0.0
	 */
	function test_set_last_authentication_time() {
		$date  = new \DateTime();
		$user = new \WP_User();
		$collection = new Devices_Of_User( $user );

		// common use.
		$t = new User_Of_Device( 'cred', 'public_key', 'just a test', $date, $collection );
		$date = new \DateTime( '+1 day' );
		$t->set_last_authentication_time( $date );
		$this->assertSame( $date, $t->last_authentication_time() );
	}

	/**
	 * Test serialization and unserialization
	 *
	 * @since 1.0.0
	 */
	public function test_serialization() {
		$date = new \DateTime();
		$user = new \WP_User();
		$collection = new Devices_Of_User( $user );

		// serialize unserialize should give equal objects.
		$t = new User_Of_Device( 'cred', 'public_key', 'just a test', $date, $collection );
		$json = $t->serialize();

		$u = User_Of_Device::unserialize( $json, $collection );
		$this->assertSame( 'cred', $u->credential_id );
		$this->assertSame( 'public_key', $u->public_key );
		$this->assertSame( 'just a test', $u->description() );
		$this->assertEquals( $date->getTimestamp(), $u->last_authentication_time()->getTimestamp() );
		$this->assertSame( $collection, $u->user_devices_collection );
	}
}