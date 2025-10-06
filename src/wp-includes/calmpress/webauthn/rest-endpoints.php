<?php
/**
 * Registration of rest endpoints used for webauthn.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\webauthn\rest_endpoints;

use function calmpress\utils\base64URL_decode;
use function calmpress\utils\base64URL_encode;

use calmpress\webauthn\Devices_Of_User;

/**
 * A DRY to generate a response indicating invalid reuest.
 */
function invalid_request_response(): \WP_REST_Response {
    return new \WP_REST_Response(
        [ 'message' => __( 'Invalid request' ) ],
        400
    );
}

/**
 * Handle the request to get a challenge when a user tries to register
 * a new authenticator.
 * 
 * @since 1.0.0
 */
function create_challenge(): \WP_REST_Response {
	$user = wp_get_current_user();

	try {
		$options = $user->webauthn_registered_devices()->new_device_challenge();
		return new \WP_REST_Response( $options, 200 );
	} catch ( \RuntimeException $e ) {
		return new \WP_REST_Response(
	        [ 'message' => __( 'Can not add more devices.' ) ],
            400
        );
	}
}

/**
 * Handle the reuest for revoking an authenticator credentials.
 *
 * @since 1.0.0
 */
function revoke( \WP_REST_Request $request ): \WP_REST_Response {

	$user = wp_get_current_user();

	$cred = base64URL_decode( $request->get_param( 'credential_id' ) );

	try {
		$could_add = $user->webauthn_registered_devices()->can_add_device();
		$user->webauthn_registered_devices()->remove_device( $cred );
		$can_add = $user->webauthn_registered_devices()->can_add_device();

		// Message should reflect if a device can be registered now, while it could
		// not be before revoking.
		if ( $can_add && ( $can_add !== $could_add ) ) {
			$message = __( 'Device was removed succesfuly, a new device can be registered now.');
		} else {
			$message = __( 'Device was removed succesfuly.');
		}
		return new \WP_REST_Response(
            [
                'message' => $message,
                'can_add' => $can_add
		    ],
            200
        );
	} catch ( \RuntimeException $e ) {
        return new \WP_REST_Response(
            [ 'message' => __( 'Failed removing the device.' ) ],
            500
        );
    }
}

/**
 * Handle the request to register an authenticator.
 * 
 * @since 1.0.0
 */
