<?php
/**
 * Implementation of a represntation of a the collection of devices registered for a user.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\webauthn;

/**
 * A representation of a collection of webauthn registered devices for a user.
 *
 * @since 1.0.0
 */
class Devices_Of_User {

	const META_KEY = 'webauthn_devices';

	/**
	 * The user which uses the devices are registered.
	 *
	 * @since 1.0.0
	 */
	public readonly \WP_User $user;

	/**
	 * Create an object representing the user's registered devices.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \WP_User  $user The user which uses the devices to authenticate.
	 */
	public function __construct(
		\WP_User  $user
	) {
		$this->user = $user;
	}

	/**
	 * The devices registered by the user.
	 * 
	 * Fetches data from the DB and construct relevant objects out of it.
	 * 
	 * @since 1.0.0
	 * 
	 * @return User_Of_Device[]
	 */
	public function devices(): array {
		$data = get_post_meta( $this->user->ID, self::META_KEY, true );
		if ( ! $data ) {
			// No registered device in the DB.
			return [];
		}

		if ( ! is_array( $data ) ) {
			// Data is curropted, log a warning and clean the data.
			// Unlikely that we get an object, but better to protect agains it
			if ( is_object( $data ) ) {
				$data = '[object ' . get_class( $data) . ']';
			}

			\calmpress\logger\Controller::log_warning_message(
				sprintf(
					'Curropted webauthn data for user %d, data not an array %s',
					$this->user->ID,
					$data

				),
				__FILE__,
				__LINE__
			);

			return [];
		}

		$ret = [];
		foreach ( $data as $value ) {
			try {
				$o = User_Of_Device::unserialize( $value, $this );
				$ret[ $o->public_key->base64URL ] = $o;
			} catch ( \Exception $e ) {
				// Something was curropted in the DB, ignore this entry.
				;
			}
		}

		return $ret;
	}

	/**
	 * Store the list of devices associated with the user in the DB.
	 * 
	 * @since 1.0.0
	 * 
	 * @param User_Of_Device[] $devices The list of devices.
	 */
	private function save_to_db( array $devices ):void {
		$store = [];
		foreach ( $devices as $d ) {
			$store[] = $d->serialize();
		}

		update_post_meta( $this->user->ID, self::META_KEY, $store );
	}

	/**
	 * Store the device data in the DB.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws \RuntimeException if the device do no belong to the collection.
	 */
	public function store( User_Of_Device $device ): void {
		if ( $this->user->ID != $device->user_devices_collection->user->ID ) {
			throw new \RuntimeException( 'trying to store a device in non matching collection' );
		}

		$devices = $this->devices();
		$devices[ $device->public_key->base64URL ] = $device;

		$this->save_to_db( $devices );
	}

	/**
	 * Check if device with specific public key is registered, if it is
	 * mark the authentication time stamp to now.
	 * 
	 * @param Public_Key $public_key The public key to check.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if device is registered, false otherwise. 
	 */
	public function authenticate_device( Public_Key $public_key) : bool {
		$devices = $this->devices();

		if ( ! array_key_exists( $public_key->base64URL, $devices ) ) {
			return false;
		}

		$device = $devices[ $public_key ];
		$device->set_last_authentication_time( new \DateTime( 'now' ) );

		return true;
	}

	/**
	 * Add a registered device by its public key. Store the device in the DB.
	 * 
	 * Ignored if device already exists.
	 *
	 * @param Public_Key $public_key The public key to register.
	 *
	 * @since 1.0.0
	 * 
	 * @return User_Of_Device Object representing the registered device.
	 */
	public function register_device( Public_Key $public_key ): User_Of_Device {
		$devices = $this->devices();

		if ( array_key_exists( $public_key->base64URL, $devices ) ) {
			return $devices[ $public_key->base64URL ];
		}

		$device = new User_Of_Device( $public_key, '', new \DateTime( 'now' ), $this );
		$this->store( $device );

		return $device;
	}

	/**
	 * Remove a registered device by its public key. Update the DB.
	 *
	 * @param Public_Key $public_key The public key to of the device to remove.
	 *
	 * @since 1.0.0
	 * 
	 * @return User_Of_Device Object representing the registered device.
	 */
	public function remove_device( Public_Key $public_key ): void {
		$devices = $this->devices();

		if ( ! array_key_exists( $public_key->base64URL, $devices ) ) {
			return;
		}

		unset( $devices[ $public_key->base64URL ] );
		$this->save_to_db( $devices );
	}
}
