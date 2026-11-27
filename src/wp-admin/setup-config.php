<?php
/**
 * Retrieves and creates the wp-config.php file.
 *
 * Creates wp-config.php through PHP file access or a validated FTP/SFTP connection.
 *
 * @package WordPress
 * @subpackage Administration
 */

/**
 * We are installing.
 */
define( 'WP_INSTALLING', true );

/**
 * We are blissfully unaware of anything.
 */
define( 'WP_SETUP_CONFIG', true );

/**
 * Disable error reporting
 *
 * Set this to error_reporting( -1 ) for debugging
 */
error_reporting( 0 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

ob_start();

require ABSPATH . 'wp-settings.php';

/** Load WordPress Administration Upgrade API */
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/** Load shared installer utilities. */
require_once ABSPATH . 'wp-admin/includes/install-utilities.php';

/** Load WordPress Translation Installation API */
require_once ABSPATH . 'wp-admin/includes/translation-install.php';

nocache_headers();

$config_file = file( ABSPATH . 'wp-admin/wp-config.sample' );

// Check if wp-config.php has been created.
if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
	wp_die(
		'<p>' . sprintf(
			/* translators: 1: wp-config.php, 2: install.php */
			__( 'The file %1$s already exists. If you need to reset any of the configuration items in this file, please delete it first. You may try <a href="%2$s">installing now</a>.' ),
			'<code>wp-config.php</code>',
			'install.php'
		) . '</p>',
		409
	);
}

// Check if wp-config.php exists above the root directory but is not part of another installation.
if ( @file_exists( ABSPATH . '../wp-config.php' ) && ! @file_exists( ABSPATH . '../wp-settings.php' ) ) {
	wp_die( '<p>' . sprintf(
			/* translators: 1: wp-config.php 2: install.php */
			__( 'The file %1$s already exists one level above your calmPress installation. If you need to reset any of the configuration items in this file, please delete it first. You may try <a href="%2$s">installing now</a>.' ),
			'<code>wp-config.php</code>',
			'install.php'
		) . '</p>',
		409
	);
}

$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'welcome';

/**
 * Detect whether the DB user has access beyond the current WordPress database.
 *
 * @since calmPress 1.0.0
 *
 * @param wpdb $wpdb
 * @return bool True if the user appears to have access to other databases.
 */
function is_broad_db_user( $wpdb ): bool {
	$grants = $wpdb->get_col( 'SHOW GRANTS FOR CURRENT_USER' );

	// Could not reliably determine privileges.
	if ( ! is_array( $grants ) || empty( $grants ) ) {
		return true;
	}

	$current_db = strtolower( $wpdb->dbname );
	$databases  = array();

	foreach ( $grants as $grant ) {

		/*
		 * Detect actual global privileges.
		 *
		 * Ignore:
		 * GRANT USAGE ON *.* ...
		 *
		 * because USAGE grants no privileges.
		 */
		if (
			preg_match( '/^GRANT\s+(.+?)\s+ON\s+\*\.\*/i', $grant, $matches )
		) {
			$privileges = strtoupper( trim( $matches[1] ) );

			if ( $privileges !== 'USAGE' ) {
				return true;
			}
		}

		/*
		 * Detect database-specific grants:
		 * ON `database`.*
		 * ON `database`.`table`
		 */
		if (
			preg_match(
				'/ON\s+`([^`]+)`\.(?:\*|`[^`]+`)/i',
				$grant,
				$matches
			)
		) {
			$databases[ strtolower( $matches[1] ) ] = true;
		}
	}

	unset( $databases[ $current_db ] );

	return ! empty( $databases );
}

/**
 * Detect whether the database is non-empty.
 *
 * This is a lightweight heuristic used during installation to determine
 * whether the selected database already contains any tables.
 * 
 * @since calmPress 1.0.0
 *
 * @param wpdb $wpdb WordPress database abstraction object.
 *
 * @return bool True if the database contains at least one table, false if empty.
 */
function db_has_tables( wpdb $wpdb ): bool {
    $tables = $wpdb->get_col( 'SHOW TABLES' );

    return ! empty( $tables );
}

/**
 * Display a confirmation when database settings may share access with other applications.
 *
 * @since calmPress 1.0.0
 *
 * @param string $message The HTML-escaped message to display.
 * @param wpdb   $wpdb    Database connection containing the selected settings.
 */