function register_device( \WP_REST_Request $request ): \WP_REST_Response {
	$user = wp_get_current_user();

	$desc    = trim( $request->get_param( 'name' ) );
	$payload = $request->get_param( 'payload' );
	$attestationData = base64_decode( $payload['response']['attestationObject'] );
    $clientDataJSON  = base64_decode( $payload['response']['clientDataJSON'] );

	// Step 1. Decode clientDataJSON (plain JSON)
	$clientData = json_decode( $clientDataJSON, true );
	if ( ! $clientData || ! isset( $clientData['challenge'] ) ) {
		 return invalid_request_response();
	}

	// Step 2. Verify challenge
	$challenge = $clientData['challenge']; // assumed to be base64url encoded

	// Step 3. Decode attestationObject (CBOR)
	$decoder = new \CBOR\Decoder();
	$attestation = $decoder->decode(new \CBOR\StringStream( $attestationData ) );

	if ( ! $attestation instanceof \CBOR\MapObject ) {
		 return invalid_request_response();
	}
	/** @var \CBOR\MapObject $attestation */

	// Extract authData (binary blob)

	/** @var \CBOR\ByteStringObject $authDataCbor */
	$authDataCbor = $attestation->get( 'authData' );
	$authData     = $authDataCbor->getValue();

	// Step 4. Parse authenticator data
	$authDataLoader    = \Webauthn\AuthenticatorDataLoader::create();
	$authenticatorData = $authDataLoader->load( $authData );

	// Extract credential ID + public key
	$credential_id = $authenticatorData->attestedCredentialData->credentialId;
	$public_key    = $authenticatorData->attestedCredentialData->credentialPublicKey;
	
	try {
		$device = $user->webauthn_registered_devices()->new_device_registration( $challenge, $credential_id, $public_key, $desc );
		$can_add = $user->webauthn_registered_devices()->can_add_device();
		if ( ! $can_add ) {
			$message = __( 'Device had been registered succesfuly. You have reached the maximal number of device that can be registered.' );
		} else {
			$message = __( 'Device had been registered succesfuly.' );
		}
        return new \WP_REST_Response(
		    [
                'message'     => $message,
                'cred'        => base64URL_encode( $device->credential_id ),
                'description' => $device->description(),
                'last_used'   => $device->human_last_used(),
                'can_add'     => $can_add,
            ],
            200
        );

	} catch ( \RuntimeException $e ) {
		$message = match ( $e->getCode() ) {
			Devices_Of_User::EXCEPTION_CAN_NOT_ADD_DEVICE => __( 'You have already registered the maximum number of devices.' ),
			Devices_Of_User::EXCEPTION_CHALLENGE_DO_NOT_MATCH => __( 'Seems like too much time passes since you started the registration process untill you authenticated yourself, you shou try again.' ),
			Devices_Of_User::EXCEPTION_CREDENTIAL_USED => __( 'The device being regitered was already registered. This might indicate some problem with your device.' ),
			Devices_Of_User::EXCEPTION_PUBLIC_KEY_MISMATCH => __( 'The device being regitered was already registered for you but with incompatible data. You should try to remove it from the registered devices and try to register again.' ),
			Devices_Of_User::EXCEPTION_DESCRIPTION_USED => __( 'The description is already used by another registered device. You should use a unique description.' ),
			Devices_Of_User::EXCEPTION_NO_DESCRIPTION => __( 'No description was given.' ),
			default => __( 'Unknown error prevented registration.' )
		};
        return new \WP_REST_Response(
		    [ 'message' => $message ],
            400
        );
	}
}

/**
 * Handler for reuest for changing an authenticator description.
 *
 * @since 1.0.0
 */
function set_description( \WP_REST_Request $request ): \WP_REST_Response {

	$user = wp_get_current_user();

	$cred = base64URL_decode( trim( $request->get_param( 'credential_id' ) ) );
	$description = trim( $request->get_param( 'description' ) );

	try {
		$user->webauthn_registered_devices()->set_device_description( $cred, $description );
		$devices = $user->webauthn_registered_devices()->devices();
        return new \WP_REST_Response(
            [
                'message'     => __( 'Description was updated succesfuly.' ),
                'description' => $devices[ $cred ]->description(),
            ],
            200
        );
	} catch ( \RuntimeException $e ) {
		$message = match ( $e->getCode() ) {
			Devices_Of_User::EXCEPTION_DESCRIPTION_USED => __( 'The description is already used by another registered device. You should use a unique description.' ),
			Devices_Of_User::EXCEPTION_NO_DESCRIPTION => __( 'No description was given.' ),
			Devices_Of_User::EXCEPTION_DEVICE_NOT_FOUND => __( 'No such device.' ),
			default => __( 'Unknown error prevented update.' )
		};
        return new \WP_REST_Response(
		    [ 'message' => $message ],
            400
        );
	}
}

add_action(
    'rest_api_init',
    function () {
        \calmpress\utils\add_current_user_post_endpoint(
            'calmpress',
            'webauthn/create_challenge',
            __NAMESPACE__ . '\\create_challenge',
            []
        );

        \calmpress\utils\add_current_user_post_endpoint(
            'calmpress',
            'webauthn/register_device',
            __NAMESPACE__ . '\\register_device',
            [ 'name', 'payload' ]
        );

        \calmpress\utils\add_current_user_post_endpoint(
            'calmpress',
            'webauthn/revoke',
            __NAMESPACE__ . '\\revoke',
            [ 'credential_id' ]
        );

        \calmpress\utils\add_current_user_post_endpoint(
            'calmpress',
            'webauthn/set_description',
            __NAMESPACE__ . '\\set_description',
            [ 'credential_id', 'description' ]
        );
    }
);
