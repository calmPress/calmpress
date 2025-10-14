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
 * 
 * @since 1.0.0
 * 
 * @return \WP_REST_Response with a 400 code and invalid reuest style of message.
 */
function invalid_request_response(): \WP_REST_Response {
	return new \WP_REST_Response(
		[ 'reason' => 'Invalid request' ],
		500
	);
}

/**
 * Handle the request to get a challenge when a user tries to register
 * a new authenticator.
 * 
 * @since 1.0.0
 * 
 * @return \WP_REST_Response A 400 response with associate text if can not add device.
 *                           A 200 with the challenge options as the brower expects
 *                           to get to start registration.
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
 * Extract the challenge from the client data part of the request.
 * 
 * @since 1.0.0
 * 
 * @param string $client_data The clientDataJSON field of the request. Assumed to be
 *                            base64url encoded.
 * 
 * @return string The challenge embedded in the data base64URL encoded,
 *                or empty string if parsing failed. 
 * 
 */
function extract_challenge( string $client_data ):string {
	$clientDataJSON  = base64URL_decode( $client_data );

	// Step 1. Decode clientDataJSON (plain JSON)
	$clientData = json_decode( $clientDataJSON, true );
	if ( ! $clientData || ! isset( $clientData['challenge'] ) ) {
		 return '';
	}

	return $clientData['challenge']; // assumed to be base64url encoded
}

/**
 * Handle the request for revoking an authenticator credentials.
 *
 * @since 1.0.0
 * 
 * @return \WP_REST_Response A 500 response if was unexpected failure.
 *                           A 200 with message and attributes indicating
 *                           if more devices can be added.
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
			[ 'message' => \calmpress\utils\unknow_error_text() ],
			500
		);
	}
}

/**
 * Handle the request to register an authenticator.
 * 
 * @since 1.0.0
 * 
 * @return \WP_REST_Response A 400 if operation failed and message explaning the failure.
 *                           A 200 If device added with a message indicating if
 *                           more can be added and the various properties of the
 *                           devie as know to the server (credential id, descripttion, last use time).
 */
function register_device( \WP_REST_Request $request ): \WP_REST_Response {
	$user = wp_get_current_user();

	$desc    = trim( $request->get_param( 'name' ) );
	$payload = $request->get_param( 'payload' );
	$attestationData = base64_decode( $payload['response']['attestationObject'] );

	$challenge = extract_challenge( $payload['response']['clientDataJSON'] );
	if ( $challenge === '' ) {
		 return invalid_request_response();
	}

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
			Devices_Of_User::EXCEPTION_CHALLENGE_DO_NOT_MATCH => __( 'Seems like too much time passed since you started the registration process untill you authenticated yourself, you should try again.' ),
			Devices_Of_User::EXCEPTION_CREDENTIAL_USED => __( 'The device being registered was already registered. This might indicate some problem with your device.' ),
			Devices_Of_User::EXCEPTION_PUBLIC_KEY_MISMATCH => __( 'The device being registered was already registered for you but with incompatible data. You should try to remove it from the registered devices and try to register again.' ),
			Devices_Of_User::EXCEPTION_DESCRIPTION_USED => __( 'The description is already used by another registered device. You should use a unique description.' ),
			Devices_Of_User::EXCEPTION_NO_DESCRIPTION => __( 'No description was given.' ),
			default => \calmpress\utils\unknow_error_text()
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
 *  
 * @return \WP_REST_Response A 400 response with associate text if can not add device.
 *                           A 200 with a success message and the description as
 *                           stored in the server.
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
			default => \calmpress\utils\unknow_error_text()
		};
		return new \WP_REST_Response(
			[ 'message' => $message ],
			400
		);
	}
}

/**
 * Handler for the API that generate a login challenge.
 *
 * @since 1.0.0
 * 
 * @return WP_REST_Response
 */
function login_challenge(): \WP_REST_Response {
	// Generate a random byte stream.
	$challenge = random_bytes( 32 );

	// Save challenge at the server for 5 min at an easily retrievable
	// format which allows to have more than one login challenge at same time.
	// The data of '1' is there just because some data is needed, it has no meaning by itsel.
	set_transient( 'webauthn_challenge_login_' . base64URL_encode( $challenge ), 1, 1 * HOUR_IN_SECONDS );

	// Response structured like browser expects

	$data = [
		'challenge'        => base64URL_encode( $challenge ),
        'rpId'             => Devices_Of_User::rp_info()->id,
        'allowCredentials' => [],
        'userVerification' => 'preferred',
	];

	return new \WP_REST_Response( $data, 200 );
}