function problematic_db_screen( string $message, $wpdb ) {
	setup_config_display_header();
	?>
<h1><?php esc_html_e( 'Confirm database setup' ); ?></h1>
<p><?php echo $message; ?></p>
<p><?php esc_html_e( 'If applications share a database or database user, a security problem in one application could affect the others.' ); ?></p>
<p><?php esc_html_e( 'For better security, use an empty database dedicated to this installation, with a database user that cannot access other applications’ databases.' ); ?></p>
<h2><?php esc_html_e( 'Selected database settings' ); ?></h2>
<table>
	<tr>
		<th scope="row"><?php esc_html_e( 'Database host' ); ?></th>
		<td><?php echo esc_html( $wpdb->dbhost ); ?></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Database name' ); ?></th>
		<td><?php echo esc_html( $wpdb->dbname ); ?></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Database username' ); ?></th>
		<td><?php echo esc_html( $wpdb->dbuser ); ?></td>
	</tr>
</table>
<form method="post" action="setup-config.php?step=prepare-files">
	<?php

	// Preserve the submitted settings while omitting the flag that would repeat this confirmation.
	foreach ( wp_unslash( $_POST ) as $key => $value ) {

		if ( 'db_verification' === $key ) {
			continue;
		}

		printf(
			'<input type="hidden" name="%s" value="%s">',
			esc_attr( $key ),
			esc_attr( $value )
		);
	}

	?>
	<p class="step"><button class="button button-large" type="submit"><?php esc_html_e( 'Continue anyway' ); ?></button> <button class="button button-large" type="submit" formaction="setup-config.php?step=database"><?php esc_html_e( 'Change database settings' ); ?></button></p>
</form>
	<?php
}

/**
 * Display setup wp-config.php file header.
 *
 * @ignore
 * @since 2.3.0
 *
 * @param string|string[] $body_classes Class attribute values for the body tag.
 */
function setup_config_display_header( $body_classes = array() ) {
	$body_classes   = (array) $body_classes;
	$body_classes[] = 'wp-core-ui';
	$dir_attr       = '';
	if ( is_rtl() ) {
		$body_classes[] = 'rtl';
		$dir_attr       = ' dir="rtl"';
	}

	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html<?php echo $dir_attr; ?>>
<head>
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php _e( 'calmPress &rsaquo; Setup Configuration File' ); ?></title>
	<?php wp_admin_css( 'install', true ); ?>
</head>
<body class="<?php echo implode( ' ', $body_classes ); ?>">
<p id="logo"><a href="<?php echo esc_url( __( 'https://calmpress.org/' ) ); ?>"><?php _e( 'calmPress' ); ?></a></p>
	<?php
} // End function setup_config_display_header();

/**
 * Display the language selection screen before configuration begins.
 *
 * @since calmPress 1.0.0
 *
 * @param string $error Error to display if a language pack could not be installed.
 */
function setup_config_display_language_screen( string $error = '' ): void {
	setup_config_display_header( 'language-chooser' );
	if ( '' !== $error ) {
		echo '<p class="message">' . esc_html( $error ) . '</p>';
	}

	echo '<h1 class="screen-reader-text">' . esc_html__( 'Select a default language' ) . '</h1>';
	echo '<form method="post" action="setup-config.php?step=welcome">';
	wp_install_language_form( wp_get_available_translations() );
	echo '</form>';
	wp_print_scripts( 'language-chooser' );
	echo '</body></html>';
}

/**
 * Display database settings, retaining supplied values and showing an installation error when provided.
 *
 * @since calmPress 1.0.0
 *
 * @param array  $parameters Unslashed database form values.
 * @param string $language   Installation language.
 * @param string $error      Plain-text error to display above the form.
 */
