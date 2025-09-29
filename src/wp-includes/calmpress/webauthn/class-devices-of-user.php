<?php
/**
 * Implementation of a represntation of a the collection of devices registered for a user.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\webauthn;

use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialDescriptor;

use function calmpress\utils\base64URL_encode;

/**
 * A representation of a collection of webauthn registered devices for a user.
 *
 * @since 1.0.0
 */
class Devices_Of_User {

	// The meta key which used to store data on all registered devices.
	const STORAGE_META_KEY       = 'webauthn_devices';

	// The meta key used to store the credential id of the registered devices.
	const CREDENTIAL_ID_META_KEY = 'webauthn_credential';

	// Codes for exceptions

	// Indicates that all slots for devices for the user are used.
	const EXCEPTION_CAN_NOT_ADD_DEVICE = 1;

	// Indiates that the challenge reieved do not match any known challenges
	// generated for the user.
	const EXCEPTION_CHALLENGE_DO_NOT_MATCH = 2;

	// Credential id used by another user.
	const EXCEPTION_CREDENTIAL_USED = 3;

	// Public key do not match expected one.
	const EXCEPTION_PUBLIC_KEY_MISMATCH = 4;

	// The description already in use.
	const EXCEPTION_DESCRIPTION_USED = 5;

	// The description is empty string.
	const EXCEPTION_NO_DESCRIPTION = 6;

	// The device was not found.
	const EXCEPTION_DEVICE_NOT_FOUND = 7;

	/**
	 * The user which uses the devices which are registered.
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
	public function __construct( \WP_User $user	) {
		$this->user = $user;
	}

	/**
	 * Make sure the credentia ids meta much the credention ids of the
	 * registered devices.
	 * 
	 * @since 1.0.0
	 * 
	 * @param User_Of_Device[] The devices to sync against where the array index
	 *                         is the credential id of the device as binary string.
	 */
	private function sync_credial_ids( array $devices ):void {

		// Make sure credential ids in their own meta match what we got here.
		$credentials = get_user_meta( $this->user->ID, self::CREDENTIAL_ID_META_KEY, false );
		if ( ! $credentials ) {
			$credentials = [];
		}

		// decode the credentials to be able to compare them to the authenticator 
		// values.
		$credentials = array_map( '\calmpress\utils\base64URL_decode', $credentials );

		// remove credential which is not in the main authenticator data.
		foreach ( $credentials as $cred ) {
			if ( ! isset( $devices[ $cred ] ) ) {
				delete_user_meta( $this->user->ID, self::CREDENTIAL_ID_META_KEY, base64URL_encode( $cred ) );
			}
		}

		// Add credentials which are in the authenticator data, but not in credentials meta.
		foreach ( $devices as $cred => $device ) {
			if ( ! in_array( $cred, $credentials, true ) ) {
				add_user_meta( $this->user->ID, self::CREDENTIAL_ID_META_KEY, base64URL_encode( $device->credential_id ) );
			}
		}
	}

	/**
	 * The devices registered by the user.
	 * 
	 * Fetches data from the DB and construct relevant objects out of it.
	 * 
	 * @since 1.0.0
	 * 
	 * @return User_Of_Device[] where index is the device's client id as binary string.
	 */
	public function devices(): array {
		$ret = [];
		$data = get_user_meta( $this->user->ID, self::STORAGE_META_KEY, true );

		if ( ! is_array( $data ) ) {
			// Data is curropted, log a warning and clean the data.
			// Unlikely that we get an object, but better to protect agains it
			if ( is_object( $data ) ) {
				$log_data = '[object ' . get_class( $data) . ']';

				\calmpress\logger\Controller::log_warning_message(
					sprintf(
						'Curropted webauthn data for user %d, data not an array %s',
						$this->user->ID,
						$log_data
					),
					__FILE__,
					__LINE__
				);
			}

			$data = [];
		}

		foreach ( $data as $value ) {
			try {
				$o = User_Of_Device::unserialize( $value, $this );
				$ret[ $o->credential_id ] = $o;
			} catch ( \Exception $e ) {
				// Something was curropted in the DB, ignore this entry.
				;
			}
		}

		$this->sync_credial_ids( $ret );
		return $ret;
	}

	/**
	 * Store the list of devices associated with the user in the DB.
	 * 
	 * @since 1.0.0
	 * 
	 * @param User_Of_Device[] The devices to store where the array index
	 *                         is the credential id of the device as binary string.
	 */
	private function save_to_db( array $devices ):void {
		$store = [];
		foreach ( $devices as $d ) {
			$store[] = $d->serialize();
		}

		update_user_meta( $this->user->ID, self::STORAGE_META_KEY, $store );

		$this->sync_credial_ids( $devices );
	}

