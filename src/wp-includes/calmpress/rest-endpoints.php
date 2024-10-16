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
}
