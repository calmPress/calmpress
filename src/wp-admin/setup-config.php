<?php
/**
 * Retrieves and creates the wp-config.php file.
 *
 * The permissions for the base directory must allow for writing files in order
 * for the wp-config.php to be created using this page.
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
	define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/' );
}

ob_start();

require( ABSPATH . 'wp-settings.php' );

/** Load WordPress Administration Upgrade API */
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

/** Load WordPress Translation Installation API */
require_once( ABSPATH . 'wp-admin/includes/translation-install.php' );

nocache_headers();

$config_file = file( ABSPATH . 'wp-admin/wp-config.sample' );

// Check if wp-config.php has been created
if ( file_exists( ABSPATH . 'wp-config.php' ) ) {
	wp_die(
		'<p>' . sprintf(
			/* translators: 1: wp-config.php, 2: install.php */
			__( 'The file %1$s already exists. If you need to reset any of the configuration items in this file, please delete it first. You may try <a href="%2$s">installing now</a>.' ),
			'<code>wp-config.php</code>',
			'install.php'
		) . '</p>'
	);
}

// Check if wp-config.php exists above the root directory but is not part of another installation
if ( @file_exists( ABSPATH . '../wp-config.php' ) && ! @file_exists( ABSPATH . '../wp-settings.php' ) ) {
	wp_die( '<p>' . sprintf(
			/* translators: 1: wp-config.php 2: install.php */
			__( 'The file %1$s already exists one level above your calmPress installation. If you need to reset any of the configuration items in this file, please delete it first. You may try <a href="%2$s">installing now</a>.' ),
			'<code>wp-config.php</code>',
			'install.php'
		) . '</p>'
	);
}

$step = isset( $_GET['step'] ) ? (int) $_GET['step'] : 0;

/**
 * Display setup wp-config.php file header.
 *
 * @ignore
 * @since 2.3.0
 *
 * @global WP_Locale $wp_locale
 *
 * @param string|array $body_classes
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
<html xmlns="http://www.w3.org/1999/xhtml"<?php echo $dir_attr; ?>>
<head>
	<meta name="viewport" content="width=device-width" />
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php _e( 'calmPress &rsaquo; Setup Configuration File' ); ?></title>
	<?php wp_admin_css( 'install', true ); ?>
</head>
<body class="<?php echo implode( ' ', $body_classes ); ?>">
<p id="logo"><a href="<?php echo esc_url( __( 'https://calmpress.org/' ) ); ?>"><?php _e( 'calmPress' ); ?></a></p>
	<?php
} // end function setup_config_display_header();

$language = '';
if ( ! empty( $_REQUEST['language'] ) ) {
	$language = preg_replace( '/[^a-zA-Z0-9_]/', '', $_REQUEST['language'] );
} elseif ( isset( $GLOBALS['wp_local_package'] ) ) {
	$language = $GLOBALS['wp_local_package'];
}

switch ( $step ) {
	case 0:
		setup_config_display_header();
		$step_1 = 'setup-config.php?step=1';
		if ( ! empty( $loaded_language ) ) {
			$step_1 .= '&amp;language=' . $loaded_language;
		}
		?>
<h1 class="screen-reader-text"><?php _e( 'Before getting started' ) ?></h1>
<p><?php _e( 'Welcome to calmPress. Before getting started, we need some information on the database. You will need to know the following items before proceeding.' ) ?></p>
<ol>
	<li><?php _e( 'Database name' ); ?></li>
	<li><?php _e( 'Database username' ); ?></li>
	<li><?php _e( 'Database password' ); ?></li>
</ol>
<p><?php _e( 'If your database is not hosted on the same server on which calmPress will run (most likely it is hosted on it), or you need advanced control, You will also need the following information :' ) ?></p>
<ol>
	<li><?php _e( 'Database host' ); ?></li>
	<li><?php _e( 'Table prefix (if you want to customize table names)' ); ?></li>
</ol>
<p><?php
	/* translators: %s: wp-config.php */
	printf( __( 'We&#8217;re going to use this information to create a %s file.' ),
		'<code>wp-config.php</code>'
	);
	?>
	</p>
