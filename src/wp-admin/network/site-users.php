<?php
/**
 * Redirects the legacy Network Admin site-users screen to the site's Users screen.
 *
 * @package calmPress
 * @subpackage Multisite
 * @since calmPress 1.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_sites' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to edit this site.' ), 403 );
}

$site_id = isset( $_REQUEST['id'] ) ? (int) $_REQUEST['id'] : 0;

if ( ! $site_id ) {
	wp_die( esc_html__( 'Invalid site ID.' ) );
}

$site = get_site( $site_id );

if ( ! $site ) {
	wp_die( esc_html__( 'The requested site does not exist.' ) );
}

if ( ! can_edit_network( $site->site_id ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ), 403 );
}

wp_redirect( get_admin_url( $site_id, 'users.php' ) );
exit;
