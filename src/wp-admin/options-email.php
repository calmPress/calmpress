<?php
/**
 * Email delivery Settings Administration Screen.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\admin\email;

/** WordPress Administration Bootstrap */
require_once dirname( __FILE__ ) . '/admin.php';

if ( ! is_super_admin() ) {
	wp_die( esc_html__( 'Sorry, you are not allowed to manage email delivery for this site.' ) );
}

wp_enqueue_script( 'calm-options-email' );

$title       = __( 'Email Delivery Settings' );
$parent_file = 'options-general.php';

get_current_screen()->add_help_tab(
	array(
		'id'      => 'overview',
		'title'   => __( 'Overview' ),
		'content' =>
			'<p>' . esc_html__( 'This screen allows you to control how emails are delivered.' ) . '</p>',
	)
);

add_settings_section(
	'calm-email-delivery-outgoing-section',
	'',
	'',
	'email_delivery'
);

add_settings_field(
	'calm-email-delivery-gateway',
	__( 'Gateway Type' ),
	__NAMESPACE__ . '\gateway',
	'email_delivery',
	'calm-email-delivery-outgoing-section',
	[ 'label_for' => 'calm-email-delivery-gateway' ]
);

add_settings_section(
	'calm-email-delivery-smtp-section',
	'SMTP',
	__NAMESPACE__ . '\smtp_section',
	'email_delivery',
	[
		'before_section' => '<div id="smtp_settings">',
		'after_section' => '</div>',
	]
);

add_settings_field(
	'calm-email-delivery-host',
	__( 'Host' ),
	__NAMESPACE__ . '\host',
	'email_delivery',
	'calm-email-delivery-smtp-section',
	[ 'label_for' => 'calm-email-delivery-host' ]
);

add_settings_field(
	'calm-email-delivery-user',
	__( 'User name' ),
	__NAMESPACE__ . '\user',
	'email_delivery',
	'calm-email-delivery-smtp-section',
	[ 'label_for' => 'calm-email-delivery-user' ]
);

add_settings_field(
	'calm-email-delivery-password',
	__( 'Password' ),
	__NAMESPACE__ . '\password',
	'email_delivery',
	'calm-email-delivery-smtp-section',
	[ 'label_for' => 'calm-email-delivery-password' ]
);

add_settings_section(
	'calm-email-delivery-sender-section',
	__( 'Default Sender' ),
	__NAMESPACE__ . '\default_sender',
	'email_delivery',
);

add_settings_field(
	'calm-email-delivery-sender-name',
	__( 'Name' ),
	__NAMESPACE__ . '\name',
	'email_delivery',
	'calm-email-delivery-sender-section',
	[ 'label_for' => 'calm-email-delivery-sender-name' ]
);

add_settings_field(
	'calm-email-delivery-sender-email',
	__( 'Email Address' ),
	__NAMESPACE__ . '\email_address',
	'email_delivery',
	'calm-email-delivery-sender-section',
	[ 'label_for' => 'calm-email-delivery-sender-email' ]
);

add_settings_section(
	'calm-email-delivery-logging-section',
	__( 'Logging' ),
	__NAMESPACE__ . '\logging',
	'email_delivery',
);

add_settings_field(
	'calm-email-delivery-logging-verbosity',
	__( 'Verbosity' ),
	__NAMESPACE__ . '\verbosity',
	'email_delivery',
	'calm-email-delivery-logging-section',
	[ 'label_for' => 'calm-email-delivery-logging-verbosity' ]
);

/**
 * Output the HTML for the gateway selection.
 *
 * @since 1.0.0
 */
function gateway() : void {
	$opt  = get_option( 'calm_email_delivery' );
	$type = $opt['type'];
	$options = [
		'local' => esc_html__( 'Local Server' ),
		'smtp'  => esc_html__( 'SMTP Server' ),
	];
	?>
	<select id="email_delivery_type" name="calm_email_delivery[type]" aria-describedby="gateway_description">
		<?php
		foreach ( $options as $value => $label ) {
			echo '<option ' . selected( $type, $value, false ) . 
			     ' value="' . esc_attr( $value ) . '">' . $label . '</option>';
		}
		?>
	</select>
	<p class="description" id="gateway_description">
		<?php
		esc_html_e(
			'The local server option implies that calmPress will delegate
 the delivery of emails to the server on which it runs. The SMTP option
 should be used when you want to send emails via a specific SMTP server.'
		);
		?>
	</p>
	<?php
}

/**
 * Output description related to the SMTP section
 *
 * @since 1.0.0
 */
function smtp_section(): void {
	?>
	<p class="description">
		<?php
		esc_html_e(
			'The details below are required to be able to connect to the
 SMTP server. You should consult the documentaion and/or support
 of your provider to fill the following fields.'
		);
		echo '<br>';
		esc_html_e(
			'The only requirement is that the SMTP server will support TLS
 over port 587.'
		);
		?>
	</p>
	<?php
}

/**
 * Output the HTML for the input for server domain.
 *
 * @since 1.0.0
 */
function host() : void {
	$opt  = get_option( 'calm_email_delivery' );
	$host = $opt['host'];
	?>
	<input
		type="text"
		name="calm_email_delivery[host]"
		value="<?php esc_attr_e( $host );?>"
		validate="pattern"
		validate-on="input"
		validation-pattern="^\s*[\w-]+([.:][-\w]+)*\s*$"
		validation-failue-class="validation-warning"
		aria-describedby="host_description"
	>
	<p class="validate-failure-message" aria-live="polite">
		<?php esc_attr_e( 'Host value seems to not be a valid domain or IP address' ); ?>
	</p>
	<p class="description" id="host_description">
		<?php
		esc_html_e(
			'The domain or IP address of the SMTP server to use.'
		);
		?>
	</p>
	<?php
}

