<?php
/**
 * WordPress Installer
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * We are installing WordPress.
 *
 * @since 1.5.1
 * @var bool
 */
define( 'WP_INSTALLING', true );

/** Load WordPress Bootstrap */
require_once dirname( __DIR__ ) . '/wp-load.php';

/** Load WordPress Administration Upgrade API */
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/** Load WordPress Translation Install API */
require_once ABSPATH . 'wp-admin/includes/translation-install.php';

/** Load wpdb */
require_once ABSPATH . WPINC . '/wp-db.php';

nocache_headers();

$step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 1;

/**
 * Display installation header.
 *
 * @since 2.5.0
 *
 * @param string $body_classes
 */
function display_header( $body_classes = '' ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	if ( is_rtl() ) {
		$body_classes .= 'rtl';
	}
	if ( $body_classes ) {
		$body_classes = ' ' . $body_classes;
	}
	?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta name="viewport" content="width=device-width" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php _e( 'calmPress &rsaquo; Installation' ); ?></title>
	<?php wp_admin_css( 'install', true ); ?>
</head>
<body class="wp-core-ui<?php echo $body_classes; ?>">
<p id="logo"><a href="<?php echo esc_url( __( 'https://calmpress.org/' ) ); ?>"><?php _e( 'calmPress' ); ?></a></p>

	<?php
} // End display_header().

/**
 * Display installer setup form.
 *
 * @since 2.8.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string|null $error
 */
