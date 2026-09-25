<?php
/**
 * Site departure confirmation screen.
 *
 * @package CalmPress
 * @subpackage Administration
 * @since calmPress 1.0.0
 */

/** Load WordPress Administration Bootstrap. */
require_once __DIR__ . '/admin.php';

$user            = wp_get_current_user();
$site            = calmpress\site\Site::current();
$user_site_ids   = $user->site_ids();
$has_other_sites = 1 < count( $user_site_ids );

if ( ! in_array( (int) $site->blog_id, $user_site_ids, true ) ) {
	wp_die( 'You are not a member of this site.', '', [ 'response' => 403 ] );
}

$cannot_leave_reason = '';
if ( $user->is_system_notification_recipient( $site ) ) {
	$cannot_leave_reason = esc_html__( 'You cannot leave this site while you are configured to receive its system notifications.' );
}

$title        = __( 'Leave This Site' );
$parent_file  = 'my-profile';
$submenu_file = 'leave-site.php';

wp_enqueue_script( 'leave-site' );

require_once ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>

	<?php
	if (
		isset( $_GET['leave-site-error'] )
		&& is_string( $_GET['leave-site-error'] )
	) {
		$leave_site_error = wp_unslash( $_GET['leave-site-error'] );
		if ( 'confirmation-required' === $leave_site_error ) {
			wp_admin_notice( esc_html__( 'Confirm that you understand the consequences before leaving the site.' ), [ 'type' => 'error', 'dismissible' => true ] );
		}
	}
	?>

	<p><?php esc_html_e( 'You are about to leave this site:' ); ?> <strong><?php echo esc_html( $site->name() ); ?></strong></p>
	<p><?php esc_html_e( 'Leaving removes your user privileges on this site and logs you out. Your existing content will remain and will be attributed to an anonymized user.' ); ?></p>

	<?php if ( '' !== $cannot_leave_reason ) { ?>
		<?php wp_admin_notice( $cannot_leave_reason, [ 'type' => 'error' ] ); ?>
	<?php } else { ?>
		<?php if ( ! is_multisite() ) { ?>
			<p><?php esc_html_e( 'Leaving will remove your account information and prevent you from logging in to this site again.' ); ?></p>
		<?php } elseif ( $site->has_mapped_domain() ) { ?>
			<?php if ( $has_other_sites ) { ?>
				<p><?php esc_html_e( 'You will not be able to log in to this site again. Your memberships of other sites will not be affected.' ); ?></p>
			<?php } else { ?>
				<p><?php esc_html_e( 'You will not be able to log in to this site again.' ); ?></p>
			<?php } ?>
		<?php } elseif ( $has_other_sites ) { ?>
			<p><?php esc_html_e( 'Your memberships of other sites will not be affected.' ); ?></p>
		<?php } ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="leave_site">
			<?php wp_nonce_field( 'leave-site' ); ?>
			<p>
				<input type="checkbox" name="confirm_leave_site" id="confirm_leave_site" value="1" required>
				<label for="confirm_leave_site"><?php esc_html_e( 'I understand the consequences.' ); ?></label>
			</p>
			<?php submit_button( __( 'Leave This Site' ), 'delete', 'submit', true, [ 'disabled' => 'disabled' ] ); ?>
		</form>
	<?php } ?>
</div>
<?php
require_once ABSPATH . 'wp-admin/admin-footer.php';