/**
 * Output the HTML for the input for user name.
 *
 * @since 1.0.0
 */
function user() : void {
	$opt  = get_option( 'calm_email_delivery' );
	$user = $opt['user'];
	?>
	<input
		type="text"
		name="calm_email_delivery[user]"
		value="<?php esc_attr_e( $user );?>"
		aria-describedby="user_decription"
	>
	<p class="description" id="user_decription">
		<?php
		esc_html_e(
			'The user name required to authenticate with the server.'
		);
		?>
	</p>
	<?php
}

/**
 * Output the HTML for the input for password.
 *
 * @since 1.0.0
 */
function password() : void {
	$opt      = get_option( 'calm_email_delivery' );
	$password = $opt['password'];
	?>
	<input
		type="email"
		name="calm_email_delivery[password]"
		value="<?php esc_attr_e( $password );?>"
		aria-describedby="password_description"
	>
	<p class="description" id="password_description">
		<?php
		esc_html_e(
			'The password required to authenticate with the server.'
		);
		?>
	</p>
	<?php
}

/**
 * Output description related to the default sender section
 *
 * @since 1.0.0
 */
function default_sender(): void {
	?>
	<p class="description">
		<?php
		esc_html_e(
			'Here you can override the default sender of rmsils. It will not
 have an impact at emails sent with the sender set in advance.'
		);
		echo '<br>';
		printf(
			esc_html__( 'If both Name and Email Address are set the recipient will see the mail
			     as sent from %s' ),
			'<code>Name &lt;email address&gt;</code>'
		);
		?>
	</p>
	<?php
}

/**
 * Output the HTML for the input for sender's name.
 *
 * @since 1.0.0
 */
function name() : void {
	$opt       = get_option( 'calm_email_delivery' );
	$from_name = $opt['from_name'];
	?>
	<input
		type="text"
		name="calm_email_delivery[from_name]"
		value="<?php esc_attr_e( $from_name );?>"
	>
	<?php
}

/**
 * Output the HTML for the input for sender's email address.
 *
 * @since 1.0.0
 */
function email_address() : void {
	$opt        = get_option( 'calm_email_delivery' );
	$from_email = $opt['from_email'];
	?>
	<input
		type="text"
		name="calm_email_delivery[from_email]"
		validate="pattern"
		validation-pattern="^\s*$|\s*[\w.]*@[\w-]+([.][-\w]+)*\s*$"
		validate-on="focusout"
		validation-failue-class="validation-warning"
	    value="<?php esc_attr_e( $from_email );?>"
	>
	<p class="validate-failure-message" aria-live="polite">
		<?php esc_attr_e( 'The email adress seems to be invalid' ); ?>
	</p>
	<?php
}

/**
 * Output description related to the logging section
 *
 * @since 1.0.0
 */
function logging(): void {
	?>
	<p class="description">
		<?php
		esc_html_e(
			'Failures to send are always logged, but you might want to control if and
 how successful email which were sent will be logged. If you have a good
 enough email logging at you email server you might want to acrivate logging
 only for debugging. When logging you can log the content of the emails
 as well as the recipients, but if your emails usually include HTML it might
 be hard to read the log file.'
		);
		?>
	</p>
	<?php
}

/**
 * Output the HTML for the input for logging verbosity setting.
 *
 * @since 1.0.0
 */
function verbosity() : void {
	$opt       = get_option( 'calm_email_delivery' );
	$verbosity = $opt['verbosity'];
	$options = [
		'no'         => esc_html__( 'No logging' ),
		'recipients' => esc_html__( 'Only Recipients' ),
		'full'       => esc_html__( 'Full' ),
	];
	?>
	<select name="calm_email_delivery[verbosity]">
		<?php
		foreach ( $options as $value => $label ) {
			echo '<option ' . selected( $verbosity, $value, false ) . 
			     ' value="' . esc_attr( $value ) . '">' . $label . '</option>';
		}
		?>
	</select>
	<?php
}

require ABSPATH . 'wp-admin/admin-header.php';

// Hide the SMTP section if gateway is local.
$opt  = get_option( 'calm_email_delivery' );
$type = $opt['type'];

if ( 'local' === $type ) {
	?>
	<style>#smtp_settings{display:none}</style>
	<?php
}

?>
<div class="wrap">
	<h1><?php echo esc_html( $title ); ?></h1>
	<p>
		<?php
			/* translators: %s: the link to the email delivery test page */
			printf(
				esc_html__( 'You can test settings related to SMTP conectivity,
 sender email, and general email delivery in the %s page before applying them here'),
				'<a href="' . esc_url( admin_url( 'test-email-delivery.php' ) ) .'">' .
				esc_html__( 'Test Email Delivery' ) .
				'</a>'
			);
		?>
	</p>

	<form method="post" class="calm-validate" action="options.php" novalidate="novalidate">
		<?php
		settings_fields( 'email_delivery' );
		do_settings_sections( 'email_delivery' );
		submit_button();
		?>
	</form>
</div>

<?php
require ABSPATH . 'wp-admin/admin-footer.php';
