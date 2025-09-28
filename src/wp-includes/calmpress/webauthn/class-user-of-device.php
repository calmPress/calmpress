<?php
/**
 * Implementation of a represntation of a user authentication with webauthn on
 * specific device.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\webauthn;

use function calmpress\utils\base64URL_decode;
use function calmpress\utils\base64URL_encode;

/**
 * A representation of a webauthn authenticated user using a specific device.
 * 
 * Meant to be an internal class of Devices_Of_User, and its API should not
 * be used from outside of that class.
 *
 * @since 1.0.0
 */
class User_Of_Device {

	/**
	 * The public key associated with the user's authentication on with the device.
	 * A binary string.
	 *
	 * @since 1.0.0
	 */
	public readonly string $public_key;

	/**
	 * The credential id associated with the user's authentication on with the device.
	 * A binary string.
	 *
	 * @since 1.0.0
	 */
	public readonly string $credential_id;

	/**
	 * The human readable description.
	 *
	 * @since 1.0.0
	 */
	private string $description;

	/**
	 * The date and time of the last time in which the device was used to authenticate
	 * the user.
	 *
	 * @since 1.0.0
	 */
	private \DateTime $last_autheticated_at;

	/**
	 * The collection of devices in which is device is included.
	 *
	 * @since 1.0.0
	 */
	public readonly Devices_Of_User $user_devices_collection;

	/**
	 * Create an object representing the user's authentication with the device.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string     $credential_id The credential id identifying the user on the device
	 *                                  when authenticating.
	 * @param string     $public_key  The public key identifying the user on the device
	 *                                when authenticating.
	 * @param string     $description The human readable description.
	 * @param \DateTime  $last_used   The latest date and time the user had authenticated
	 *                               with the device.
	 * @param Devices_Of_User $user_devices_collection The collection of devices
	 *                                                 in which this device belongs.
	 */
	public function __construct(
		string          $credential_id,
		string          $public_key,
		string          $description,
		\DateTime       $last_used,
		Devices_Of_User $user_devices_collection
	) {
		$this->credential_id           = $credential_id;
		$this->public_key              = $public_key;
		$this->description             = $description;
		$this->last_autheticated_at    = $last_used;
		$this->user_devices_collection = $user_devices_collection; 
	}

	/**
	 * The device's description.
	 * 
	 * @since 1.0.0
	 * 
	 * @return string The description of the device.
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * Set the desctiption.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $description The description to set.
	 */
	public function set_description( string $description ): void {
		$this->description = $description;
		$this->user_devices_collection->store( $this );
	}

	/**
	 * The latest authentication from this device.
	 * 
	 * @since 1.0.0
	 * 
	 * @return DateTime The time of last authentication.
	 */
	public function last_authentication_time(): \DateTime {
		return $this->last_autheticated_at;
	}

	/**
	 * Set the time of the latest authentication from this device.
	 * 
	 * @since 1.0.0
	 * 
	 * @param \DateTime $time The time of the latest authentication.
	 */
	public function set_last_authentication_time( \DateTime $time ): void {
		$this->last_autheticated_at = $time;
		$this->user_devices_collection->store( $this );
	}

	/**
	 * Helper function which creates a text describing how long ago the
	 * device was used to authenticate.
	 * 
	 * @since 1.0.0
	 * 
	 * @return string
	 */
	public function human_last_used(): string {
		/* translators: %s: Time since last time the devie was used to authenticate. */
		return sprintf( __( '%s ago' ), human_time_diff( $this->last_autheticated_at->format( 'U' ) ) );
	}
	/**
	 * Create a string represantation of the object which can be unserialized.
	 * 
	 * The user context is not serialized and it is assumed that the correct user
	 * information will be provided when unserializing. 
	 * 
	 * The generated json has the following fields
	 * - c  which has the credential id as a base64URL encoded string.
	 * - p  which has the public key as a base64URL encoded string.
	 * - de which has the description
	 * - da which contains latest authentication time formatted as a unix time stamp.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string The represantation. 
	 */
	public function serialize() : string {
		$o = new \stdClass();
		$o->c  = base64URL_encode( $this->credential_id );
		$o->p  = base64URL_encode( $this->public_key );
		$o->de = $this->description;
		$o->da = (string) $this->last_autheticated_at->getTimestamp();
		return json_encode( $o );
	}

	/**
	 * Unserialize priviosly serialize device represantation into a device object.
	 * 
	 * @see serialize for the expected format of the json string.
	 * 
	 * @since calmpress 1.0.0
	 * 
	 * @param string          $data The string containing the serialized represantation.
	 * @param Devices_Of_User $user_devices_collection The collection of devices
	 *                                                 in which this device belongs.
	 * 
	 * @return User_Of_Device An object created based from parsing the $data.
	 * 
	 * @throws RuntimeException If the serialized data is invalid.
	 */
	public static function unserialize( string $data, Devices_Of_User $user ): User_Of_Device {
		$o = json_decode( $data, true );

		// check if valid json.
		if ( ! $o ) {
			throw new \RuntimeException( 
				sprintf(
					'data %s is not json encoded',
					$data
				)
			);
		}

		// Check for all expected fields.
		foreach ( ['c', 'p', 'de', 'da' ] as $key ) {
			if ( ! isset( $o[ $key ] ) ) {
				throw new \RuntimeException( 
					sprintf(
						'missing field %s in serialised data %s',
						$key,
						$data
					)
				);
			}

			if ( ! is_string( $o[ $key ] ) ) {
				throw new \RuntimeException( 
					sprintf(
						'field %s is not a string in serialised data %s',
						$key,
						$data
					)
				);
			}
		}

		try {
			$date = new \DateTime( '@' . $o['da'] );
		} catch ( \Exception $e ) {
			if ( $date === false ) {
				throw new \RuntimeException( 
					sprintf(
						'Date can not be parsed from data %s',
						$data
					)
				);
			}
		}

		return new User_Of_Device(
			base64URL_decode( $o['c'] ),
			base64URL_decode( $o['p'] ),
			$o['de'],
			$date,
			$user
		);
	}
}