	/**
	 * Store the device data in the DB.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws \LogicException if the device do no belong to the collection.
	 */
	public function store( User_Of_Device $device ): void {
		if ( $this->user->ID != $device->user_devices_collection->user->ID ) {
			throw new \LogicException( 'Trying to store a device in non matching collection' );
		}

		$devices = $this->devices();
		$devices[ $device->credential_id ] = $device;

		$this->save_to_db( $devices );
	}

	/**
	 * DRY to check if description is used and throw the relevant exception
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $description   The description to check for.
	 * @param string $except_device A device not to check against identified by its
	 *               credentials. An empty string can be used to indicate no device.
	 * 
	 * @throws RuntimeException if the $description already used as anothers device
	 *                          desription.
	 */
	private function throw_if_description_used_or_empty( string $description, string $except_device = '' ): void {
		$description = trim( $description );

		if ( ! $description ) {
				throw new \RuntimeException( 'empty description', self::EXCEPTION_NO_DESCRIPTION );
		}

		$devices = $this->devices();

		foreach ( $devices as $device ) {
			if ( ( $device->credential_id !== $except_device ) && ( $device->description() === $description ) ) {
				throw new \RuntimeException( 'description already used', self::EXCEPTION_DESCRIPTION_USED );
			}
		}
	}

	/**
	 * Add a registered device by its credential id and public key.
	 * Store the device in the DB.
	 * 
	 * If device already exists with matching credentil id and public key just
	 * update description.
	 *
	 * @param string $credential_id The credential id of the authenticator as a binary string.
	 * @param string $public_key    The public key as a binary string.
	 * @param string $description   A text describing the device.
	 *
	 * @since 1.0.0
	 * 
	 * @return User_Of_Device Object representing the registered device.
	 * 
	 * @throws RuntimeException if no slots avaialable for new device, or device exists
	 *                          with mismatching public key, or desription already used.
	 *                          Specific reason indiated in exception code.
	 */
	public function register_device(
		string $credential_id,
		string $public_key,
		string $description
	): User_Of_Device {

		$description   = trim( $description );
		$credential_id = trim( $credential_id );
		$public_key    = trim( $public_key );

		if ( ! $credential_id || ! $public_key ) {
			throw new \RuntimeException( 'empty credential and/or public key' );
		}

		$devices = $this->devices();

		// If credentials already exists for the user just update description.
		$device = $this->device_for_credentials( $credential_id, $public_key );
		if ( $device ) {
			$this->throw_if_description_used_or_empty( $description, $credential_id );
			$device->set_description( $description );
			return $device;
		}

		// If only credential exists but public key do not match, throw.
		if ( array_key_exists( $credential_id, $devices ) ) {
			throw new \RuntimeException( 'public key mismatch', self::EXCEPTION_PUBLIC_KEY_MISMATCH );
		}

		$this->throw_if_can_not_add_new_device();

		$this->throw_if_description_used_or_empty( $description );

		$device = new User_Of_Device(
			$credential_id,
			$public_key,
			$description,
			new \DateTime( 'now' ),
			$this
		);

		$this->store( $device );

		return $device;
	}

	/**
	 * Remove a registered device by its credential id. Update the DB.
	 *
	 * @since 1.0.0
	 *
	 * @param string $credential_id The redential id identifying the device
	 *                              as binary string.
	 */
	public function remove_device( string $credential_id ): void {
		$devices = $this->devices();

		if ( ! array_key_exists( $credential_id, $devices ) ) {
			return;
		}

		unset( $devices[ $credential_id ] );
		$this->save_to_db( $devices );
	}

	/**
	 * Generate challenge string for the device register options.
	 * 
	 * Exists and marked protected to be able to do tests with known challenge.
	 * No actual support to overriding it by subclassing.
	 * 
	 * @since 1.0.0
	 * 
	 * @return string A binary string of 32 length.
	 */
	protected function challenge():string {
		return random_bytes( 32 );
	}

	/**
	 * Generate a binary string from an int, handling correctly the case of 
	 * values bigger than 32 bit int.
	 * 
	 * Used to generate strings from user ids.
	 * 
	 * @since 1.0.0
	 * 
	 * @param int $value The value to generate the string for.
	 * 
	 * @return string A binary string representation of the number.
	 */
	private static function packed_number( int $value ):string {
		$high = $value >> 32;         // upper 32 bits
    	$low  = $value & 0xFFFFFFFF;  // lower 32 bits
		return pack( 'NN', $high, $low );
	}