/**
 * Handle the request to register an authenticator.
 * 
 * @since 1.0.0
 * 
 * @return \WP_REST_Response A 400 if operation failed and message explaning the failure.
 *                           A 200 If device added with a message indicating if
 *                           more can be added and the various properties of the
 *                           devie as know to the server (credential id, descripttion, last use time).
 */
function login( \WP_REST_Request $request ): \WP_REST_Response {

	$t = $request->get_param( 'clientDataJSON' );

	// Check if the challenege was generate by us in the last 5 min.
	$challenge = extract_challenge( $t );

	// if challenge extarction failed send a 500
	if ( $challenge === '' ) {
		return invalid_request_response();
	}

	$transient_key = 'webauthn_challenge_login_' . $challenge;
	if ( ! get_transient( $transient_key ) ) {
		return new \WP_REST_Response(
			[ 'message' => __( 'Your login attempt took too long. Please try again.' ) ],
			400
		);
	}
	delete_transient( $transient_key );

	$credential_id = $request->get_param( 'credential_id' );
	$user_id = Devices_Of_User::credential_is_used( base64URL_decode( $credential_id ) );
	if ( $user_id === false ) {
		return new \WP_REST_Response(
			[ 'message' => __( 'Could not find a matching user.' ) ],
			400
		);
	}

	$webauthn_user = get_user_by( 'id', $user_id );

	// Set the authenticate filter to always authenticate the user in the context
	// of wp_signon so we will be able to use it.
	add_filter(
		'authenticate',
		function ( $user, $usernam, $password ) use ( $webauthn_user ) {
			return $webauthn_user;
		},
		19,
		3
	);

	$user = wp_signon();

	// Check if login was rejected for another reason.
	if ( is_wp_error( $user ) ) {
		return new \WP_REST_Response(
			[ 'message' => $user->get_error_message() ],
			400
		);
	}

	$redirect_to = $request->get_param( 'redirect_to' );

	/**
	 * Filters the login redirect URL.
	 *
	 * @since 3.0.0
	 *
	 * @param string           $redirect_to           The redirect destination URL.
	 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
	 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
	 */
	$redirect_to = apply_filters( 'login_redirect', $redirect_to, $redirect_to, $user );

	// sanitize the url, set it to empty string if seems invalid.
	if ( ! empty( $redirect_to ) ) {
		$redirect_to = wp_sanitize_redirect( $redirect_to );
		if ( ! wp_validate_redirect( $redirect_to, false ) ) {
			$redirect_to = '';
		}
	}

	// code taken from wp_login.php, find default url to redirect to if none given.
	if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || admin_url() === $redirect_to ) ) {
		// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
		if ( is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
			$redirect_to = user_admin_url();
		} elseif ( is_multisite() && ! $user->has_cap( 'read' ) ) {
			$redirect_to = get_dashboard_url( $user->ID );
		} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
			$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'user-edit.php' ) : home_url();
		}
	}

	return new \WP_REST_Response(
		[ 'redirect_to' => $redirect_to ],
		200
	);
}

add_action(
	'rest_api_init',
	/**
	 * Register the various end points to handle webauthn rest requests.
	 * 
	 * @since 1.0.0
	 */
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

		register_rest_route(
			'calmpress/webauthn',
			'/login_challenge',
			[
				'methods'             => 'POST',
				'callback'            => __NAMESPACE__ . '\\login_challenge',
				'permission_callback' => '__return_true', // No login required for login challenge
				'args'                => [], // no mandatory parameters
			]
		);

		register_rest_route(
			'calmpress/webauthn',
			'/login',
			[
				'methods'             => 'POST',
				'callback'            => __NAMESPACE__ . '\\login',
				'permission_callback' => '__return_true', // No login required for login challenge
				'args'                => [ 'credential_id', 'clientDataJSON', 'redirect_to' ],
			]
		);
	}
);
