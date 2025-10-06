<?php
/**
 * Build User Administration Menu.
 *
 * @package WordPress
 * @subpackage Administration
 * @since 3.1.0
 */

$menu[2] = array( __( 'Dashboard' ), 'exist', 'index.php', '', 'menu-top menu-top-first menu-icon-dashboard', 'menu-dashboard', 'dashicons-dashboard' );

$menu[4] = array( '', 'exist', 'separator1', '', 'wp-menu-separator' );

$menu[900] = array( __( 'My Profile' ), 'exist', 'user-edit.php', '', 'menu-top menu-icon-users', 'menu-users', 'dashicons-admin-users' );

$menu[999] = array( '', 'exist', 'separator-last', '', 'wp-menu-separator' );

$compat                            = array();
$submenu                           = array();

require_once ABSPATH . 'wp-admin/includes/menu.php';
