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

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __('Overview'),
		'content' => '<p>' . __('This screen lists importers installed at the site. Importers are a functionality provided by plugins, and if you need a specific type of importer you should look for a plugin that implements it.') . '</p>'
	)
);

require_once ABSPATH . 'wp-admin/admin-header.php';
$parent_file = 'tools.php';
?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>
