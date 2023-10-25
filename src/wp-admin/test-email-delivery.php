<?php
/**
 * test email delivery tools Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\admin\email;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! current_user_can( 'delete_users' ) ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to test email delivery on this site.' ) );
}

$title       = __( 'Test Email Delivery' );
$parent_file = 'tools.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'Test possible settings for email delivery.' ) . '</p>',
	)
);

require ABSPATH . 'wp-admin/admin-header.php';

$opt = get_option( 'calm_email_delivery' );

$server   = isset( $_POST['server'] ) ? $_POST['server'] : $opt['host'];
$user     = isset( $_POST['user'] ) ? $_POST['user'] : $opt['user'];
$password = isset( $_POST['password'] ) ? $_POST['password'] : $opt['password'];
?>
<div class="wrap">
<?php settings_errors(); ?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<form action="" method="post">
		<input name='action' type="hidden" value='test_email_delivery'>
		<?php wp_nonce_field( 'test_email_delivery' ); ?>
		<h2><?php esc_html_e( 'SMTP Connectivity' ); ?></h2>
			<p>
				<?php esc_html_e( 'Test SMTP settings before applying them.' ); ?>
			</p>
			<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="server">
						<?php esc_html_e( 'Server' ); ?>
					</label>
				</th>
				<td>
					<input type="text" id="server" name="server" value="<?php echo esc_attr( $server );?>">
					<p class="description">
						<?php esc_html_e( 'The domain or IP of the server. The server should support TLS on port 587.' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="user">
						<?php esc_html_e( 'User Name' ); ?>
					</label>
				</th>
				<td>
					<input type="text" id="user" name="user" value="<?php echo esc_attr( $user );?>">
					<p class="description">
						<?php esc_html_e( 'The user name to use when authenticating with the server' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="password">
						<?php esc_html_e( 'Password' ); ?>
					</label>
				</th>
				<td>
					<input type="text" id="password" name="password" value="<?php echo esc_attr( $password );?>">
					<p class="description">
						<?php esc_html_e( 'The password to use when authenticating with the server' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p class="submit">
		<?php
		submit_button( __( 'Test connectivity' ), 'primary', 'test', false );
		submit_button( __( 'Save As Email Delivery SMTP Settings' ), 'secondary', 'save_smtp', false );
		?>
		</p>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
