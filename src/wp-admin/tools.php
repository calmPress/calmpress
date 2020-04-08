<?php
/**
 * Tools Administration Screen.
 *
 * @package WordPress
 * @subpackage Administration
 */

if ( isset( $_GET['page'] ) && ! empty( $_POST ) ) {
	// Ensure POST-ing to `tools.php?page=export_personal_data` and `tools.php?page=remove_personal_data`
	// continues to work after creating the new files for exporting and erasing of personal data.
	if ( $_GET['page'] === 'export_personal_data' ) {
		require_once( ABSPATH . 'wp-admin/export-personal-data.php' );
		return;
	} elseif ( $_GET['page'] === 'remove_personal_data' ) {
		require_once( ABSPATH . 'wp-admin/erase-personal-data.php' );
		return;
	}
}

// The privacy policy guide used to be outputted from here. Since WP 5.3 it is in wp-admin/privacy-policy-guide.php.
if ( isset( $_GET['wp-privacy-policy-guide'] ) ) {
	require_once dirname( __DIR__ ) . '/wp-load.php';
	wp_redirect( admin_url( 'privacy-policy-guide.php' ), 301 );
	exit;
} elseif ( isset( $_GET['page'] ) ) {
	// These were also moved to files in WP 5.3.
	if ( $_GET['page'] === 'export_personal_data' ) {
		require_once dirname( __DIR__ ) . '/wp-load.php';
		wp_redirect( admin_url( 'export-personal-data.php' ), 301 );
		exit;
	} elseif ( $_GET['page'] === 'remove_personal_data' ) {
		require_once dirname( __DIR__ ) . '/wp-load.php';
		wp_redirect( admin_url( 'erase-personal-data.php' ), 301 );
		exit;
	}
}

/** WordPress Administration Bootstrap */
require_once( dirname( __FILE__ ) . '/admin.php' );

$title = __( 'Tools' );

}

require_once( ABSPATH . 'wp-admin/admin-header.php' );

?>
<div class="wrap">
<h1><?php echo esc_html( $title ); ?></h1>
<?php

/**
 * Fires at the end of the Tools Administration screen.
 *
 * @since 2.8.0
 */
do_action( 'tool_box' );

?>
</div>
<?php

include( ABSPATH . 'wp-admin/admin-footer.php' );
