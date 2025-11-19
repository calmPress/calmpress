<?php
/**
 * Multisite administration panel.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

/** Load WordPress dashboard API */
require_once ABSPATH . 'wp-admin/includes/dashboard.php';

if ( ! current_user_can( 'manage_network' ) ) {
	wp_die( __( 'Sorry, you are not allowed to access this page.' ), 403 );
}

// Used in the HTML title tag.
$title       = __( 'Dashboard' );
$parent_file = 'index.php';

wp_dashboard_setup();

wp_enqueue_script( 'dashboard' );
wp_enqueue_script( 'plugin-install' );
add_thickbox();

require_once ABSPATH . 'wp-admin/admin-header.php';

?>

<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>

<div id="dashboard-widgets-wrap">

<?php wp_dashboard(); ?>

<div class="clear"></div>
</div><!-- dashboard-widgets-wrap -->

</div><!-- wrap -->

<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
