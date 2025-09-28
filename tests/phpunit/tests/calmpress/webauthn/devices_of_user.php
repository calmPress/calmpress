<?php
/**
 * Unit tests covering Devices_Of_User class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\webauthn\User_Of_Device;
use calmpress\webauthn\Devices_Of_User;
use function calmpress\utils\base64URL_encode;

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
	 * Test devices method after constructor
	 * 
	 * @since 1.0.0
	 */
	public function test_devices_after_constructor() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// Initialy no devices.
		$this->assertSame( 0, count( $collection->devices() ) );
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
		$device = new User_Of_Device( 'cred', 'public', 'desc', new \DateTime( 'now' ), $collection );
		$collection->store( $device );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );

		// Check the device has the index of its public key
		$this->assertSame( 'public', $devices['cred']->public_key );
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
		$device = new User_Of_Device( 'cred', 'deadbeef', 'desc', new \DateTime( 'now' ), $collection );
		$collection->store( $device );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );

		// Check the credential id is the index in the array.
		$this->assertTrue( array_key_exists( 'cred', $devices ) );
		$device = $devices['cred'];

		// Check the device has the right credential id.
		$this->assertSame( 'cred', $device->credential_id );

		// Check the device has the right public key.
		$this->assertSame( 'deadbeef', $device->public_key );

		// Check description
		$this->assertSame( 'desc', $device->description() );

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

		// Throw if trying to add device with no description.
		$throw = false;
		try {
			$collection->register_device( 'cred', 'deadbeef2', '' );
		} catch ( \RunTimeException $e) {
			$this->assertSame( Devices_Of_User::EXCEPTION_NO_DESCRIPTION, $e->getCode() );
			$throw = true;
		}
		$this->assertTrue( $throw );

		// One device added.
		$collection->register_device( 'cred', 'deadbeef', 'desc' );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );

		// Check the device has the index of its credential, and correct fields.
		$this->assertSame( 'cred', $devices['cred']->credential_id );
		$this->assertSame( 'deadbeef', $devices['cred']->public_key );
		$this->assertSame( 'desc', $devices['cred']->description() );

		// trying to add second device with same credentials and public key
		// updates the description.
		$collection->register_device( 'cred', 'deadbeef', 'desc 2' );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );
		$this->assertSame( 'cred', $devices['cred']->credential_id );
		$this->assertSame( 'deadbeef', $devices['cred']->public_key );
		$this->assertSame( 'desc 2', $devices['cred']->description() );

		// .. but with wrong public key throws an exception.
		$throw = false;
		try {
			$collection->register_device( 'cred', 'deadbeef2', 'desc2' );
		} catch ( \RunTimeException $e) {
			$this->assertSame( Devices_Of_User::EXCEPTION_PUBLIC_KEY_MISMATCH, $e->getCode() );
			$this->assertSame( 1, count( $collection->devices() ) );
			$throw = true;
		}
		$this->assertTrue( $throw );

		// Second device added.
		$collection->register_device( 'cred2', 'beefdead', 'desc2' );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );

		// Check the devices has the index of its credential
		$this->assertSame( 'deadbeef', $devices['cred']->public_key );
		$this->assertSame( 'beefdead', $devices['cred2']->public_key );

		// Registering same device twice makes no difference.
		$collection->register_device( 'cred2', 'beefdead', 'desc2' );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );

		// Check the devices has the index of its credential
		$this->assertSame( 'deadbeef', $devices['cred']->public_key );
		$this->assertSame( 'beefdead', $devices['cred2']->public_key );

		// Fails registering a device with already existing description.
		$throw = false;
		try {
			$collection->register_device( 'fail', 'failpub', 'desc 2' );
		} catch ( \RunTimeException $e) {
			$this->assertSame( Devices_Of_User::EXCEPTION_DESCRIPTION_USED, $e->getCode() );
			$this->assertSame( 2, count( $collection->devices() ) );
			$throw = true;
		}
		$this->assertTrue( $throw );

		// Fails with existing credential and public key, and an already existing description.
		$throw = false;
		try {
			$collection->register_device( 'cred2', 'beefdead', 'desc 2' );
		} catch ( \RunTimeException $e) {
			$this->assertSame( Devices_Of_User::EXCEPTION_DESCRIPTION_USED, $e->getCode() );
			$this->assertSame( 2, count( $collection->devices() ) );
			$throw = true;
		}
		$this->assertTrue( $throw );

		// check collection can have 5 devices.
		for ( $i=3; $i<6; $i++ ) {
			$collection->register_device( 'cred' . $i, 'beefdead'. $i, 'desc' . $i );
		}

		// .. but trying to register 6th throws an exception.
		$throw = false;
		try {
			$collection->register_device( 'cred6', 'deadbeef6', 'desc2' );
		} catch ( \RunTimeException $e) {
			$this->assertSame( Devices_Of_User::EXCEPTION_CAN_NOT_ADD_DEVICE, $e->getCode() );
			$this->assertSame( 5, count( $collection->devices() ) );
			$throw = true;
		}
		$this->assertTrue( $throw );
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

		$collection->register_device( 'cred', 'deadbeef', 'desc' );
		$collection->register_device( 'cred2', 'beefdead', 'desc2' );

		// Removing non registered device changes nothing
		$collection->remove_device( 'wow' );
		$devices = $collection->devices();
		$this->assertSame( 2, count( $devices ) );
		$this->assertSame( 'deadbeef', $devices['cred']->public_key );
		$this->assertSame( 'beefdead', $devices['cred2']->public_key );

		// Remove one.
		$collection->remove_device( 'cred' );
		$devices = $collection->devices();
		$this->assertSame( 1, count( $devices ) );
		$this->assertSame( 'beefdead', $devices['cred2']->public_key );

		// Remove second.
		$collection->remove_device( 'cred2' );
		$devices = $collection->devices();
		$this->assertSame( 0, count( $devices ) );
	}

	/**
	 * test can_add_device
	 */
	function test_can_add_device() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// check true returned when ollection having less than 5 devices.
		for ( $i=1; $i<5; $i++ ) {
			$collection->register_device( 'cred' . $i, 'beefdead'. $i, 'desc' . $i );
			$this->assertTrue( $collection->can_add_device() );
		}

		// and false returned when there are 5.
		$collection->register_device( 'cred5', 'deadbeef5', 'desc5' );
		$this->assertFalse( $collection->can_add_device() );
	}

	/**
	 * Test pack_number.
	 * 
	 * @since 1.0.0
	 * 
     * @dataProvider packedNumberProvider
     */
    public function test_packed_number( int $int_value, string $packed ): void {

		$refMethod = new ReflectionMethod( Devices_Of_User::class, 'packed_number' );
		$refMethod->setAccessible( true );

        $this->assertSame(
            $packed,
            $refMethod->invoke( null, $int_value )
        );
    }

	/**
	 * Test unpacked_string.
	 * 
	 * @since 1.0.0
	 * 
     * @dataProvider packedNumberProvider
     */
    public function test_unpacked_string( int $int_value, string $packed ): void {

		$refMethod = new ReflectionMethod( Devices_Of_User::class, 'unpacked_string' );
		$refMethod->setAccessible( true );

        $this->assertSame(
            $int_value,
            $refMethod->invoke( null, $packed )
        );
    }

	/**
	 * Data provider for pack and unpack integers into binary strings.
	 * 
	 * @since 1.0.0
	 */
	public static function packedNumberProvider(): array {
        return [
            'zero' => [
                0,
                "\x00\x00\x00\x00\x00\x00\x00\x00",
            ],
            'one' => [
                1,
                "\x00\x00\x00\x00\x00\x00\x00\x01",
            ],
            'max 32-bit' => [
                4294967295,
                "\x00\x00\x00\x00\xFF\xFF\xFF\xFF",
            ],
            'min 64-bit high' => [
                4294967296,
                "\x00\x00\x00\x01\x00\x00\x00\x00",
            ],
            '32-bit + 1' => [
                4294967297,
                "\x00\x00\x00\x01\x00\x00\x00\x01",
            ],
            'php int max' => [
                PHP_INT_MAX,
                "\x7F\xFF\xFF\xFF\xFF\xFF\xFF\xFF",
            ],
        ];
    }

	/**
	 * test devices_as_webautn_array
	 * 
	 * @since 1.0.0
	 */
	function test_devices_as_webautn_array() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		$refMethod = new ReflectionMethod( Devices_Of_User::class, 'devices_as_webautn_array' );
		$refMethod->setAccessible( true );

		// Empty when no devices.
		$devices_as_webauthn = $refMethod->invoke( $collection );
		$this->assertSame( count( $devices_as_webauthn ), 0 );

		for ( $i=1; $i<6; $i++ ) {
			$collection->register_device( 'cred' . $i, 'beefdead'. $i, 'desc' . $i );
		}

		$devices_as_webauthn = $refMethod->invoke( $collection );
		$this->assertSame( count( $devices_as_webauthn ), 5 );

		// The array an't be expected to be ordered or indexed in any speific way
		// so make sure all elements are there the hard wy.
		for ( $i=1; $i<6; $i++ ) {
			$found = false;
			foreach ( $devices_as_webauthn as $cred => $descriptior ) {
				if ( base64URL_encode( 'cred' . $i ) === $descriptior->id ) {
					$this->assertSame( 'public-key', $descriptior->type );
					$found = true;
					break;
				}
			}
			$this->assertTrue( $found );
		}
	}

	/**
	 * test rp_info
	 * 
	 * @since 1.0.0
	 */
	function test_rp_info() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		$refMethod = new ReflectionMethod( Devices_Of_User::class, 'rp_info' );
		$refMethod->setAccessible( true );

		$rp_info = $refMethod->invoke( $collection );
		if ( is_multisite() ) {
			// Main site.
			$this->assertSame( $rp_info->name, 'Test Blog Network' );
			$this->assertSame( $rp_info->id, 'example.org' );
		} else {
			$this->assertSame( $rp_info->name, 'Test Blog' );
			$this->assertSame( $rp_info->id, 'example.org' );
		}
	}

	/**
	 * test credential_is_used
	 * 
	 * In addition to explicit test of the function tests if relevant DB is
	 * updated when devices are registered and removed.
	 * 
	 * @since 1.0.0
	 */
	function test_credential_is_used() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

        $user_id2 = $factory->create( [
        ] );
		
		$user2 = new \WP_User( $user_id2 );
		$collection2 = new Devices_Of_User( $user2 );

		$refMethod = new ReflectionMethod( Devices_Of_User::class, 'credential_is_used' );
		$refMethod->setAccessible( true );

		// No devices were registered
		$this->assertFalse( $refMethod->invoke( null, 'cred' ) );

		// there are devices but not the same cred id.
		$collection->register_device( 'cred1', 'pub1', 'desc1' );
		$collection2->register_device( 'cred2', 'pub2', 'desc2' );
		$this->assertFalse( $refMethod->invoke( null, 'cred' ) );

		// there is one match, the user is returned.
		$this->assertSame( $user_id, $refMethod->invoke( null, 'cred1' ) );

		// More than one device per collection
		$collection->register_device( 'cred3', 'pub3', 'desc3' );
		$this->assertSame( $user_id, $refMethod->invoke( null, 'cred1' ) );
		$this->assertSame( $user_id, $refMethod->invoke( null, 'cred3' ) );

		// Removal removes the device without removing the others.
		$collection->remove_device( 'cred1' );
		$this->assertFalse( $refMethod->invoke( null, 'cred1' ) );
		$this->assertSame( $user_id, $refMethod->invoke( null, 'cred3' ) );
	}

	/**
	 * test new_device_challenge
	 * 
	 * @since 1.0.0
	 */
	function test_new_device_challenge() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
			'display_name' => 'user name',
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		$refMethod = new ReflectionMethod( Devices_Of_User::class, 'packed_number' );
		$refMethod->setAccessible( true );

		// Exception thrown when there are no available slots for ne device.
		for ( $i=1; $i<6; $i++ ) {
			$collection->register_device( 'cred' . $i, 'beefdead'. $i, 'desc' .$i );
		}

		$thrown = false;
		try {
			$collection->new_device_challenge();
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}

		$this->assertTrue( $thrown );
		$this->assertSame( Devices_Of_User::EXCEPTION_CAN_NOT_ADD_DEVICE, $e->getCode() );

		// Cleanup, leave only first.
		for ( $i=2; $i<6; $i++ ) {
			$collection->remove_device( 'cred' . $i );
		}

		$mock = $this->getMockBuilder( Devices_Of_User::class )
			->setConstructorArgs( [ $user ] ) 
            ->onlyMethods( ['challenge', 'rp_info'] ) // tell PHPUnit we want to mock this method
            ->getMock();

        $mock->method( 'challenge' )
             ->willReturn('abcd');

		 $mock->method( 'rp_info' )
             ->willReturn(new webauthn\PublicKeyCredentialRpEntity( 'name', 'id.org' ) );

		$packed_method = new ReflectionMethod( Devices_Of_User::class, 'packed_number' );
		$packed_method->setAccessible( true );

		$challenge = $mock->new_device_challenge();
		
		$this->assertSame( $challenge->challenge, 'abcd' );
		$this->assertSame( $challenge->rp->name, 'name' );
		$this->assertSame( $challenge->rp->id, 'id.org' );
		$this->assertSame( $challenge->user->name, 'user name' );
		$this->assertSame( $challenge->user->displayName, 'user name' );
		$this->assertSame( $challenge->user->id, $packed_method->invoke( null, $user_id ) );
		// just minimal sanity for the next 3 fields
		$this->assertTrue( count( $challenge->pubKeyCredParams ) > 0 );
		$this->assertNotNull( $challenge->pubKeyCredParams );
		$this->assertNull( $challenge->attestation );

		// Have one credential with the right value
		$this->assertSame( 1, count( $challenge->excludeCredentials ) );
		$this->assertSame( 'public-key', $challenge->excludeCredentials[0]->type );
		$this->assertSame( base64URL_encode( 'cred1' ), $challenge->excludeCredentials[0]->id );

		// Check transient is set
		$this->assertEquals( 1, get_transient( 'webauthn_challenge_' . $user_id . '_' . base64URL_encode( 'abcd' ) ) );
	}

	/**
	 * test new_device_registration
	 * 
	 * @since 1.0.0
	 */
	function test_new_device_registration() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		// throws if empty description.
		$thrown = false;
		try {
			$collection->new_device_registration( 'dummy', 'cred', 'pub', 'desc' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_CHALLENGE_DO_NOT_MATCH, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		$challenge_key = 'webauthn_challenge_' . $user_id . '_' . base64URL_encode( 'abcd' );
		// Rejected if credential already used in the system
        $user_id2 = $factory->create( [
        ] );
		
		$user2 = new \WP_User( $user_id2 );
		$collection2 = new Devices_Of_User( $user2 );
		$collection2->register_device( 'dummy_cred', 'pub', 'desc' );
		
		set_transient( $challenge_key, 1, 10 );

		// throws if credentials already used with another user.
		$thrown = false;
		try {
			$collection->new_device_registration( base64URL_encode( 'abcd' ), 'dummy_cred', 'pub', '' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_CREDENTIAL_USED, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		set_transient( $challenge_key, 1, 10 );

		// throw if credential is on the same user but different public key.
		$collection->register_device( 'dummy_cred2', 'pub', 'desc2' );
		$thrown = false;
		try {
			$collection->new_device_registration( base64URL_encode( 'abcd' ), 'dummy_cred2', 'pub2', '' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_PUBLIC_KEY_MISMATCH, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		set_transient( $challenge_key, 1, 10 );

		// throw if empty description.
		$thrown = false;
		try {
			$collection->new_device_registration( base64URL_encode( 'abcd' ), 'dummy_yummy', 'pub2', '' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_NO_DESCRIPTION, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		set_transient( $challenge_key, 1, 10 );

		// If credential and public key already match at the user just update description..
		$collection->register_device( 'dummy_cred3', 'pub', 'desc3' );
		$collection->new_device_registration( base64URL_encode( 'abcd' ), 'dummy_cred3', 'pub', 'change' );
		$devices = $collection->devices();
		$this->assertSame( 'change', $devices['dummy_cred3']->description() );

		// device registered sucessfully.
		set_transient( $challenge_key, 1, 10 );
		$collection->register_device( 'dummy_cred4', 'pub', 'new' );
		$collection->new_device_registration( base64URL_encode( 'abcd' ), 'dummy_cred3', 'pub', 'change' );
		$devices = $collection->devices();
		$this->assertSame( 'dummy_cred4', $devices['dummy_cred4']->credential_id );
		$this->assertSame( 'pub', $devices['dummy_cred4']->public_key );
		$this->assertSame( 'new', $devices['dummy_cred4']->description() );

		// Throw if trying to add device with non unique description
		set_transient( $challenge_key, 1, 10 );
		$thrown = false;
		try {
			$collection->new_device_registration( base64URL_encode( 'abcd' ), 'fail', 'fail', 'new' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_DESCRIPTION_USED, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// Make sure there are 5 registered devices at the collection
		for ( $i=1; $i<6; $i++ ) {
			try {
					$collection->register_device( 'cred' . $i, 'pub' .$i, 'desc ' . $i );
			} catch ( \Exception $e ) {
				// ignore exceptions as normaly regardless of why something failed
				// we should have 5 registered devices after the loop.
				;
			}
		}

		// Trying to add more devices throws.
		set_transient( $challenge_key, 1, 10 );

		$thrown = false;
		try {
			$collection->new_device_registration( base64URL_encode( 'abcd' ), 'dummy_cred6', 'pub2', 'desc6' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_CAN_NOT_ADD_DEVICE, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * test set_device_description
	 * 
	 * @since 1.0.0
	 */
	function test_set_device_description() {
		$factory = $this->factory->user;
        $user_id = $factory->create( [
        ] );
		
		$user = new \WP_User( $user_id );
		$collection = new Devices_Of_User( $user );

		$collection->register_device( 'cred', 'pub', 'desc' );
		$collection->register_device( 'cred2', 'pub2', 'desc2' );

		// Exception trying to set empty description.
		$thrown = false;
		try {
			$collection->set_device_description( 'cred', '' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_NO_DESCRIPTION, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );
		
		// Exception trying to set existing description.
		$thrown = false;
		try {
			$collection->set_device_description( 'cred', 'desc2' );
		} catch (\RuntimeException $e ) {
			$this->assertSame( Devices_Of_User::EXCEPTION_DESCRIPTION_USED, $e->getCode() );
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// Check change happens.
		$collection->set_device_description( 'cred', 'desc6' );
		$devices = $collection->devices();
		$device = $devices['cred'];
		$this->assertSame( 'desc6', $device->description() );
	}
}