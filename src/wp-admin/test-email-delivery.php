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

if ( ! empty( $_POST ) &&
	! ( isset( $_POST['_wpnonce'] ) &&
	wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'test_email_delivery' ) ) ) {
		wp_die( esc_html__( 'Missing credentials to test email delivery. Please refresh the page and try again' ) );
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

add_action(
	'admin_head',
	static function () {
		?>
<style>
	#save_smtp {margin:0 10px;}
</style>
		<?php
	}
);

require ABSPATH . 'wp-admin/admin-header.php';
require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

$message      = __( 'Basic communication was successful' );
$message_type = 'error';

$server   = '';
$user     = '';
$password = '';
if ( isset( $_POST['server'])) {
	$server   = trim( wp_unslash( $_POST['server'] ) );
	$user     = trim( wp_unslash( $_POST['user'] ) );
	$password = trim( wp_unslash( $_POST['password'] ) );
}

if ( filter_var( $server, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
	$message_type = 'success';
} else {
	if ( filter_var( $server, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
		$ip = gethostbyname( $server );
		if ( $server === $ip ) {
			// IPv4 resolving didn't work, try to get an AAAA DNS record
			// to check if its an IPv6
			$dns = dns_get_record( $server, DNS_AAAA );
			if ( empty( $dns ) ) {
				$message = __( 'Invalid host' );
			} else {
				$message_type = 'success';
			}
		} else {
			$message_type = 'success';
		}
	} else {
		$message = __( 'Invalid host' );
	}
}

if ( isset( $_POST['test'] ) ) {
	$smtp = new \PHPMailer\PHPMailer\SMTP();
	// If host is an ipv6 address need to add brackets around it
	if ( ! empty( $server ) && strpos( ':' , $server ) !== FALSE ) {
		$host = 'tls://[' . $server . ']'; 
	} else {
		$host = 'tls://' . $server; 
	}
	
	// Check connectivity
	// step 1. check if it is the host is accessable and the port is open.
	$connected = $smtp->connect( $server, 587, 10, [] );
	if ( ! $connected ) {
		/* translators: %s: the URL to which the actual connection was attempet */
		$message = sprintf(
			__( 'Failed to connect to server (connection attempt to %s)' ),
			'<code>' . $host . ':587</code>'
		);
		$message_type = 'error';
	} else {
		// Step 2. Send HELO/EHLO to make sure it is an SMTP server there
		$sitename = wp_parse_url( network_home_url(), PHP_URL_HOST );
		if ( 'www.' === substr( $sitename, 0, 4 ) ) {
			$sitename = substr( $sitename, 4 );
		}
		if ( ! $smtp->hello( $sitename ) ) {
			$error_code = $smtp->getError()['error'];
			/* translators:1: The full URL of the sever, 2: The returned error code. */
			$message = sprintf(
				__( 'Server at %1$s reported error code %2$s when tryoing to establish basic communication' ),
				'<code>' . $host . ':587</code>',
				'<code>' . $error_code . '</code>',
			);
			$message_type = 'error';
		} else {
			if ( ! $smtp->getServerExt( 'STARTTLS' ) ) {
				$message = sprintf(
					__( 'Server at %1$s do not support STARTLS' ),
					'<code>' . $host . ':587</code>',
				);
				$message_type = 'error';
			} else {
				$smtp->startTLS();
				// send ELHO Again to get the secure connectivity features
				$smtp->hello( $sitename );
				if ( ! $smtp->authenticate( $user, $password ) ) {
					$message = __( 'Authentication failed' );	
					$message_type = 'error';
				}
			}
		}
	}
} elseif ( isset( $_POST[ 'save_smtp' ] ) ) {
	$opt = get_option( 'calm_email_delivery' );
	$opt['host']     = $server;
	$opt['user']     = $user;
	$opt['password'] = $password;
	update_option( 'calm_email_delivery', $opt );
	$message = __( 'SMTP Settings Saved' );
	$message_type = 'success';
}
?>
<div class="wrap">
<?php
	$disabled_save = 'disabled="disabled"';
	if ( isset( $_POST['test'] ) || isset( $_POST[ 'save_smtp' ] ) ) {
		echo "<div class='notice notice-$message_type settings-error'>";
		echo "<p><strong>$message</strong></p>";
		echo '</div>';

		if ( $message_type === 'success' ) {
			$disabled_save = '';
		}
	}
?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<h2><?php esc_html_e( 'SMTP Connectivity' ); ?></h2>
	<p><?php esc_html_e( 'Test SMTP settings before applying them.' ); ?></p>
	<div>
		<button id="populate_smtp" class="button" type="button">
			<?php esc_html_e( 'Use current settings' );?>
		</button>
	</div>
	<form action="" method="post">
		<?php wp_nonce_field( 'test_email_delivery' ); ?>
			<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="server">
						<?php esc_html_e( 'Host' ); ?>
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
		submit_button( __( 'Save As Email Delivery SMTP Settings' ), 'secondary', 'save_smtp', false, $disabled_save );
		?>
		</p>
	</form>
</div>

<?php
add_action(
	'admin_footer',
	static function () {
		$opt      = get_option( 'calm_email_delivery' );
		$host     = $opt['host'];
		$user     = $opt['user'];
		$password = $opt['password']; 
		?>
<script>
	e = document.querySelector( '#populate_smtp' );
	e.addEventListener( 'click', () => {
		b       = document.querySelector( '#server' );
		b.value = "<?php echo esc_js( $host );?>";
		e       = document.querySelector( '#user' );
		e.value = "<?php echo esc_js( $user );?>";
		e       = document.querySelector( '#password' );
		e.value = "<?php echo esc_js( $password );?>";
	} );
</script>
		<?php
	}
);

require ABSPATH . 'wp-admin/admin-footer.php';
