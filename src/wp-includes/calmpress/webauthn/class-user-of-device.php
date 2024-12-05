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

/**
 * A representation of a webauthn authenticated user using a specific device.
 *
 * @since 1.0.0
 */
class User_Of_Device {

	/**
	 * The public key associated with the user's authentication on with the device.
	 *
	 * @since calmPress 1.0.0
	 */
	public readonly string $public_key;

	/**
	 * The human readable description.
	 *
	 * @since calmPress 1.0.0
	 */
	public readonly string $description;

	/**
	 * The date and time of the last time in which the device was used to authenticate
	 * the user.
	 *
	 * @since calmPress 1.0.0
	 */
	public readonly \DateTime $last_autheticated_at;

	/**
	 * The user which uses the device to authenticate.
	 *
	 * @since calmPress 1.0.0
	 */
	public readonly \WP_User $user;

	/**
	 * Create an object representing the user's authentication with the device.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @param string    $public_key  The public key identifying the user on the device
	 *                               when authenticating.
	 * @param string    $description The human readable description.
	 * @param \DateTime $last_used   The latest date and time the user had authenticated
	 *                               with the device.
	 * @param \WP_User  $user        The user which uses the device to authenticate.
	 */
	public function __construct(
		string    $public_key,
		string    $description,
		\DateTime $last_used,
		\WP_User  $user
	) {
		$this->public_key           = $public_key;
		$this->description          = $description;
		$this->last_autheticated_at = $last_used;
		$this->user                 = $user; 
	}

	/**
	 * Create a string represantation of the object which can be unserialized.
	 * 
	 * The user context is not serialized and it is assumed that the correct user
	 * information will be provided when unserializing. 
	 * 
	 * The generated json has the following fields
	 * - p  which has the public key
	 * - de which has the description
	 * - da which contains latest authentication time formatted as a unix time stamp.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string The represantation. 
	 */
	public function serialize() : string {
		$o = new \stdClass();
		$o->p  = $this->public_key;
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
	 * @param string   $data The string containing the serialized represantation.
	 * @param \WP_User $user The user associated with authetication from this device.
	 * 
	 * @return User_Of_Device An object created based from parsing the $data.
	 * @throws RuntimeException If the serialized data is invalid.
	 */
	public static function unserialize( string $data, \WP_User $user ): User_Of_Device {
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
		foreach ( ['p', 'de', 'da' ] as $key ) {
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

		return new User_Of_Device( $o['p'], $o['de'], $date, $user );
	}
}
