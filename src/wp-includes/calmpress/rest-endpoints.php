<?php
/**
 * Registration of rest endpoints used by calmPress code.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\rest_endpoints;

// webauthn rest api routes.
require ABSPATH . WPINC . '/calmpress/webauthn/rest-endpoints.php';

add_action( 'rest_api_init', __NAMESPACE__ . '\create_routes', 2 );

/**
 * Handle the send_otp (one time password) request, send it to the email
 * specified in the request.
 * 
 * @sine 1.0.0
 * 
 * @param \WP_REST_Request $request The request the broser sent.
 * 
 * @return \WP_REST_Response A generic response meant to not be a vector
 *                           for exposing registered emails.
 */
function handle_send_otp( \WP_REST_Request $request ): \WP_REST_Response {
	$email = $request->get_param( 'email' );
	$user = get_user_by( 'email', $email );
	if ( $user ) {
		$user->generate_and_email_one_time_password();
	}

	// Content do change with success or fail and use a 200 code to prevent discloser
	// of email addresses on the site.
	return new \WP_REST_Response( [
		'message' => __( 'Email with a temporary password was sent to you.' )
	]);
}

/**
 * Add various routes to the rest API and associate them with the code handling them.
 *
 * @since 1.0.0
 */
function create_routes() {

	/*
	 * Route to create a new backup by a POST request. The expected parameters are the nonce,
	 * description, storage id identifying the storage on which to store the backup and engines
	 * to be used in backup creation.
	 */
	register_rest_route(
		'calmpress',
		'create_backup',
		[
			[
				'methods'             => 'POST',
				'callback'            => '\calmpress\backup\Utils::handle_backup_request',
				'permission_callback' => function () {

					return current_user_can( 'backup' );
				},
				'args'                => [
					'description' => [
						'required' => true,
					],
					'storage' => [
						'required' => true,
					],
					'engines' => [
						'required' => true,
					],
				],
			],
		]
	);

	/*
	 * Route to restore a backup by a POST request. The expected parameters are the nonce and
	 * the backup id.
	 */
	register_rest_route(
		'calmpress',
		'restore_backup',
		[
			[
				'methods'             => 'POST',
				'callback'            => '\calmpress\backup\Utils::handle_restore_backup_request',
				'permission_callback' => function () {

					return current_user_can( 'backup' );
				},
				'args'                => [
					'backup_id' => [
						'required' => true,
					],
				],
			],
		]
	);

	/*
	 * Send one-time password if user exists for the email.
	 */
	register_rest_route(
		'calmpress',
		'send_otp',
		[
			[
				'methods'             => 'POST',
				'callback'            => __NAMESPACE__ . '\handle_send_otp',
				'permission_callback' => '__return_true',
				'args'                => [
					'email' => [
						'required' => true,
					],
				],
			],
		]
	);
}
