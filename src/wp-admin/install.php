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
require_once ABSPATH . WPINC . '/class-wpdb.php';

nocache_headers();

$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'site-details';

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
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
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

	$weblog_title = isset( $_POST['weblog_title'] ) ? trim( wp_unslash( $_POST['weblog_title'] ) ) : '';
	$admin_email  = isset( $_POST['admin_email']  ) ? trim( wp_unslash( $_POST['admin_email'] ) ) : '';

	if ( ! is_null( $error ) ) {
		?>
<h1><?php _ex( 'Welcome', 'Howdy' ); ?></h1>
<p class="message"><?php echo esc_html( $error ); ?></p>
<?php } ?>
<form id="setup" method="post" action="install.php?step=install" novalidate="novalidate">
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="weblog_title"><?php _e( 'Site Title' ); ?></label></th>
			<td><input name="weblog_title" type="text" id="weblog_title" size="25" value="<?php echo esc_attr( $weblog_title ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="admin_email"><?php _e( 'Your Email' ); ?></label></th>
			<td><input name="admin_email" type="email" id="admin_email" size="25" aria-describedby="admin-email-desc" value="<?php echo esc_attr( $admin_email ); ?>" />
			<p id="admin-email-desc"><?php _e( 'Double-check your email address before continuing.' ); ?></p></td>
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
					<div class="password-input-wrapper">
						<input type="password" name="admin_password" id="pass1" class="regular-text" autocomplete="new-password" spellcheck="false" data-reveal="1" data-pw="<?php echo esc_attr( $initial_password ); ?>" aria-describedby="pass-strength-result admin-password-desc" />
						<div id="pass-strength-result" aria-live="polite"></div>
					</div>
					<button type="button" class="button wp-hide-pw hide-if-no-js" data-start-masked="<?php echo (int) isset( $_POST['admin_password'] ); ?>" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
						<span class="dashicons dashicons-hidden"></span>
						<span class="text"><?php _e( 'Hide' ); ?></span>
					</button>
				</div>
				<p id="admin-password-desc"><span class="description important hide-if-no-js">
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
				<input type="password" name="admin_password2" id="pass2" autocomplete="new-password" spellcheck="false" />
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
		'<p class="step"><a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log In' ) . '</a></p>' .
		'</body></html>'
	);
}

/**
 * @global string   $required_php_version    The minimum required PHP version string.
 * @global string[] $required_php_extensions The names of required PHP extensions.
 * @global string[] $upto_php_version        The maximal supported PHP verion.
 * @global string   $required_mysql_version  The minimum required MySQL version string.
 * @global string   $upto_mysql_version      The maximal supported MySQL version string.
 * @global string   $required_mariadb_version  The minimum required mariaDB version string.
 * @global string   $upto_mariadb_version      The maximal supported mariaDB version string.
 * @global string[] $required_apache_modules The names of the required apache modules.
 * @global wpdb     $wpdb                    WordPress database abstraction object.
 */
global $required_php_version,
	   $upto_php_version,
       $required_php_extensions, 
	   $alternative_php_extensions,
	   $required_mysql_version,
	   $upto_mysql_version,
	   $required_mariadb_version,
	   $upto_mariadb_version,
	   $required_apache_modules,
	   $wpdb;

$php_version   = PHP_VERSION;

$mysql_version = $wpdb->db_version();

$version_slug_parts = explode( '.', calmpress_version() );
$version_slug = $version_slug_parts[0] . '-' . $version_slug_parts[1];

$version_url = sprintf(
	/* translators: %s: calmPress version. */
	esc_url( 'https://calmpress.org/Version/%s' ),
	$version_slug
);

$php_update_message = '</p><p>' . sprintf(
	/* translators: %s: URL to Update PHP page. */
	__( '<a href="%s">Learn more about updating PHP</a>.' ),
	esc_url( wp_get_update_php_url() )
);

$annotation = wp_get_update_php_annotation();

if ( $annotation ) {
	$php_update_message .= '</p><p><em>' . $annotation . '</em>';
}
$errors = [];

// get current version data locally.
require ABSPATH . WPINC . '/version.php';

// Check PHP minimal compatibility.
$php_compat = version_compare( $php_version, $required_php_version, '>=' );
if ( ! $php_compat ) {
	$errors[] = sprintf(
		/* translators: 1: Minimum required PHP version number, 2: Current PHP version number. */
		esc_html__( 'PHP version must be %1$s or higher. You are running version %2$s.' ),
		esc_html( $required_php_version ),
		esc_html( $php_version )
	)
	. $php_update_message;
}

// Check PHP maximal compatibility.
if ( version_compare( $php_version, $upto_php_version . '.9999', '>' ) ) {
	$errors[] = sprintf(
		/* translators: 1: Maximal supported PHP version number, 2: Current PHP version number. */
		esc_html__( 'PHP versions %1$s or higher are not supported. You are running version %2$s.' ),
		esc_html( $required_php_version ),
		esc_html( $php_version )
	);
}

