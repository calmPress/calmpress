<?php
/**
 * Build User Administration Menu.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

// Don't load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

$menu[900] = array( __( 'My Profile' ), 'exist', 'user-edit.php', '', 'menu-top menu-top-first menu-icon-users', 'menu-users', 'dashicons-admin-users' );

$menu[999] = array( '', 'exist', 'separator-last', '', 'wp-menu-separator' );

$compat                            = array();
$submenu                           = array();
$submenu['user-edit.php'][5] = array( __( 'Account' ), 'exist', 'user-edit.php' );
if ( is_multisite() ) {
	// The Sites screen is useful when there are other sites to visit or invitations to answer.
	$user = wp_get_current_user();
	if ( count( $user->sites() ) > 1 || [] !== $user->sites_pending_activation( get_network() ) ) {
		$submenu['user-edit.php'][20] = array( __( 'Sites' ), 'exist', 'sites.php' );
	}
}
$submenu['user-edit.php'][30] = array( __( 'Device Login' ), 'exist', 'webauthn.php' );
if ( wp_is_application_passwords_available_for_user( get_current_user_id() ) ) {
	$submenu['user-edit.php'][50] = array( __( 'Application Passwords' ), 'exist', 'application-passwords.php' );
}

require_once ABSPATH . 'wp-admin/includes/menu.php';