function setup_config_display_database_form( array $parameters, string $language, string $error = '' ): void {
	setup_config_display_header();

	$database_settings = [
		'dbname' => '',
		'uname'  => '',
		'pwd'    => '',
		'dbhost' => 'localhost',
		'prefix' => 'cp_' . uniqid() . '_',
	];

	// Fill the database fields from the settings supplied for this display.
	foreach ( $database_settings as $name => $default ) {
		if ( isset( $parameters[ $name ] ) && is_string( $parameters[ $name ] ) ) {
			$database_settings[ $name ] = $parameters[ $name ];
		}
	}
	?>
<h1><?php esc_html_e( 'Database settings' ); ?></h1>
<?php if ( '' !== $error ) { ?>
<p class="message" role="alert"><?php echo esc_html( $error ); ?></p>
<?php } ?>
<form id="database-setup" method="post" action="setup-config.php?step=prepare-files">
<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
<p><?php _e( 'Below you should enter your database connection details. If you are not sure about these, contact your host.' ); ?></p>
<p><?php esc_html_e( 'For better security, use an empty database dedicated to this installation, with a database user that cannot access other applications’ databases.' ); ?></p>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><label for="dbname"><?php esc_html_e( 'Name' ); ?></label></th>
		<td><input name="dbname" id="dbname" type="text" aria-describedby="dbname-desc" size="25" value="<?php echo esc_attr( $database_settings[ 'dbname' ] ); ?>" /></td>
		<td id="dbname-desc"><?php _e( 'The name of the database you want to use with calmPress.' ); ?></td>
	</tr>
	<tr>
		<th scope="row"><label for="uname"><?php esc_html_e( 'Username' ); ?></label></th>
		<td><input name="uname" id="uname" type="text" aria-describedby="uname-desc" size="25" value="<?php echo esc_attr( $database_settings[ 'uname' ] ); ?>" /></td>
		<td id="uname-desc"><?php _e( 'Your database username.' ); ?></td>
	</tr>
	<tr>
		<th scope="row"><label for="pwd"><?php esc_html_e( 'Password' ); ?></label></th>
		<td><input name="pwd" id="pwd" type="text" aria-describedby="pwd-desc" size="25" value="<?php echo esc_attr( $database_settings[ 'pwd' ] ); ?>" autocomplete="off" /></td>
		<td id="pwd-desc"><?php _e( 'Your database password.' ); ?></td>
	</tr>
	<tr id="database-host-settings" class="hide-if-js">
		<th scope="row"><label for="dbhost"><?php esc_html_e( 'Host' ); ?></label></th>
		<td><input name="dbhost" id="dbhost" type="text" aria-describedby="dbhost-desc" size="25" value="<?php echo esc_attr( $database_settings[ 'dbhost' ] ); ?>" /></td>
		<td id="dbhost-desc"><?php esc_html_e( 'The server hosting your database. Leave this as localhost unless your hosting provider supplied another address.' ); ?></td>
	</tr>
	<tr id="database-prefix-settings" class="hide-if-js">
		<th scope="row"><label for="prefix"><?php _e( 'Table Prefix' ); ?></label></th>
		<td><input name="prefix" id="prefix" type="text" aria-describedby="prefix-desc" value="<?php echo esc_attr( $database_settings[ 'prefix' ] ); ?>" size="25" /></td>
		<td id="prefix-desc"><?php esc_html_e( 'Added to database table names. You can usually keep the generated value.' ); ?></td>
	</tr>
	<tr class="hide-if-no-js">
		<th></th>
		<td></td>
		<td>
			<button type="button" class="toggle-advanced button" aria-expanded="<?php echo '' !== $error ? 'true' : 'false'; ?>" aria-controls="database-host-settings database-prefix-settings" data-show-text="<?php echo esc_attr__( 'Show Advanced Settings' ); ?>" data-hide-text="<?php echo esc_attr__( 'Hide Advanced Settings' ); ?>">
				<?php esc_html_e( 'Show Advanced Settings' ); ?>
			</button>
		</td>
	</tr>
</table>
<p class="step"><button type="submit" class="button button-large"><?php esc_html_e( 'Continue installation' ); ?></button></p>
<input type="hidden" name="db_verification" value="1">
</form>
<script>
( function () {
const form_element = document.getElementById( 'database-setup' );
const button_element = form_element.querySelector( '.toggle-advanced' );
const advanced_rows = form_element.querySelectorAll( '.hide-if-js' );
const advanced_expanded = button_element.getAttribute( 'aria-expanded' ) === 'true';

for ( const row_element of advanced_rows ) {
	row_element.hidden = ! advanced_expanded;
}

button_element.textContent = advanced_expanded ? button_element.dataset.hideText : button_element.dataset.showText;

for ( const row_element of form_element.querySelectorAll( '.hide-if-no-js' ) ) {
	row_element.classList.remove( 'hide-if-no-js' );
}

/**
 * Toggle the database host and table prefix fields and their button label.
 *
 * @since calmPress 1.0.0
 */
function toggle_advanced_settings() {
	const expanded = button_element.getAttribute( 'aria-expanded' ) === 'true';

	for ( const row_element of advanced_rows ) {
		row_element.hidden = expanded;
	}

	button_element.setAttribute( 'aria-expanded', String( ! expanded ) );
	button_element.textContent = expanded ? button_element.dataset.showText : button_element.dataset.hideText;
}

button_element.addEventListener( 'click', toggle_advanced_settings );
}() );
</script>
	<?php
}

/**
 * Display file-access choices or the selected credentials form after file preparation fails.
 *
 * Both forms carry the submitted database settings and any prior database-sharing
 * confirmation in hidden fields so installation can retry with the same settings.
 *
 * @since calmPress 1.0.0
 *
 * @param array    $parameters Unslashed submitted database and credential fields.
 * @param string   $language   Installation language.
 * @param WP_Error $errors     Failure preventing file preparation.
 */
