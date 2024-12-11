<?php
/**
 * Unit tests covering Devices_Of_User class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\webauthn\Public_Key;
use calmpress\webauthn\User_Of_Device;
use calmpress\webauthn\Devices_Of_User;

class Devices_Of_User_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set all properties.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {

		$user = new \WP_User();
		$collection = new Devices_Of_User( $user );
		$this->assertSame( $user, $collection->user );
	}

	/**
	 * Test the devices function.
	 *
	 * @since 1.0.0
	 */
	public function test_devices() {

		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// Initialy no devices.
		$this->assertSame( 0, count( $collection->devices() ) );

		// One device added.
		$device = new User_Of_Device( new Public_Key( 'deadbeef' ), 'desc', new \DateTime( 'now' ), $collection );
		$collection->store( $device );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );

		// Check the device has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );
	}

	/**
	 * Test the store function.
	 *
	 * @since 1.0.0
	 */
	public function test_store() {

		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// One device added.
		$device = new User_Of_Device( new Public_Key( 'deadbeef' ), 'desc', new \DateTime( 'now' ), $collection );
		$collection->store( $device );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );

		// Check the device has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );

		// Check description
		$this->assertSame( 'desc', $devices['deadbeef']->description() );

		// Same device different description
		$device = new User_Of_Device( new Public_Key( 'deadbeef' ), 'new desc', new \DateTime( 'now' ), $collection );
		$collection->store( $device );
		$devices = $collection->devices();

		$this->assertSame( 1, count( $devices ) );
		// Check the device has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );

		// Check description
		$this->assertSame( 'new desc', $devices['deadbeef']->description() );

		// Second device added.
		$device2 = new User_Of_Device( new Public_Key( 'beefdead' ), 'desc2', new \DateTime( 'now' ), $collection );
		$collection->store( $device2 );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );

		// Check the devices has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );
		$this->assertSame( 'beefdead', $devices['beefdead']->public_key->base64URL );
	}

	/**
	 * Test the register_device function.
	 *
	 * @since 1.0.0
	 */
	public function test_register_device() {

		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// One device added.
		$collection->register_device( new Public_Key( 'deadbeef' ) );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );

		// Check the device has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );

		// Second device added.
		$collection->register_device( new Public_Key( 'beefdead' ) );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );

		// Check the devices has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );
		$this->assertSame( 'beefdead', $devices['beefdead']->public_key->base64URL );

		// Registering same device twice makes no difference.
		$collection->register_device( new Public_Key( 'beefdead' ) );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );

		// Check the devices has the index of its public key
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );
		$this->assertSame( 'beefdead', $devices['beefdead']->public_key->base64URL );
	}

	/**
	 * Test the remove_device function.
	 *
	 * @since 1.0.0
	 */
	public function test_remove_device() {

		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// One device added.
		$collection->register_device( new Public_Key( 'deadbeef' ) );
		$collection->register_device( new Public_Key( 'beefdead' ) );

		// Removing non registered device changes nothing
		$collection->remove_device( new Public_Key( 'aaaaaa' ) );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );
		$this->assertSame( 'deadbeef', $devices['deadbeef']->public_key->base64URL );
		$this->assertSame( 'beefdead', $devices['beefdead']->public_key->base64URL );

		// Remove one.
		$collection->remove_device( new Public_Key( 'deadbeef' ) );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );
		$this->assertSame( 'beefdead', $devices['beefdead']->public_key->base64URL );

		// Remove second.
		$collection->remove_device( new Public_Key( 'beefdead' ) );
		$devices = $collection->devices();
		$this->assertSame( 0, count( $devices ) );
	}
}