function display_setup_form( $error = null ) {
	global $wpdb;

	$user_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->users ) ) ) !== null );

	// Ensure that Blogs appear in search engines by default, unless this is a local install.
	$blog_public = in_array( $_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true ) ? 0 : 1;
	if ( isset( $_POST['weblog_title'] ) ) {
		$blog_public = isset( $_POST['blog_public'] ) ? 0 : 1;
	}

	$weblog_title = isset( $_POST['weblog_title'] ) ? trim( wp_unslash( $_POST['weblog_title'] ) ) : '';
	$admin_email  = isset( $_POST['admin_email']  ) ? trim( wp_unslash( $_POST['admin_email'] ) ) : '';

	if ( ! is_null( $error ) ) {
		?>
<h1><?php _ex( 'Welcome', 'Howdy' ); ?></h1>
<p class="message"><?php echo $error; ?></p>
<?php } ?>
<form id="setup" method="post" action="install.php?step=2" novalidate="novalidate">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="weblog_title"><?php _e( 'Site Title' ); ?></label></th>
			<td><input name="weblog_title" type="text" id="weblog_title" size="25" value="<?php echo esc_attr( $weblog_title ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="admin_email"><?php _e( 'Your Email' ); ?></label></th>
			<td><input name="admin_email" type="email" id="admin_email" size="25" value="<?php echo esc_attr( $admin_email ); ?>" />
			<p><?php _e( 'Double-check your email address before continuing.' ); ?></p></td>
		</tr>
		<tr class="form-field form-required user-pass1-wrap">
			<th scope="row">
				<label for="pass1">
					<?php _e( 'Password' ); ?>
				</label>
			</th>
			<td>
				<div class="wp-pwd">
					<?php $initial_password = isset( $_POST['admin_password'] ) ? stripslashes( $_POST['admin_password'] ) : wp_generate_password( 18 ); ?>
					<input type="password" name="admin_password" id="pass1" class="regular-text" autocomplete="off" data-reveal="1" data-pw="<?php echo esc_attr( $initial_password ); ?>" aria-describedby="pass-strength-result" />
					<button type="button" class="button wp-hide-pw hide-if-no-js" data-start-masked="<?php echo (int) isset( $_POST['admin_password'] ); ?>" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
						<span class="dashicons dashicons-hidden"></span>
						<span class="text"><?php _e( 'Hide' ); ?></span>
					</button>
					<div id="pass-strength-result" aria-live="polite"></div>
				</div>
				<p><span class="description important hide-if-no-js">
				<strong><?php _e( 'Important:' ); ?></strong>
				<?php /* translators: The non-breaking space prevents 1Password from thinking the text "log in" should trigger a password save prompt. */ ?>
				<?php _e( 'You will need this password to log&nbsp;in. Please store it in a secure location.' ); ?></span></p>
			</td>
		</tr>
		<tr class="form-field form-required user-pass2-wrap hide-if-js">
			<th scope="row">
				<label for="pass2"><?php _e( 'Repeat Password' ); ?>
					<span class="description"><?php _e( '(required)' ); ?></span>
				</label>
			</th>
			<td>
				<input name="admin_password2" type="password" id="pass2" autocomplete="off" />
			</td>
		</tr>
		<tr class="pw-weak">
			<th scope="row"><?php _e( 'Confirm Password' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="pw_weak" class="pw-checkbox" />
					<?php _e( 'Confirm use of weak password' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php has_action( 'blog_privacy_selector' ) ? _e( 'Site visibility' ) : _e( 'Search engine visibility' ); ?></th>
			<td>
				<fieldset>
					<legend class="screen-reader-text"><span><?php has_action( 'blog_privacy_selector' ) ? _e( 'Site visibility' ) : _e( 'Search engine visibility' ); ?> </span></legend>
					<?php
					if ( has_action( 'blog_privacy_selector' ) ) {
						?>
						<input id="blog-public" type="radio" name="blog_public" value="1" <?php checked( 1, $blog_public ); ?> />
						<label for="blog-public"><?php _e( 'Allow search engines to index this site' ); ?></label><br/>
						<input id="blog-norobots" type="radio" name="blog_public" value="0" <?php checked( 0, $blog_public ); ?> />
						<label for="blog-norobots"><?php _e( 'Discourage search engines from indexing this site' ); ?></label>
						<p class="description"><?php _e( 'Note: Neither of these options blocks access to your site &mdash; it is up to search engines to honor your request.' ); ?></p>
						<?php
						/** This action is documented in wp-admin/options-reading.php */
						do_action( 'blog_privacy_selector' );
					} else {
						?>
						<label for="blog_public"><input name="blog_public" type="checkbox" id="blog_public" value="0" <?php checked( 0, $blog_public ); ?> />
						<?php _e( 'Discourage search engines from indexing this site' ); ?></label>
						<p class="description"><?php _e( 'It is up to search engines to honor this request.' ); ?></p>
					<?php } ?>
				</fieldset>
			</td>
		</tr>
	</table>
	<p class="step"><?php submit_button( __( 'Install calmPress' ), 'large', 'Submit', false, array( 'id' => 'submit' ) ); ?></p>
	<input type="hidden" name="language" value="<?php echo isset( $_REQUEST['language'] ) ? esc_attr( $_REQUEST['language'] ) : ''; ?>" />
</form>
	<?php
} // End display_setup_form().

// Let's check to make sure WP isn't already installed.
if ( is_blog_installed() ) {
	display_header();
	die(
		'<h1>' . __( 'Already Installed' ) . '</h1>' .
		'<p>' . __( 'You appear to have already installed calmPress. To reinstall please clear your old database tables first.' ) . '</p>' .
		'<p class="step"><a href="' . esc_url( wp_login_url() ) . '" class="button button-large">' . __( 'Log In' ) . '</a></p>' .
		'</body></html>'
	);
}

/**
 * @global string $required_php_version   The required PHP version string.
 * @global string $required_mysql_version The required MySQL version string.
 */
global $required_php_version, $required_mysql_version;

$php_version   = phpversion();
$mysql_version = $wpdb->db_version();
$php_compat    = version_compare( $php_version, $required_php_version, '>=' );
$mysql_compat  = version_compare( $mysql_version, $required_mysql_version, '>=' ) || file_exists( WP_CONTENT_DIR . '/db.php' );

$version_slug_parts = explode( '.', calmpress_version() );
$version_slug = $version_slug_parts[0] . '-' . $version_slug_parts[1];

$version_url = sprintf(
	/* translators: %s: calmPress version. */
	esc_url( 'https://calmpress.org/Version/%s' ),
	$version_slug
);

/* translators: %s: URL to Update PHP page. */
$php_update_message = '</p><p>' . sprintf(
	__( '<a href="%s">Learn more about updating PHP</a>.' ),
	esc_url( wp_get_update_php_url() )
);

$annotation = wp_get_update_php_annotation();

if ( $annotation ) {
	$php_update_message .= '</p><p><em>' . $annotation . '</em>';
}

if ( ! $mysql_compat && ! $php_compat ) {
	/* translators: 1: URL to calmPress release notes, 2: calmPress version number, 3: Minimum required PHP version number, 4: Minimum required MySQL version number, 5: Current PHP version number, 6: Current MySQL version number. */
	$compat = sprintf( __( 'You cannot install because <a href="%1$s">calmPress %2$s</a> requires PHP version %3$s or higher and MySQL version %4$s or higher. You are running PHP version %5$s and MySQL version %6$s.' ), $version_url, calmpress_version(), $required_php_version, $required_mysql_version, $php_version, $mysql_version ) . $php_update_message;
} elseif ( ! $php_compat ) {
	/* translators: 1: URL to calmPress release notes, 2: calmPress version number, 3: Minimum required PHP version number, 4: Current PHP version number. */
	$compat = sprintf( __( 'You cannot install because <a href="%1$s">calmPress %2$s</a> requires PHP version %3$s or higher. You are running version %4$s.' ), $version_url, calmpress_version(), $required_php_version, $php_version ) . $php_update_message;
} elseif ( ! $mysql_compat ) {
	/* translators: 1: URL to calmPress release notes, 2: calmPress version number, 3: Minimum required MySQL version number, 4: Current MySQL version number. */
	$compat = sprintf( __( 'You cannot install because <a href="%1$s">calmPress %2$s</a> requires MySQL version %3$s or higher. You are running version %4$s.' ), $version_url, calmpress_version(), $required_mysql_version, $mysql_version );
}

if ( ! $mysql_compat || ! $php_compat ) {
	display_header();
	die( '<h1>' . __( 'Requirements Not Met' ) . '</h1><p>' . $compat . '</p></body></html>' );
}

if ( ! is_string( $wpdb->base_prefix ) || '' === $wpdb->base_prefix ) {
	display_header();
	die(
		'<h1>' . __( 'Configuration Error' ) . '</h1>' .
		'<p>' . sprintf(
			/* translators: %s: wp-config.php */
			__( 'Your %s file has an empty database table prefix, which is not supported.' ),
			'<code>wp-config.php</code>'
		) . '</p></body></html>'
	);
}

// Set error message if DO_NOT_UPGRADE_GLOBAL_TABLES isn't set as it will break install.
if ( defined( 'DO_NOT_UPGRADE_GLOBAL_TABLES' ) ) {
	display_header();
	die(
		'<h1>' . __( 'Configuration Error' ) . '</h1>' .
		'<p>' . sprintf(
			/* translators: %s: DO_NOT_UPGRADE_GLOBAL_TABLES */
			__( 'The constant %s cannot be defined when installing WordPress.' ),
			'<code>DO_NOT_UPGRADE_GLOBAL_TABLES</code>'
		) . '</p></body></html>'
	);
}

// Make sure we have continuation from the wp-config.php generation phase by
// looking for a cookie that should have been set then. This is done to ensure
// that the install is not hijacked by someone else.
if ( ! isset( $_COOKIE['calmpress_install_auth_key'] ) || AUTH_KEY !== $_COOKIE['calmpress_install_auth_key'] ) {
	display_header();
	die(
		'<h1>' . __( 'Permissions Error' ) . '</h1>' .
		'<p>' . sprintf(
			/* translators: %s: wp-config.php */
			__( 'It seems like you are trying to complete the install from a different browser than the one you used to create the %s file, or more than a month passed since then.' ),
			'<code>wp-config.php</code>'
		) . '</p>' .
		'<p>' . sprintf(
			/* translators: %s: wp-config.php */
			__( 'You should either continue from the original browser, or delete the %s file and start over.' ),
			'<code>wp-config.php</code>'
		) . '</p></body></html>'
	);
}

/**
 * @global string    $wp_local_package Locale code of the package.
 * @global WP_Locale $wp_locale        WordPress date and time locale object.
 */
$language = '';
if ( ! empty( $_REQUEST['language'] ) ) {
	$language = preg_replace( '/[^a-zA-Z0-9_]/', '', $_REQUEST['language'] );
} elseif ( isset( $GLOBALS['wp_local_package'] ) ) {
	$language = $GLOBALS['wp_local_package'];
}

$scripts_to_print = array( 'jquery' );

switch ( $step ) {

	case 1: // Step 1, direct link or from language chooser.
		if ( ! empty( $language ) ) {
			$loaded_language = wp_download_language_pack( $language );
			if ( $loaded_language ) {
				load_default_textdomain( $loaded_language );
				$GLOBALS['wp_locale'] = new WP_Locale();
			}
		}

		$scripts_to_print[] = 'user-profile';

		display_header();
		?>
<h1><?php _ex( 'Welcome', 'Howdy' ); ?></h1>
<p><?php _e( 'Almost done! Just fill in the information below and you&#8217;ll be on your way to using the calmest CMS in the world.' ); ?></p>

<h2><?php _e( 'Information needed' ); ?></h2>
<p><?php _e( 'Please provide the following information. Don&#8217;t worry, you can always change these settings later.' ); ?></p>

		<?php
		display_setup_form();
		break;
	case 2:
		if ( ! empty( $language ) && load_default_textdomain( $language ) ) {
			$loaded_language      = $language;
			$GLOBALS['wp_locale'] = new WP_Locale();
		} else {
			$loaded_language = 'en_US';
		}

		if ( ! empty( $wpdb->error ) ) {
			wp_die( $wpdb->error->get_error_message() );
		}

		$scripts_to_print[] = 'user-profile';

		ob_start();
		display_header();
		// Fill in the data we gathered.
		$weblog_title         = isset( $_POST['weblog_title'] ) ? trim( wp_unslash( $_POST['weblog_title'] ) ) : '';
		$admin_password       = isset( $_POST['admin_password'] ) ? wp_unslash( $_POST['admin_password'] ) : '';
		$admin_password_check = isset( $_POST['admin_password2'] ) ? wp_unslash( $_POST['admin_password2'] ) : '';
		$admin_email          = isset( $_POST['admin_email'] ) ?trim( wp_unslash( $_POST['admin_email'] ) ) : '';
		$public               = isset( $_POST['blog_public'] ) ? (int) $_POST['blog_public'] : 1;

		// Check email address.
		$error = false;
		if ( $admin_password != $admin_password_check ) {
			// TODO: poka-yoke.
			display_setup_form( __( 'Your passwords do not match. Please try again.' ) );
			$error = true;
		} elseif ( empty( $admin_email ) ) {
			// TODO: Poka-yoke.
			display_setup_form( __( 'You must provide an email address.' ) );
			$error = true;
		} elseif ( ! is_email( $admin_email ) ) {
			// TODO: Poka-yoke.
			display_setup_form( __( 'Sorry, that isn&#8217;t a valid email address. Email addresses look like <code>username@example.com</code>.' ) );
			$error = true;
		}

		if ( false === $error ) {
			$wpdb->show_errors();
			$result = wp_install( $weblog_title, md5( $admin_email ), $admin_email, $public, '', wp_slash( $admin_password ), $loaded_language );

			if ( $result['password'] === wp_slash( $admin_password ) ) {
				wp_redirect( wp_login_url() );
				die();
			}
			?>

<h1><?php _e( 'Success!' ); ?></h1>

<p><?php _e( 'calmPress has been installed. Thank you, and enjoy!' ); ?></p>

<table class="form-table install-success">
	<tr>
		<th><?php _e( 'Email' ); ?></th>
		<td><?php echo esc_html( $admin_email ); ?></td>
	</tr>
	<tr>
		<th><?php _e( 'Password' ); ?></th>
		<td>
			<?php
			if ( ! empty( $result['password'] ) && empty( $admin_password_check ) ) :
				?>
			<code><?php echo esc_html( $result['password'] ); ?></code><br />
		<?php endif ?>
			<p><?php echo $result['password_message']; ?></p>
		</td>
	</tr>
</table>

<p class="step"><a href="<?php echo esc_url( wp_login_url() ); ?>" class="button button-large"><?php _e( 'Log In' ); ?></a></p>

			<?php
		}
		ob_end_flush();
		break;
}

if ( ! wp_is_mobile() ) {
	?>
<script type="text/javascript">var t = document.getElementById('weblog_title'); if (t){ t.focus(); }</script>
	<?php
}

wp_print_scripts( $scripts_to_print );
?>
<script type="text/javascript">
jQuery( function( $ ) {
	$( '.hide-if-no-js' ).removeClass( 'hide-if-no-js' );
} );
</script>
</body>
</html>
