<?php
/**
 * Registration of admin_post handlers used by calmPress code.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\admin_post;

add_action( 'admin_init', __NAMESPACE__ . '\add_handlers' );

/**
 * Add various handlers to the admin_post handling.
 *
 * @since 1.0.0
 */
function add_handlers(): void {

	// Maintenance page form submittion.
	add_action( 'admin_post_maintenance_mode_content', '\calmpress\calmpress\Maintenance_Mode::handle_content_change_post' );
	add_action( 'admin_post_maintenance_mode_status', '\calmpress\calmpress\Maintenance_Mode::handle_status_change_post' );

	// Switch User form submittion.
	add_action( 'admin_post_switch_user', '\calmpress\user\Switch_User::handle_user_switch' );

	// Opcache restart form submittion.
	add_action( 'admin_post_opcache_reset', '\calmpress\opcache\Opcache::handle_opcache_reset' );

	// APCu restart form submittion.
	add_action( 'admin_post_apcu_reset', '\calmpress\apcu\APCu::handle_apcu_reset' );

	// Object cache restart form submittion.
	add_action( 'admin_post_object_cache_reset', '\calmpress\object_cache\Utils::handle_object_cache_reset' );

	// Backup delete "GET" (link) action.
	add_action( 'admin_post_delete_backup', '\calmpress\backup\Utils::handle_delete_backup' );

	// Backup delete "GET" (link) action.
	add_action( 'admin_post_bulk_backup', '\calmpress\backup\Utils::handle_bulk_backup' );
}