<?php
/**
 * Network identity settings administration screen.
 *
 * @package calmPress
 * @since 1.0.0
 */

/** Load WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_network_options' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to access this page.' ), 403 );
}

// Used in the HTML title tag.
$title       = __( 'Identity Settings' );
$parent_file = 'settings.php';

if ( $_POST ) {
	/** This action is documented in wp-admin/network/edit.php */
	do_action( 'wpmuadminedit' );

	check_admin_referer( 'identitysettings' );

	if ( isset( $_POST['site_name'] ) ) {
		update_site_option( 'site_name', wp_unslash( $_POST['site_name'] ) );
	}

	wp_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'identity.php' ) ) );
	exit;
}

require_once ABSPATH . 'wp-admin/admin-header.php';

if ( isset( $_GET['updated'] ) ) {
	wp_admin_notice(
		esc_html__( 'Settings saved.' ),
		array(
			'type'        => 'success',
			'dismissible' => true,
			'id'          => 'message',
		)
	);
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Identity Settings' ); ?></h1>
	<form method="post" action="identity.php" novalidate="novalidate">
		<?php wp_nonce_field( 'identitysettings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="site_name"><?php esc_html_e( 'Network title' ); ?></label></th>
				<td>
					<input name="site_name" type="text" id="site_name" class="regular-text" value="<?php echo esc_attr( get_network()->site_name ); ?>" />
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