// Check DB server compatibility. Skip check if using a replacement driver.
if ( ! file_exists( WP_CONTENT_DIR . '/db.php' ) ) {
	$db_server_name = 'MySQL';
	$db_min_version = $required_mysql_version;
	$db_max_version = $upto_mysql_version;
	
	// check if the DB is mariadb
	$server_version = $wpdb->get_var( 'SELECT VERSION()' );
	if ( stristr( $server_version, 'mariadb' ) ) {
		$db_server_name = 'MariaDB';
		$db_min_version = $required_mariadb_version;
		$db_max_version = $upto_mariadb_version;
	}
	
	$mysql_compat  = version_compare( $mysql_version, $db_min_version, '>=' );
	if ( ! $mysql_compat ) {
		$errors[] = sprintf( 
			/* translators: 1: Name of DB server software, 2: Minimum required DB server version number, 3: Current DB server version number. */
			esc_html__( '%1$s version must be %2$s or higher. You are running version %3$s.' ),
			esc_html( $db_server_name ),
			esc_html( $db_min_version ),
			esc_html( $server_version )
		);
	}

	if ( version_compare( $mysql_version, $db_max_version . '9999', '>' ) ) {
		$errors[] = sprintf( 
			/* translators: 1: Name of DB server software, 2: Minimum required DB server version number, 3: Current DB server version number. */
			esc_html__( '%1$s version must be no higher than %2$s. You are running version %3$s.' ),
			esc_html( $db_server_name ),
			esc_html( $db_max_version ),
			esc_html( $server_version )
		);
	}
}

// CalmPress requires HTTPS for every installation, including local development sites.
if ( ! is_ssl() ) {
	$errors[] = esc_html__( 'This site is not currently being accessed over HTTPS. HTTPS is required to install calmPress.' );
}

// Check for require php extensions.
foreach ( $required_php_extensions as $extension ) {
	if ( ! extension_loaded( $extension ) ) {
		if ( isset( $alternative_php_extensions[ $extension ] ) ) {
			// Check if an alternative can be found.
			$found = false;
			foreach ( $alternative_php_extensions[ $extension ] as $altenative ) {
				if ( extension_loaded( $extension ) ) {
					$found = true;
				}
			}
			// No alternative found, set an error message.
			if ( ! $found ) {
				$errors[] = sprintf(
					/* translators: 1: The name of the extension, 2: List of alternative extensions. */
					esc_html__( 'The required PHP extension %1$s is not enabled. Possible alternatives: %2$s' ),
					esc_html( $extension ),
					esc_html( join( ', ', $alternative_php_extensions[ $extension ] ) )
				);
			}
		} else {
			$errors[] = sprintf(
				/* translators: 1: The name of the extension. */
				esc_html__( 'The required PHP extension %1$s is not enabled.' ),
				esc_html( $extension )
			);
		}
	}
}

// Check for require apache modules if it is being used.
if ( is_apache() ) {
	foreach ( $required_apache_modules as $module ) {
		if ( false === apache_mod_loaded( $module, 'unknown' ) ) {
			$errors[] = sprintf(
				/* translators: 1: The name of the module. */
				esc_html__( 'The required Apache module %1$s is not enabled.' ),
				esc_html( $module )
			);
		}
	}
}

if ( ! empty( $errors ) ) {
	display_header();
	$html = '<h1>' . esc_html__( 'Requirements Not Met' ) . '</h1><p>';
	$html .= esc_html__( ' Problems that needs to be fixed ') . '</p><ul>';
	foreach ( $errors as $error ) {
		$html .= '<li>' . $error . '</li>';
	}
	$html .= '</ul></body></html>';
	die( $html );
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
	$language = sanitize_locale_name( $_REQUEST['language'] );
} elseif ( isset( $GLOBALS['wp_local_package'] ) ) {
	$language = $GLOBALS['wp_local_package'];
}

$scripts_to_print = array( 'jquery' );

switch ( $step ) {

	case 'site-details': // Direct link or from language chooser.
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
<p><?php _e( 'Please provide the following information. Do not worry, you can always change these settings later.' ); ?></p>

		<?php
		display_setup_form();
		break;
	case 'install':
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
		$public               = 1;

		// Check email address.
		$installation_errors = new WP_Error();
		if ( $admin_password != $admin_password_check ) {
			// TODO: poka-yoke.
			$installation_errors->add( 'password_mismatch', __( 'Your passwords do not match. Please try again.' ) );
		} elseif ( empty( $admin_email ) ) {
			// TODO: Poka-yoke.
			$installation_errors->add( 'admin_email_empty', __( 'You must provide an email address.' ) );
		} elseif ( ! is_email( $admin_email ) ) {
			// TODO: Poka-yoke.
			$installation_errors->add( 'admin_email_invalid', __( 'Please enter a valid email address.' ) );
		}

		if ( ! $installation_errors->has_errors() ) {
			$parts  = wp_parse_url( wp_guess_url() );
			$domain = $parts['host'];
			$path   = '/' . trim( $parts['path'] ?? '', '/' ) . '/';

			if ( '//' === $path ) {
				$path = '/';
			}

			$wpdb->show_errors();
			wp_install( $weblog_title, md5( $admin_email ), $admin_email, $public, '', wp_slash( $admin_password ), $loaded_language );

			global $wp_rewrite;
			$wp_rewrite->init();
			flush_rewrite_rules( false );

			if ( is_multisite() ) {
				foreach ( $wpdb->tables( 'ms_global' ) as $table => $prefixed_table ) {
					$wpdb->$table = $prefixed_table;
				}

				install_network();
				populate_network( 1, $domain, $admin_email, $weblog_title, $path, is_subdomain_install() );
			}

			ob_end_clean();
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		if ( $installation_errors->has_errors() ) {
			display_setup_form( $installation_errors->get_error_message() );
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
wp_print_footer_scripts();
?>
<script type="text/javascript">
jQuery( function( $ ) {
	$( '.hide-if-no-js' ).removeClass( 'hide-if-no-js' );

} );
</script>
</body>
</html>
