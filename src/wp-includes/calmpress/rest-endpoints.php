<?php
/**
 * Registration of rest endpoints used by calmPress code.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\rest_endpoints;

add_action( 'rest_api_init', __NAMESPACE__ . '\create_routes', 2 );

/**
 * Add various routes to the rest API and associate them with the code handling them.
 *
 * @since 1.0.0
 */
function create_routes() {

	register_rest_route(
		'calmpress',
		'create_backup',
		[
			[
				'methods'             => 'POST',
				'callback'            => '\calmpress\backup\Managed_Backup::handle_backup_request',
				'permission_callback' => function () {
					return current_user_can( 'backup' );
				},
				'args'                => [
					'nonce'       => [
						'required'          => true,
						'validate_callback' => function( $param, $request, $key ) {
							return wp_verify_nonce( $request['nonce'], 'create_backup' );
						}
					],
					'description' => [
						'required' => true,
					],
				],
			],
		]
	);
}