<p><?php _e( 'In all likelihood, these items were supplied to you by your Web Host. If you don&#8217;t have this information, then you will need to contact them before you can continue. If you&#8217;re all ready&hellip;' ); ?></p>

<p class="step"><a href="<?php echo $step_1; ?>" class="button button-large"><?php _e( 'Let&#8217;s go!' ); ?></a></p>
		<?php
		break;

	case 1:
		load_default_textdomain( $language );
		$GLOBALS['wp_locale'] = new WP_Locale();

		setup_config_display_header();

		$autofocus = wp_is_mobile() ? '' : ' autofocus';
		?>
<h1 class="screen-reader-text"><?php _e( 'Set up your database connection' ); ?></h1>
<form method="post" action="setup-config.php?step=2">
	<p><?php _e( 'Below you should enter your database connection details. If you&#8217;re not sure about these, contact your host.' ); ?></p>
	<table class="form-table">
		<tr>
			<th scope="row"><label for="dbname"><?php _e( 'Database Name' ); ?></label></th>
			<td><input name="dbname" id="dbname" type="text" size="25" value="" /></td>
			<td><?php _e( 'The name of the database you want to use with calmPress.' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="uname"><?php _e( 'Username' ); ?></label></th>
			<td><input name="uname" id="uname" type="text" size="25" value="" /></td>
			<td><?php _e( 'Your database username.' ); ?></td>
		</tr>
		<tr>
			<th scope="row"><label for="pwd"><?php _e( 'Password' ); ?></label></th>
			<td><input name="pwd" id="pwd" type="text" size="25" value="" autocomplete="off" /></td>
			<td><?php _e( 'Your database password.' ); ?></td>
		</tr>
		<tr class="hide-if-js">
			<th scope="row"><label for="dbhost"><?php _e( 'Database Host' ); ?></label></th>
			<td><input name="dbhost" id="dbhost" type="text" size="25" value="localhost" /></td>
			<td>
			<?php
				/* translators: %s: localhost */
				printf( __( 'You should be able to get this info from your web host, if %s doesn&#8217;t work.' ), '<code>localhost</code>' );
			?>
			</td>
		</tr>
		<tr  class="hide-if-js">
			<th scope="row"><label for="prefix"><?php _e( 'Table Prefix' ); ?></label></th>
			<td><input name="prefix" id="prefix" type="text" value="cp_<?php echo esc_attr( md5( time() ) ); ?>_" size="25" /></td>
			<td><?php _e( 'if you want to customize table names, change this.' ); ?></td>
		</tr>
		<tr class="hide-if-no-js">
			<th></th>
			<td></td>
			<td>
				<button type="button" class="toggle-advanced button" data-toggle-text="<?php echo esc_attr( 'Hide Advanced Settings' ); ?>">
					<?php esc_html_e( 'Show Advanced Settings' ); ?>
				</button>
			</td>
		</tr>
	</table>
	<p class="step"><input name="submit" type="submit" value="<?php echo htmlspecialchars( __( 'Submit' ), ENT_QUOTES ); ?>" class="button button-large" /></p>
</form>
		<?php
		break;

	case 2:
		load_default_textdomain( $language );
		$GLOBALS['wp_locale'] = new WP_Locale();

		$dbname = trim( wp_unslash( $_POST['dbname'] ) );
		$uname  = trim( wp_unslash( $_POST['uname'] ) );
		$pwd    = trim( wp_unslash( $_POST['pwd'] ) );
		$dbhost = trim( wp_unslash( $_POST['dbhost'] ) );
		$prefix = trim( wp_unslash( $_POST['prefix'] ) );

		$step_1  = 'setup-config.php?step=1';
		$install = 'install.php';

		$tryagain_link = '</p><p class="step"><a href="' . $step_1 . '" onclick="javascript:history.go(-1);return false;" class="button button-large">' . __( 'Try again' ) . '</a>';

		if ( empty( $prefix ) ) {
			wp_die( __( '<strong>ERROR</strong>: "Table Prefix" must not be empty.' ) . $tryagain_link );
		}

		// Validate $prefix: it can only contain letters, numbers and underscores.
		if ( preg_match( '|[^a-z0-9_]|i', $prefix ) ) {
			wp_die( __( '<strong>ERROR</strong>: "Table Prefix" can only contain numbers, letters, and underscores.' ) . $tryagain_link );
		}

		// Test the db connection.
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
		* The wpdb constructor bails when WP_SETUP_CONFIG is set, so we must
		* fire this manually. We'll fail here if the values are no good.
		*/
		$wpdb->db_connect();
		if ( ! empty( $wpdb->error ) ) {
			wp_die( $wpdb->error->get_error_message() . $tryagain_link );
		}

		$errors = $wpdb->hide_errors();
		$wpdb->query( "SELECT $prefix" );
		$wpdb->show_errors( $errors );
		if ( ! $wpdb->last_error ) {
			// MySQL was able to parse the prefix as a value, which we don't want. Bail.
			wp_die( __( '<strong>ERROR</strong>: "Table Prefix" is invalid.' ) );
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
			if ( '$table_prefix =' == substr( $line, 0, 15 ) ) {
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
					if ( 'utf8mb4' === $wpdb->charset || ( ! $wpdb->charset && $wpdb->has_cap( 'utf8mb4' ) ) ) {
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

		if ( ! is_writable( ABSPATH ) ) :
			setup_config_display_header();
			?>
	<p>
			<?php
			/* translators: %s: wp-config.php */
			printf( __( 'Sorry, but I can&#8217;t write the %s file.' ), '<code>wp-config.php</code>' );
			?>
</p>
<p>
			<?php
			/* translators: %s: wp-config.php */
			printf( __( 'You can create the %s file manually and paste the following text into it.' ), '<code>wp-config.php</code>' );

			$config_text = '';

			foreach ( $config_file as $line ) {
				$config_text .= htmlentities( $line, ENT_COMPAT, 'UTF-8' );
			}
			?>
</p>
<textarea id="wp-config" cols="98" rows="15" class="code" readonly="readonly"><?php echo $config_text; ?></textarea>
<p><?php _e( 'After you&#8217;ve done that, click &#8220;Run the installation.&#8221;' ); ?></p>
<p class="step"><a href="<?php echo $install; ?>" class="button button-large"><?php _e( 'Run the installation' ); ?></a></p>
<script>
(function(){
if ( ! /iPad|iPod|iPhone/.test( navigator.userAgent ) ) {
	var el = document.getElementById('wp-config');
	el.focus();
	el.select();
}
})();
</script>
			<?php
	else :
		$path_to_wp_config = ABSPATH . 'wp-config.php';

		$handle = fopen( $path_to_wp_config, 'w' );
		foreach ( $config_file as $line ) {
			fwrite( $handle, $line );
		}
		fclose( $handle );
		chmod( $path_to_wp_config, 0666 );
		?>
		<?php
			wp_redirect( $install );
			die();
	endif;
		break;
}
?>
<script type="text/javascript">
var elements = document.querySelectorAll( '.hide-if-no-js' );
elements.forEach( function( item ) {
	item.classList.remove( 'hide-if-no-js' );
});

var elements = document.querySelectorAll( '.hide-if-js' );
elements.forEach( function( item ) {
	item.style.display = 'none';
});

var element = document.querySelector( '.toggle-advanced' );
element.addEventListener('click', function () {
	var elements = document.querySelectorAll( '.hide-if-js' );
	elements.forEach( function( item ) {
		if ( item.style.display == 'none' ) {
	  		item.style.display = 'table-row';
		} else {
			item.style.display = 'none';
		}
	});
	var currentText = element.innerHTML;
	var toggleText  = element.getAttribute( 'data-toggle-text' );
	element.innerHTML = toggleText;
	element.setAttribute( 'data-toggle-text', currentText );
});

</script>
</body>
</html>
<?php
	ob_end_flush();