function setup_config_display_filesystem_form( array $parameters, string $language, WP_Error $errors ): void {
	$fields = '<input type="hidden" name="language" value="' . esc_attr( $language ) . '">';

	// Carry database settings and any prior sharing confirmation into the retry POST.
	foreach ( [ 'dbname', 'uname', 'pwd', 'dbhost', 'prefix', 'db_verification' ] as $name ) {
		if ( isset( $parameters[ $name ] ) ) {
			$fields .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $parameters[ $name ] ) . '">';
		}
	}


	if ( ! isset( $parameters['calm_credentials_protocol'] ) && 'credentials' !== ( $parameters['filesystem_access'] ?? '' ) ) {
		setup_config_display_header();
		?>
<h1><?php esc_html_e( 'File access required' ); ?></h1>
<p><?php esc_html_e( 'Installation could not prepare all required files and directories. Choose how to continue.' ); ?></p>
<?php if ( 'runtime_directory_removal_failed' === $errors->get_error_code() ) { ?>
<p><?php echo esc_html( $errors->get_error_message() ); ?></p>
<?php } ?>
<p><?php esc_html_e( 'Installation needs to create the following files and directories:' ); ?></p>
<ul>
	<li><code>wp-config.php</code></li>
	<li><code>.htaccess</code></li>
	<li><code>wp-content/.private/</code></li>
	<li><code>wp-content/uploads/</code></li>
	<li><code><?php echo esc_html( trailingslashit( str_replace( ABSPATH, '', WP_LANG_DIR ) ) ); ?></code></li>
</ul>
<form method="post" action="setup-config.php?step=prepare-files">
	<?php echo $fields; ?>
	<h2><?php esc_html_e( 'Use FTP/SFTP credentials — recommended' ); ?></h2>
	<p><?php esc_html_e( 'Let calmPress prepare the required files and directories without making the whole installation writable by the web server.' ); ?></p>
	<p class="step"><button class="button button-large" type="submit" name="filesystem_access" value="credentials" formaction="setup-config.php?step=filesystem-credentials"><?php esc_html_e( 'Enter FTP/SFTP credentials' ); ?></button></p>
	<h2><?php esc_html_e( 'Change ownership or permissions manually — less secure' ); ?></h2>
	<p><?php
		/* translators: %s: Installation directory path. */
		printf( esc_html__( 'Connect to your server using SSH or another method that provides a command-line terminal. Temporarily change ownership or permissions so the web server can write to the files and directories in %s, then select Check again.' ), esc_html( ABSPATH ) );
	?></p>
	<p><?php esc_html_e( 'This option is less secure and is not recommended. After installation, restore protection for the calmPress files and .languages directory. Keep .private and uploads writable by the web server.' ); ?></p>
	<p class="step"><button class="button button-large" type="submit"><?php esc_html_e( 'Check again' ); ?></button></p>
</form>
		<?php
		return;
	}
	$initial_state = [];

	foreach ( $parameters as $name => $value ) {
		if ( str_starts_with( $name, 'calm_ftp_' ) || str_starts_with( $name, 'calm_sftp_' ) || 'calm_credentials_protocol' === $name ) {
			$initial_state[ $name ] = $value;
		}
	}

	unset( $initial_state['calm_ftp_password'], $initial_state['calm_sftp_password'], $initial_state['calm_sftp_private_key_text'], $initial_state['calm_sftp_passphrase'] );
	$install_base_url = trailingslashit( wp_guess_url() );
	wp_register_script( 'calm-form-validate', $install_base_url . 'wp-admin/js/form-validate.js', [], false, false );
	wp_register_script( 'calm-credentials-storage', $install_base_url . 'wp-includes/js/cp-credentials-storage.js', [ 'calm-form-validate' ], false, false );
	wp_register_script( 'calm-ftp-credentials', $install_base_url . 'wp-includes/js/cp-ftp-credentials-form.js', [ 'calm-credentials-storage' ], false, false );
	wp_register_script( 'calm-sftp-credentials', $install_base_url . 'wp-includes/js/cp-sftp-credentials-form.js', [ 'calm-credentials-storage' ], false, false );
	wp_register_script( 'calm-ftp-sftp-credentials', $install_base_url . 'wp-includes/js/cp-ftp-sftp-credentials-form.js', [ 'calm-ftp-credentials', 'calm-sftp-credentials' ], false, false );
	setup_config_display_header();
	?>
<h1><?php esc_html_e( 'File access required' ); ?></h1>
<?php if ( $errors->has_errors() ) { ?>
<p class="message" role="alert"><?php echo esc_html( $errors->get_error_message() ); ?></p>
<?php } ?>
<p><?php esc_html_e( 'Enter FTP or SFTP credentials so calmPress can create the files and directories needed for installation.' ); ?></p>
<div id="calm-install-credentials-wrapper">
	<?php
	$form = \calmpress\filesystem\Filesystem_Access_Over_FTP_Or_SFTP::form( $initial_state, null, __( 'Continue installation' ), 'setup-config.php?step=prepare-files' );

	// Match the action button styling and spacing used by the other installer steps.
	$form = str_replace( [ 'class="submit"', 'class="button button-primary button-large"' ], [ 'class="step"', 'class="button button-large"' ], $form );

	echo str_replace( '</form>', $fields . '</form>', $form );
	?>
