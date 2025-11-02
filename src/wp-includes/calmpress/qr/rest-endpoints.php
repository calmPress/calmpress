<?php
/**
 * Registration of rest endpoints used for QR generation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\QR\rest_endpoints;

use calmpress\QR;

/**
 * Handle the request to generate a QR code with url to login for the current user.
 * 
 * @since 1.0.0
 * 
 * @return \WP_REST_Response A 200 with the image data url and success message.
 */
function generate(): \WP_REST_Response {
	$user = wp_get_current_user();

	$nonce = $user->generate_QR_one_time_password();
	$url = wp_login_url();
	$url = add_query_arg( 'action', 'login', $url );
	$url = add_query_arg( 'qremail', $user->user_email, $url );
	$url = add_query_arg( 'nonce', $nonce, $url );
	return new \WP_REST_Response(
		[
			'message' => __( 'QR image generate.' ),
			'image'   => QR\Utils::image_data_url_for_url( $url ),
		],
		200
	);
}

add_action(
	'rest_api_init',
	/**
	 * Register the various end points to handle QR rest requests.
	 * 
	 * @since 1.0.0
	 */
	function () {
		\calmpress\utils\add_current_user_post_endpoint(
			'calmpress',
			'qr/generate',
			__NAMESPACE__ . '\\generate',
			[]
		);
	}
);
