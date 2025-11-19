<?php
/**
 * Dashboard Administration Screen
 *
 * @package WordPress
 * @subpackage Administration
 */

/** Load WordPress Bootstrap */
require_once __DIR__ . '/admin.php';

/** Load WordPress dashboard API */
require_once ABSPATH . 'wp-admin/includes/dashboard.php';

wp_dashboard_setup();

wp_enqueue_script( 'dashboard' );

if ( current_user_can( 'install_plugins' ) ) {
	wp_enqueue_script( 'plugin-install' );
	wp_enqueue_script( 'updates' );
}
if ( current_user_can( 'upload_files' ) ) {
	wp_enqueue_script( 'media-upload' );
}
add_thickbox();

if ( wp_is_mobile() ) {
	wp_enqueue_script( 'jquery-touch-punch' );
}

// Used in the HTML title tag.
$title       = __( 'Dashboard' );
$parent_file = 'index.php';

$screen = get_current_screen();

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<div id="dashboard-widgets-wrap">
	<?php wp_dashboard(); ?>
	</div><!-- dashboard-widgets-wrap -->

</div><!-- wrap -->

<?php

require_once ABSPATH . 'wp-admin/admin-footer.php';