</div>
	<?php
	wp_print_scripts( 'calm-ftp-sftp-credentials' );
	wp_print_footer_scripts();
}

/**
 * @global string    $wp_local_package Locale code of the package.
 * @global WP_Locale $wp_locale        WordPress date and time locale object.
 */
$language = '';
if ( isset( $_REQUEST['language'] ) && is_string( $_REQUEST['language'] ) ) {
	$language = sanitize_locale_name( wp_unslash( $_REQUEST['language'] ) );
} elseif ( isset( $GLOBALS['wp_local_package'] ) ) {
	$language = $GLOBALS['wp_local_package'];
}

if ( 'language' === $step ) {
	setup_config_display_language_screen();
	ob_end_flush();
	exit;
}

if ( 'welcome' === $step && '' !== $language && false === wp_download_language_pack( $language ) ) {
	setup_config_display_language_screen( __( 'The selected language could not be installed. Choose another language or try again.' ) );
	ob_end_flush();
	exit;
}

if ( '' !== $language ) {
	load_default_textdomain( $language );
	$GLOBALS['wp_locale'] = new WP_Locale();
}

// Reject HTTP before showing setup forms or accepting connection credentials.
if ( ! is_ssl() ) {
	wp_die(
		esc_html__( 'This site is not currently being accessed over HTTPS. HTTPS is required to install calmPress.' ),
		esc_html__( 'HTTPS required' ),
		[ 'response' => 403 ]
	);
}

// Save the site type before collecting database and filesystem credentials.
if ( 'site-type' === $step && isset( $_POST['site_type'] ) ) {
	$install_type = 'standalone';

	if ( 'network' === $_POST['site_type'] ) {
		$install_type = wp_unslash( $_POST['install_type'] ?? '' );

		if ( ! in_array( $install_type, [ 'network_subdirectory', 'network_subdomain' ], true ) ) {
			throw new UnexpectedValueException( 'Missing or unsupported network installation type.' );
		}
	}

	$site_path = trim( wp_parse_url( wp_guess_url(), PHP_URL_PATH ) ?? '', '/' );

	if ( 'network_subdomain' === $install_type && '' !== $site_path ) {
		wp_die( esc_html__( 'Sorry, Subdomain-based sites can only be used when the installation is located at the root of a domain.' ) );
	}

	setcookie( 'calmpress_install_type', $install_type, installation_cookie_options( time() + HOUR_IN_SECONDS ) );
	wp_redirect( add_query_arg( 'language', $language, 'setup-config.php?step=database' ) );
	exit;
}

$webserver_step_url = 'setup-config.php?step=webserver-check';
$site_type_step_url = 'setup-config.php?step=site-type';
$database_step_url = 'setup-config.php?step=database';

if ( '' !== $language ) {
	$webserver_step_url = add_query_arg( 'language', $language, $webserver_step_url );
	$site_type_step_url = add_query_arg( 'language', $language, $site_type_step_url );
	$database_step_url = add_query_arg( 'language', $language, $database_step_url );
}

