<?php
/**
 * Safe mode tools Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\user;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! current_user_can( 'delete_users' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to swith user on this site.' ) );
}

$title       = __( 'Switch User' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'Switch the effective user under witch you authenticate, useful for debug and support other users.' ) . '</p>',
	)
);

require ABSPATH . 'wp-admin/admin-header.php';
?>
<div class="wrap">
<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>

	<p>
		<?php esc_html_e( 'Switching to a user will cause that for the rest of the session, until the session expires or you logout, the site will behave us if you are the user you switch to.' ); ?>
	</p>
	<p>
		<?php esc_html_e( 'You will need to log out and log in again to get back to your normal user. You can also use a different browser or a browser in a "private" mode to login to your normal user in the same time you are being switched on this browser window.' ); ?>
	</p>
	<form action="admin-post.php" method="post">
		<input name='action' type="hidden" value='switch_user'>
		<?php
		$user_id = get_current_user_id();
		$dropdown = wp_dropdown_users(
			[
				'show'    => 'display_name_with_login',
				'exclude' => $user_id,
				'echo'    => false,
			]
		);
		wp_nonce_field( 'switch_user' );
		if ( $dropdown ) {
		?>
		<table class="form-table">
			<tr>
				<th>
					<label><?php esc_html_e( 'Switch to:' ); ?></label>
				</th>
				<td>
					<?php
					echo $dropdown;
					?>
				</td>
			</tr>
		</table>
		<?php
		submit_button( __( 'Switch to the selected user' ), 'primary', 'enter' );
		} else {
			echo '<p><b>' . esc_html__( 'There are no users to switch to' ) . '</b></p>';
		}
		?>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
