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
	.save {margin:0 10px !important;}
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
if ( isset( $_POST['server'] ) ) {
	$server   = trim( wp_unslash( $_POST['server'] ) );
	$user     = trim( wp_unslash( $_POST['user'] ) );
	$password = trim( wp_unslash( $_POST['password'] ) );
}

$sendto     = '';
$from_name  = '';
$from_email = '';
if ( isset( $_POST['sendto'] ) ) {
	$sendto     = trim( wp_unslash( $_POST['sendto'] ) );
	$from_name  = trim( wp_unslash( $_POST['from_name'] ) );
	$from_email = trim( wp_unslash( $_POST['from_email'] ) );
}

$disabled_save        = 'disabled="disabled"';
$disabled_save_sender = 'disabled="disabled"';

/* translators: %s: the title of the site */
$test_subject = sprintf(
	__( 'Mail delivery test from %s' ),
	get_bloginfo( 'name' )
);

if ( filter_var( $server, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
	$message_type = 'success';
} else {
	if ( filter_var( $server, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
		$ip = gethostbyname( $server );
		if ( $server === $ip ) {
			// IPv4 resolving didn't work, try to get an AAAA DNS record
			// to check if its an IPv6.
			// Errors are supressed to avoid login warnings that are generated
			// when DNS server are not available or information not accessable.
			$dns = @dns_get_record( $server, DNS_AAAA );
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
				} else {
					$disabled_save = '';
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
} elseif ( isset( $_POST['test_email'] ) ) {
	// To keep the flow of wp_mail "authentic" overwrite the
	// email settings values.
	add_filter(
		'option_calm_email_delivery',
		static function ( $value ) use ( $from_name, $from_email ) {
			$value['from_name']  = $from_name;
			$value['from_email'] = $from_email;
			return $value;
		}
	);

	// Catch the error if mail send failed.
	$error = null;
	add_filter(
		'wp_mail_failed',
		static function ( $value ) use ( &$error ) {
			$error = $value;
			return $value;
		}
	);

	$r = wp_mail(
		$sendto,
		$test_subject,
		__( 'A mail sent as a test to verify that email are properly delivered.' )
	);

	if ( $r ) {
		$message              = __('test email sent' );
		$message_type         = 'success';
		$disabled_save_sender = '';
	} else {
		/* translators: %s: the human description of the reason */
		$message = sprintf(
			__('Failed sending test email. Reason: %s'),
			$error->get_error_message()
		);
	}
}
?>
<div class="wrap">
<?php
	if ( 
		isset( $_POST['test'] ) ||
		isset( $_POST[ 'save_smtp' ] ) ||
		isset( $_POST['test_email'] ) ||
		isset( $_POST['save_sender'] )
		 ) {
		echo "<div class='notice notice-$message_type settings-error'>";
		echo "<p><strong>$message</strong></p>";
		echo '</div>';
	}
?>
	<h1><?php echo esc_html( $title ); ?></h1>
	<p>
		<?php
			/* translators: %s: the link to the email delivery setting page */
			printf(
				esc_html__( 'This page can be used to test values for SMTP connectivity
 before setting them at %s page and testing the functionality of sending emails.'),
				'<a href="' . esc_url( admin_url( 'options-email.php' ) ) .'">' .
				esc_html__( 'Email Delivery Settings' ) .
				'</a>'
			);
		?>
	</p>
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
		submit_button( __( 'Save As Email Delivery SMTP Settings' ), 'secondary save', 'save_smtp', false, $disabled_save );
		?>
		</p>
	</form>

	<h2><?php esc_html_e( 'Sender and full delivery' ); ?></h2>
	<p>
		<?php
		echo esc_html(
			/* translators: %s: the subject of the test email */
			sprintf(
				__('Test the sender setting and wheather email is sent. The email subject will be "%s".' ),
				$test_subject
			)
		);
		?>
	</p>
	<div>
		<button id="populate_sender" class="button" type="button">
			<?php esc_html_e( 'Use current settings' );?>
		</button>
	</div>
	<form action="" method="post">
		<?php wp_nonce_field( 'test_email_delivery' ); ?>
			<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="sendto">
						<?php esc_html_e( 'Send to' ); ?>
					</label>
				</th>
				<td>
					<input type="text" id="sendto" name="sendto" value="<?php echo esc_attr( $sendto );?>">
					<p class="description">
						<?php esc_html_e( 'The email address to which to send the test mail.' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="from_name">
						<?php esc_html_e( 'Sender Name' ); ?>
					</label>
				</th>
				<td>
					<input type="text" id="from_name" name="from_name" value="<?php echo esc_attr( $from_name );?>">
					<p class="description">
						<?php esc_html_e( 'The name of the sender from which the email will seem to originate.' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="from_email">
						<?php esc_html_e( 'Sender Email Address' ); ?>
					</label>
				</th>
				<td>
					<input type="text" id="from_email" name="from_email" value="<?php echo esc_attr( $from_email );?>">
					<p class="description">
						<?php esc_html_e( 'The email address from which the email will seem to originate.' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<p class="submit">
		<?php
		submit_button( __( 'Send Email' ), 'primary', 'test_email', false );
		submit_button( __( 'Save As Email Delivery Sender Settings' ), 'secondary save', 'save_sender', false, $disabled_save_sender );
		?>
		</p>
	</form>
</div>

<?php
add_action(
	'admin_footer',
	static function () {
		$opt        = get_option( 'calm_email_delivery' );
		$host       = $opt['host'];
		$user       = $opt['user'];
		$password   = $opt['password']; 
		$from_name  = $opt['from_name']; 
		$from_email = $opt['from_email']; 
		// The settings values will be populated into input fields when the user will 
		// want it to happen. This is a very simplified way to fetch the settings
		// into the input fields, a proper one would have required AJAX to fetch fresh
		// value as they might have changed via the settings page while the testing
		// page is loaded, but it is assumed that this way is good enough as people
		// less likely to fiddle with setting while they testing them.
	?>
<script>
	b = document.querySelector( '#populate_smtp' );
	b.addEventListener( 'click', () => {
		e       = document.querySelector( '#server' );
		e.value = "<?php echo esc_js( $host );?>";
		e       = document.querySelector( '#user' );
		e.value = "<?php echo esc_js( $user );?>";
		e       = document.querySelector( '#password' );
		e.value = "<?php echo esc_js( $password );?>";
	} );
	b = document.querySelector( '#populate_sender' );
	b.addEventListener( 'click', () => {
		e       = document.querySelector( '#from_name' );
		e.value = "<?php echo esc_js( $from_name );?>";
		e       = document.querySelector( '#from_email' );
		e.value = "<?php echo esc_js( $from_email );?>";
	} );

	<?php
	// Save should be disabled when any of the inputs changed as at that point
	// values have not been verified any more.
	?>
	f = document.querySelectorAll( 'form' );
	f.forEach( ( form ) => {
		form.addEventListener( 'input', () => {
			b          = form.querySelector( '.save' );
			b.disabled = 'disabled';
		});
	});
</script>
		<?php
	}
);

require ABSPATH . 'wp-admin/admin-footer.php';
