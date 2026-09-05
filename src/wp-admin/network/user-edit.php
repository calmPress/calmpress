<?php
/**
 * Edit user network administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.1.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
$user = get_userdata( $user_id );

if ( $user && $user->has_network_invite( get_network() ) ) {
	require ABSPATH . 'wp-admin/network/user-edit-invitation.php';
	return;
}

require ABSPATH . 'wp-admin/user-edit.php';
