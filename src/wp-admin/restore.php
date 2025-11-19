<?php
/**
 * Restore Administration Screen
 *
 * @package calmPress
 * @since 1.0.0
 */

/** Load WordPress Admin Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'backup' ) ) {
	wp_die( __( 'Sorry, you are not allowed to restore this site.' ) );
}

$title = __( 'Import' );

require_once ABSPATH . 'wp-admin/admin-header.php';
$parent_file = 'tools.php';
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>
