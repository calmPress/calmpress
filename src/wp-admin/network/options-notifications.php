<?php
/**
 * Network Notifications administration screen.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

/** WordPress Administration Bootstrap */
require_once __DIR__ . '/admin.php';

if ( ! current_user_can( 'manage_network_options' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage notifications for this network.' ), 403 );
}

$title       = __( 'Notifications' );
$parent_file = 'settings.php';

add_settings_section( 'network-notifications-section', '', '', 'notifications' );
add_settings_field(
	'network-notification-user',
	esc_html__( 'Super admin receiving notifications' ),
	'notification_user_field',
	'notifications',
	'network-notifications-section',
	[ 'label_for' => 'admin_user_id' ]
);

/**
 * Outputs the network notification user setting field.
 *
 * @since 1.0.0
 */
function notification_user_field(): void {
	$selected_user_id = (int) get_site_option( 'admin_user_id' );
	$network          = get_network();
	?>
	<select name="admin_user_id" id="admin_user_id" aria-describedby="admin-user-id-desc">
		<?php
		foreach ( $network->administrators() as $super_admin ) {
			printf(
				'<option value="%1$d"%2$s>%3$s</option>',
				$super_admin->ID,
				selected( $selected_user_id, $super_admin->ID, false ),
				esc_html( sprintf( '%1$s (%2$s)', $super_admin->display_name, $super_admin->user_email ) )
			);
		}
		?>
	</select>
	<p class="description" id="admin-user-id-desc">
		<?php esc_html_e( 'Network-related notifications are sent to this super admin.' ); ?>
	</p>
	<?php
}

require_once ABSPATH . 'wp-admin/admin-header.php';
?>

<div class="wrap">
	<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<form method="post" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'notifications' );
		do_settings_sections( 'notifications' );
		submit_button();
		?>
	</form>
</div>

<?php require_once ABSPATH . 'wp-admin/admin-footer.php'; ?>