switch ( $step ) {
	case 'filesystem-credentials':
		setup_config_display_filesystem_form( wp_unslash( $_POST ), $language, new WP_Error() );
		break;

	case 'welcome':
		setup_config_display_header();
		?>
<h1><?php esc_html_e( 'Welcome to calmPress' ); ?></h1>
<p><?php esc_html_e( 'This installer will help you choose your site setup, connect to a database, and create your administrator account.' ); ?></p>
<p><?php esc_html_e( 'You may need help from your server administrator or hosting provider with server configuration or file permissions. If file access is needed, you can provide FTP/SFTP credentials during installation.' ); ?></p>

<p class="step"><a href="<?php echo esc_url( $webserver_step_url ); ?>" class="button button-large"><?php esc_html_e( 'Begin installation' ); ?></a></p>
		<?php
		break;

	case 'webserver-check':
		$web_server_owns_files = \calmpress\filesystem\File_Modification_Access::is_owned_by_web_server( ABSPATH )
			|| \calmpress\filesystem\File_Modification_Access::is_owned_by_web_server( ABSPATH . 'wp-settings.php' );

		if ( ! $web_server_owns_files ) {
			wp_redirect( $site_type_step_url );
			exit;
		}

		setup_config_display_header();
		?>
<h1><?php esc_html_e( 'Web server configuration' ); ?></h1>
<p><?php esc_html_e( 'Web requests run under an account that also owns the installation directory or calmPress files. For stronger protection, the server should use separate accounts for web requests and file ownership.' ); ?></p>
<p><?php esc_html_e( 'Contact your server administrator or hosting provider for help with this configuration.' ); ?></p>
<form method="get" action="setup-config.php">
	<input type="hidden" name="step" value="webserver-check">
	<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
	<p class="step"><button type="submit" class="button button-large"><?php esc_html_e( 'Check again' ); ?></button> <a href="<?php echo esc_url( $site_type_step_url ); ?>" class="button button-large"><?php esc_html_e( 'Continue installation' ); ?></a></p>
</form>
		<?php
		break;

	case 'site-type':
		setup_config_display_header();
		$install_type = $_COOKIE['calmpress_install_type'] ?? 'standalone';

		if ( ! in_array( $install_type, [ 'standalone', 'network_subdirectory', 'network_subdomain' ], true ) ) {
			$install_type = 'standalone';
		}

		$is_network   = 'standalone' !== $install_type;
		$parts        = wp_parse_url( wp_guess_url() );
		$host         = $parts['host'];
		$path         = trim( $parts['path'] ?? '', '/' );
		$site_url     = $host . '/';

		if ( '' !== $path ) {
			$site_url .= $path . '/';
		}

		?>
<h1><?php esc_html_e( 'Choose your site setup' ); ?></h1>
<form id="installation-type" method="post" action="setup-config.php?step=site-type">
	<input type="hidden" name="language" value="<?php echo esc_attr( $language ); ?>">
	<fieldset>
		<p><label><input type="radio" name="site_type" value="standalone" aria-describedby="standalone-description"<?php checked( ! $is_network ); ?> required> <?php esc_html_e( 'Standalone' ); ?></label></p>
		<p id="standalone-description" class="description"><?php esc_html_e( 'Create one website with its own content, settings, and users.' ); ?></p>
		<p><label><input type="radio" name="site_type" value="network" aria-describedby="network-description"<?php checked( $is_network ); ?>> <?php esc_html_e( 'Network' ); ?></label></p>
		<p id="network-description" class="description"><?php esc_html_e( 'Manage one or more websites, each with its own content and settings. A network administrator (by default, you) manages the network and can also manage individual sites within it. Users can be given permission to administer multiple sites using the same account.' ); ?></p>
	</fieldset>
	<fieldset id="network-types" aria-labelledby="network-addresses-heading"<?php
		if ( ! $is_network ) {
			echo ' hidden disabled';
		}
	?>>
		<h2 id="network-addresses-heading"><?php esc_html_e( 'Network site addresses' ); ?></h2>
		<p><label><input type="radio" name="install_type" value="network_subdirectory" aria-describedby="subdirectory-description"<?php checked( 'network_subdomain' !== $install_type ); ?>> <?php
			printf(
				/* translators: %s: Example site URL. */
				esc_html__( 'Subdirectories (%s)' ),
				esc_html( $site_url . 'site1/' )
			);
		?></label></p>
		<p id="subdirectory-description" class="description"><?php esc_html_e( 'Useful for related sites that should share a common identity while still being managed separately.' ); ?></p>
		<?php if ( '' === $path ) { ?>
		<p><label><input type="radio" name="install_type" value="network_subdomain" aria-describedby="subdomain-description subdomain-configuration-description"<?php checked( $install_type, 'network_subdomain' ); ?>> <?php
			printf(
				/* translators: %s: Example site URL. */
				esc_html__( 'Subdomains (%s)' ),
				esc_html( 'site1.' . $host )
			);
		?></label></p>
		<p id="subdomain-description" class="description"><?php esc_html_e( 'Useful for related sites that should maintain distinct identities while still being managed together.' ); ?></p>
		<div id="subdomain-configuration-description" class="description">
			<p><strong><?php esc_html_e( 'DNS setup:' ); ?></strong> <?php esc_html_e( 'Subdomains must resolve to your server. A wildcard DNS record is usually used so each new site does not need a separate record.' ); ?></p>
			<p><strong><?php esc_html_e( 'Web server setup:' ); ?></strong> <?php esc_html_e( 'The server must direct requests for the subdomains to this calmPress installation and provide HTTPS, usually with a wildcard certificate.' ); ?></p>
			<p><?php esc_html_e( 'Your server administrator or hosting provider can help with this setup.' ); ?></p>
		</div>
		<?php } ?>
	</fieldset>
	<p class="step"><button type="submit" class="button button-large"><?php esc_html_e( 'Continue installation' ); ?></button></p>
</form>
<script>
( function () {
	const form_element = document.getElementById( 'installation-type' );
	const network_types_element = document.getElementById( 'network-types' );

	/**
	 * Display and enable network address choices when Network is selected.
	 *
	 * @since 1.0.0
	 */
	function display_network_types() {
		const network_selected = form_element.querySelector( '[name="site_type"][value="network"]' ).checked;
		network_types_element.hidden = ! network_selected;
		network_types_element.disabled = ! network_selected;
	}

	form_element.addEventListener( 'change', display_network_types );
	display_network_types();
}() );
</script>
		<?php
		break;

	case 'database':
		load_default_textdomain( $language );
		$GLOBALS['wp_locale'] = new WP_Locale();
		setup_config_display_database_form( wp_unslash( $_POST ), $language );
		break;

	case 'prepare-files':
		load_default_textdomain( $language );
		$GLOBALS['wp_locale'] = new WP_Locale();

		$dbname = trim( wp_unslash( $_POST['dbname'] ) );
		$uname  = trim( wp_unslash( $_POST['uname'] ) );
		$pwd    = trim( wp_unslash( $_POST['pwd'] ) );
		$dbhost = trim( wp_unslash( $_POST['dbhost'] ) );
		$prefix = trim( wp_unslash( $_POST['prefix'] ) );

		$install = 'install.php';
		if ( '' !== $language ) {
			$install = add_query_arg( 'language', $language, $install );
		}

		// Post the settings back for editing rather than placing credentials in a retry URL.
		$database_retry_form = '<form method="post" action="' . esc_url( $database_step_url ) . '">';

		foreach ( [ 'dbname', 'uname', 'pwd', 'dbhost', 'prefix', 'language' ] as $name ) {
			$database_retry_form .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( wp_unslash( $_POST[ $name ] ?? '' ) ) . '">';
		}

		$database_retry_form .= '<p class="step"><button type="submit" class="button button-large">' . esc_html__( 'Try again' ) . '</button></p></form>';
		$tryagain_link        = '</p>' . $database_retry_form;

		if ( empty( $prefix ) ) {
			wp_die( __( '<strong>Error:</strong> "Table Prefix" must not be empty.' ) . $tryagain_link );
		}

		// Validate $prefix: it can only contain letters, numbers and underscores.
		if ( preg_match( '|[^a-z0-9_]|i', $prefix ) ) {
			wp_die( __( '<strong>Error:</strong> "Table Prefix" can only contain numbers, letters, and underscores.' ) . $tryagain_link );
		}

		// Validate $prefix: its length can be at most 25 characters.
		if ( strlen( $prefix ) > 25 ) {
			wp_die( __( '<strong>Error</strong>: "Table Prefix" cannot be longer than 25 characters.' ) . $tryagain_link );
		}

		// Test the DB connection.
		/**#@+
		 *
		 * @ignore
		 */
		define( 'DB_NAME', $dbname );
		define( 'DB_USER', $uname );
		define( 'DB_PASSWORD', $pwd );
		define( 'DB_HOST', $dbhost );
		/**#@-*/
		// Re-construct $wpdb with these new values.
		unset( $wpdb );
		require_wp_db();

		/*
		 * WP_SETUP_CONFIG defers the connection until the submitted settings are available.
		 *
		 * Suppress the normal site's fatal connection and database-selection screens so
		 * installation can display its own error alongside the editable settings.
		 */
		$previous_show_errors = $wpdb->hide_errors();
		$wpdb->db_connect( false );
		$wpdb->show_errors( $previous_show_errors );

		if ( ! $wpdb->ready ) {
			setup_config_display_database_form(
				wp_unslash( $_POST ),
				$language,
				__( 'Could not connect to the selected database. Check the name, username, password, and host, then try again.' )
			);
			break;
		}

		$errors = $wpdb->suppress_errors();
		$wpdb->query( "SELECT $prefix" );
		$wpdb->suppress_errors( $errors );
		if ( ! $wpdb->last_error ) {
			// MySQL was able to parse the prefix as a value, which we don't want. Bail.
			wp_die( __( '<strong>Error:</strong> "Table Prefix" is invalid.' ) . $tryagain_link );
		}

		$create_file = true;
		// Check if user has access to more than one DB.
		if ( isset( $_POST['db_verification'] ) ) {
			if ( db_has_tables( $wpdb ) ) {
				problematic_db_screen( esc_html__( 'The selected database already contains tables. It may be used by another application.'), $wpdb );
				$create_file = false;
			} elseif ( is_broad_db_user( $wpdb ) ) {
				problematic_db_screen( esc_html__( 'The database user has access to multiple databases. This indicates the same credentials may be used across applications.'), $wpdb );
				$create_file = false;
			}
		}

		if ( $create_file ) {
			$filesystem_access = new \calmpress\filesystem\Local_Filesystem_Access();

			if ( isset( $_POST['calm_credentials_protocol'] ) ) {
				$filesystem_access = \calmpress\filesystem\Filesystem_Access_Over_FTP_Or_SFTP::writable_directory_access( wp_unslash( $_POST ), ABSPATH );

				if ( is_wp_error( $filesystem_access ) ) {
					setup_config_display_filesystem_form( wp_unslash( $_POST ), $language, $filesystem_access );
					break;
				}
			}

			$install_type = $_COOKIE['calmpress_install_type'] ?? null;

			if ( ! in_array( $install_type, [ 'standalone', 'network_subdirectory', 'network_subdomain' ], true ) ) {
				throw new UnexpectedValueException( 'Missing or unsupported installation type.' );
			}

			$parts  = wp_parse_url( wp_guess_url() );
			$domain = $parts['host'];
			$path   = '/' . trim( $parts['path'] ?? '', '/' ) . '/';

			if ( '//' === $path ) {
				$path = '/';
			}

			if ( 'network_subdomain' === $install_type && '/' !== $path ) {
				throw new UnexpectedValueException( 'Subdomain networks require installation at the domain root.' );
			}

			// Generate keys and salts using secure CSPRNG.
			$chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_ []{}<>~`+=,.;:/?|';
			$max   = strlen( $chars ) - 1;
			for ( $i = 0; $i < 8; $i++ ) {
				$key = '';
				for ( $j = 0; $j < 64; $j++ ) {
					$key .= substr( $chars, random_int( 0, $max ), 1 );
				}
				$secret_keys[] = $key;
			}

			$key = 0;
			foreach ( $config_file as $line_num => $line ) {
				if ( str_starts_with( $line, '$table_prefix =' ) ) {
					$config_file[ $line_num ] = '$table_prefix = \'' . addcslashes( $prefix, "\\'" ) . "';\r\n";
					continue;
				}

				if ( ! preg_match( '/^define\(\s*\'([A-Z_]+)\',([ ]+)/', $line, $match ) ) {
					continue;
				}

				$constant = $match[1];
				$padding  = $match[2];

				switch ( $constant ) {
					case 'DB_NAME':
					case 'DB_USER':
					case 'DB_PASSWORD':
					case 'DB_HOST':
						$config_file[ $line_num ] = "define( '" . $constant . "'," . $padding . "'" . addcslashes( constant( $constant ), "\\'" ) . "' );\r\n";
						break;
					case 'DB_CHARSET':
						if ( 'utf8mb4' === $wpdb->charset || ( ! $wpdb->charset ) ) {
							$config_file[ $line_num ] = "define( '" . $constant . "'," . $padding . "'utf8mb4' );\r\n";
						}
						break;
					case 'AUTH_KEY':
						// A cookie is set with the value generated for the AUTH_KEY
						// constant. It is going to be used in the install phase to
						// make sure the install is being done by whoever created the
						// wp-config.php file.
						setcookie( 'calmpress_install_auth_key', $secret_keys[ $key ], time() + MONTH_IN_SECONDS );

						// Intentional full through to actually set the value to be
						// stored in wp-config.php.
					case 'SECURE_AUTH_KEY':
					case 'LOGGED_IN_KEY':
					case 'NONCE_KEY':
					case 'AUTH_SALT':
					case 'SECURE_AUTH_SALT':
					case 'LOGGED_IN_SALT':
					case 'NONCE_SALT':
						$config_file[ $line_num ] = "define( '" . $constant . "'," . $padding . "'" . $secret_keys[ $key++ ] . "' );\r\n";
						break;
				}
			}
			unset( $line );

			$config = str_replace( '// NETWORK_SETTINGS_PLACEHOLDER', installation_network_settings( $install_type, $domain, $path ), implode( '', $config_file ) );
			$htaccess = installation_htaccess_contents( $install_type, $path );

			try {
				$file_errors = create_directories_writable_by_web_server( $filesystem_access, WP_CONTENT_DIR, [ '.private', 'uploads' ] );

				if ( ! $file_errors->has_errors() ) {
					$file_errors = create_directory_protected_from_web_server_writes( $filesystem_access, WP_LANG_DIR );
				}

				if ( ! $file_errors->has_errors() ) {
					$file_errors = write_installation_file( $filesystem_access, ABSPATH . '.htaccess', $htaccess );
				}

				if ( ! $file_errors->has_errors() ) {

					// Publish wp-config.php last: its presence advances the next request to site installation.
					$file_errors = write_installation_file( $filesystem_access, ABSPATH . 'wp-config.php', $config );
				}
			} catch ( \calmpress\filesystem\Filesystem_Access_Exception $error ) {
				$file_errors = $error->to_wp_error();
			}

			if ( $file_errors->has_errors() ) {
				setup_config_display_filesystem_form( wp_unslash( $_POST ), $language, $file_errors );
				break;
			}

			clearstatcache( true, ABSPATH . 'wp-config.php' );
			wp_redirect( $install );
			exit;
		}
		break;
} // End of the steps switch.
?>


</body>
</html>
<?php
	ob_end_flush();