	/**
	 * Generate an integer from a binary string which is packed by packed_number.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $value The binary string.
	 * 
	 * @return int The integer represented by the binary string.
	 * 
	 * @throws \RunTimeException if string do not match the format supposed to be
	 *                           generate by packed_number.
	 */
	private static function unpacked_string( string $value ):int {
		$parts = unpack( 'Nhigh/Nlow', $value );
		if ( $parts === false ) {
			// bad format.
			throw new \RuntimeException( 'bad binary string format' );
		}

    	return ( $parts['high'] << 32 ) | $parts['low'];
	}

	/**
	 * Generate Relaying Party info for server initiated messages.
	 * 
	 * The info is used to identify the site by the authenticator.
	 * For non network sites it uses the site domain while for Networks
	 * it uses the main domain of the site.
	 *
	 * Marked protected to be able to do tests with known info.
	 * No actual support to overriding it by subclassing.
	 *
	 * @since 1.0.0
	 *
	 * @return PublicKeyCredentialRpEntity
	 */
	protected function rp_info():PublicKeyCredentialRpEntity {
		if ( is_multisite() ) {
			$network = get_network();
			$rp_name = $network->site_name;
			$rp_id   = $network->domain;
		} else {
			$rp_name = get_bloginfo( 'name' );
			$rp_id   = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		return new PublicKeyCredentialRpEntity(
			$rp_name,
			$rp_id
		);
	}

	/**
	 * Generate a list of public key credential to be used in webauthn messages.
	 *
	 * @since 1.0.0
	 * 
	 * @return PublicKeyCredentialDescriptor[] The credential id used as index to help
	 *                                         in testing, but meaningless otherwise.
	 */
	private function devices_as_webautn_array(): array {
		$ret = [];
		foreach ( $this->devices() as $cred ) {
			$ret[] = new PublicKeyCredentialDescriptor(
				'public-key',
				$cred->credential_id,
				[] // optional transports, can leave empty
			);
		}

		return $ret;
	} 

	/**
	 * Create a challenge information for the user registering a new device.
	 * 
	 * The challenge is being stored as transient for when the browser signals
	 * that the user had authorised the device.
	 * 
	 * @since 1.0.0
	 * 
	 * @return PublicKeyCredentialCreationOptions A structure containing relevant options 
	 *                                            that should be sent to the browser.
	 * 
	 * @throws RuntimeException if no slots avaialable for new device.
	 */
	public function new_device_challenge(): PublicKeyCredentialCreationOptions {

		$this->throw_if_can_not_add_new_device();

		$rp_entity = $this->rp_info();

		// Create User info to be displayed and reported on authentication, and
		// reported back as needed when user autheticates.
		$id = self::packed_number( $this->user->ID );

		$user_entity = new PublicKeyCredentialUserEntity(
			$this->user->display_name,    // user handle display name
			$id,                          // unique user ID
			$this->user->display_name     // user name
		);

		// Valid public key encryptions.
		$pubKeyCredParams = [
			new PublicKeyCredentialParameters( 'public-key', -7 ),   // ES256
			new PublicKeyCredentialParameters( 'public-key', -257 ), // RS256
		];

		$challenge = $this->challenge();

		$auth_selection = new AuthenticatorSelectionCriteria(
			AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
			AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
			AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED
		);

		// Tell the authenticator our already registered credentials
		// so it can avoid them when creating a new one.
		$exclude_credentials = $this->devices_as_webautn_array();

		$options = new PublicKeyCredentialCreationOptions(
			$rp_entity,
			$user_entity,
			$challenge,
			$pubKeyCredParams,
			$auth_selection,
			null,
			$exclude_credentials
		);

		// Save challenge at the server for 10 min at an easily retrievable
		// format which allows the user to register more than one device at same time.
		// The dat of '1' is there just because some data is needed, it has no meaning by itsel.
		set_transient( 'webauthn_challenge_' . $this->user->ID . '_' . base64URL_encode( $challenge ), 1, 10 * MINUTE_IN_SECONDS );

		return $options;
	}

	/**
	 * Handles the device registration as part of handling the relevant ajax
	 * request, verifying the challange, avoiding credential id duplication and using
	 * lower level api to do the actual DB modifications.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $challenge  Value to authenticate that the user wanted to register a device.
	 *                           base64url encoded.
	 * @param string $credential_id The credential used to identify the device for the registration
	 *                           raw binary string.
	 * @param string $public_key The "secret" key used to identify the registration.
	 *                           raw binary string.
	 * 
	 * @param string $description A text which is human description of the device.
	 * 
	 * @return User_Of_Device The device which was created.
	 * 
	 * @throws RuntimeException If can not register the device, reason is given in the
	 *                          exeptions code.
	 */
	public function new_device_registration(
		string $challenge,
		string $credential_id,
		string $public_key,
		string $description
		): User_Of_Device {

		$transient_key = 'webauthn_challenge_' . $this->user->ID . '_' . $challenge;
		if ( ! get_transient( $transient_key ) ) {
			throw new \RuntimeException( 'Challenge mismatch', self::EXCEPTION_CHALLENGE_DO_NOT_MATCH );
		}
		delete_transient( $transient_key );

		// if the credential already used in the system for another user, reject the request unless
		$used_user_id = self::credential_is_used( $credential_id );
		if ( $used_user_id !== false ) {
			// maybe we tried to re-register same device for the user.
			if ( $used_user_id !== $this->user->ID ) {
				// authenticator registered with another user.
				throw new \RuntimeException( 'Authenticator registered with another user', self::EXCEPTION_CREDENTIAL_USED );
			}
		}

		$this->throw_if_can_not_add_new_device();
		return $this->register_device( $credential_id, $public_key, $description );
	}

	/**
	 * Handles changing a device desription as part of handling the relevant ajax
	 * request, avoiding desription duplication and using
	 * lower level api to do the actual DB modifications.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $credential_id The credential used to identify the device for the registration
	 *                           raw binary string.
	 * 
	 * @param string $description A text which is human description of the device.
	 * 
	 * @throws RuntimeException If can not set the description, reason is given in the
	 *                          exeptions code.
	 */
	public function set_device_description(
		string $credential_id,
		string $description
		): void {

		$description   = trim( $description );
		$credential_id = trim( $credential_id );

		if ( ! $credential_id ) {
			throw new \RuntimeException( 'empty credential' );
		}

		$devices = $this->devices();
		// Does the device even exists.
		if ( ! array_key_exists( $credential_id, $devices ) ) {
			throw self::EXCEPTION_DEVICE_NOT_FOUND;
		}

		// Check no duplicated description or empty one.
		$this->throw_if_description_used_or_empty( $description, $credential_id );

		$devices[ $credential_id ]->set_description( $description );
	}

	/**
	 * Indicate if the collection hold the maximum number of devices it may.
	 * 
	 * @since 1.0.0
	 * 
	 * @return true if device number limit was not reached, false otherwise.
	 */
	public function can_add_device() : bool {
		if ( count( $this->devices() ) < 5 ) {
			return true;
		}

		return false;
	}

	/**
	 * Same as can_add_device but throws instead of returning value when number of 
	 * devices reached limit.
	 * 
	 * @since 1.0.0
	 * 
	 * @throws RuntimeException if device number limit was reached with 
	 *                          code = EXCEPTION_CAN_NOT_ADD_DEVICE.
	 */
	private function throw_if_can_not_add_new_device() : void {
		if ( ! $this->can_add_device() ) {
			throw new \RuntimeException( 'Can not register more device for this user', self::EXCEPTION_CAN_NOT_ADD_DEVICE );
		}
	}

	/**
	 * Find a device using same credential and public key.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $credential_id A binary string containing the credential value.
	 * @param string $public_key    A binary string containing the publi key value.
	 * 
	 * @return ?User_Of_Device The matching object if a match found, null otherwise.
	 */
	private function device_for_credentials( string $credential_id, string $public_key ): ?User_Of_Device {
		$devices = $this->devices();
		if ( array_key_exists( $credential_id, $devices ) ) {
			if ( $devices[ $credential_id ]->public_key === $public_key ) {
				return $devices[ $credential_id ];
			}
		}

		return null;
	}

	/**
	 * Check if a credential is already associated with any user.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string $credential_id A binary string containing the credential value.
	 * 
	 * @return false if the credential is not used, otherwise the user id of the user
	 *         with which it is associated.
	 */
	private static function credential_is_used( string $credential_id ):bool|int {
		$users = get_users( [
			'meta_key'   => self::CREDENTIAL_ID_META_KEY,
			'meta_value' => base64url_encode( $credential_id ),
			'number'     => 1,
			'fields'     => 'ID',
		] );

		if ( count( $users ) === 0 ) {
			return false;
		}

		return (int) $users[0];
	}
}
