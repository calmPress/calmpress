<?php
/**
 * WordPress User Page
 *
 * Handles authentication, registering, resetting passwords, forgot password,
 * and other user handling.
 *
 * @package WordPress
 */

/** Make sure that the WordPress bootstrap has run before continuing. */
require __DIR__ . '/wp-load.php';

/**
 * Output the login page header.
 *
 * @since 2.1.0
 *
 * @global string      $error         Login error message set by deprecated pluggable wp_login() function
 *                                    or plugins replacing it.
 * @global bool|string $interim_login Whether interim login modal is being displayed. String 'success'
 *                                    upon successful login.
 * @global string      $action        The action that brought the visitor to the login page.
 *
 * @param string   $title    Optional. WordPress login Page title to display in the `<title>` element.
 *                           Default 'Log In'.
 * @param string   $message  Optional. Message to display in header. Default empty.
 * @param WP_Error $wp_error Optional. The error to pass. Default is a WP_Error instance.
 */
function login_header( $title = 'Log In', $message = '', $wp_error = null ) {
	global $error, $interim_login, $action;

	// Don't index any of these forms.
	add_filter( 'wp_robots', 'wp_robots_sensitive_page' );
	add_action( 'login_head', 'wp_strict_cross_origin_referrer' );

	add_action( 'login_head', 'wp_login_viewport_meta' );

	if ( ! is_wp_error( $wp_error ) ) {
		$wp_error = new WP_Error();
	}

	// Shake it!
	$shake_error_codes = array( 'empty_password', 'empty_email', 'invalid_email', 'invalidcombo', 'empty_username', 'invalid_username', 'incorrect_password', 'retrieve_password_email_failure' );
	/**
	 * Filters the error codes array for shaking the login form.
	 *
	 * @since 3.0.0
	 *
	 * @param string[] $shake_error_codes Error codes that shake the login form.
	 */
	$shake_error_codes = apply_filters( 'shake_error_codes', $shake_error_codes );

	if ( $shake_error_codes && $wp_error->has_errors() && in_array( $wp_error->get_error_code(), $shake_error_codes, true ) ) {
		add_action( 'login_footer', 'wp_shake_js', 12 );
	}

	$login_title = get_bloginfo( 'name', 'display' );

	/* translators: Login screen title. 1: Login screen name, 2: Network or site name. */
	$login_title = sprintf( __( '%1$s &lsaquo; %2$s &#8212; calmPress' ), $title, $login_title );

	/**
	 * Filters the title tag content for login page.
	 *
	 * @since 4.9.0
	 *
	 * @param string $login_title The page title, with extra context added.
	 * @param string $title       The original page title.
	 */
	$login_title = apply_filters( 'login_title', $login_title, $title );

	?><!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
	<title><?php echo $login_title; ?></title>
	<?php

	wp_enqueue_style( 'login' );

	/*
	 * Remove all stored post data on logging out.
	 * This could be added by add_action('login_head'...) like wp_shake_js(),
	 * but maybe better if it's not removable by plugins.
	 */
	if ( 'loggedout' === $wp_error->get_error_code() ) {
		?>
		<script>if("sessionStorage" in window){try{for(var key in sessionStorage){if(key.indexOf("wp-autosave-")!=-1){sessionStorage.removeItem(key)}}}catch(e){}};</script>
		<?php
	}

	/**
	 * Enqueue scripts and styles for the login page.
	 *
	 * @since 3.1.0
	 */
	do_action( 'login_enqueue_scripts' );

	/**
	 * Fires in the login page header after scripts are enqueued.
	 *
	 * @since 2.1.0
	 */
	do_action( 'login_head' );

	if ( is_multisite() ) {
		$login_header_url   = network_home_url();
		$login_header_title = get_network()->site_name;
	} else {
		$login_header_url   = __( 'https://calmpress.org/' );
		$login_header_title = __( 'Powered by calmPress' );
	}

	/**
	 * Filters link URL of the header logo above login form.
	 *
	 * @since 2.1.0
	 *
	 * @param string $login_header_url Login header logo URL.
	 */
	$login_header_url = apply_filters( 'login_headerurl', $login_header_url );

	$login_header_title = '';

	$login_header_text = empty( $login_header_title ) ? __( 'Powered by calmPress' ) : $login_header_title;

	/**
	 * Filters the link text of the header logo above the login form.
	 *
	 * @since 5.2.0
	 *
	 * @param string $login_header_text The login header logo link text.
	 */
	$login_header_text = apply_filters( 'login_headertext', $login_header_text );

	$classes = array( 'login-action-' . $action, 'wp-core-ui' );

	if ( is_rtl() ) {
		$classes[] = 'rtl';
	}

	if ( $interim_login ) {
		$classes[] = 'interim-login';

		?>
		<style type="text/css">html{background-color: transparent;}</style>
		<?php

		if ( 'success' === $interim_login ) {
			$classes[] = 'interim-login-success';
		}
	}

	$classes[] = ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

	/**
	 * Filters the login page body classes.
	 *
	 * @since 3.5.0
	 *
	 * @param string[] $classes An array of body classes.
	 * @param string   $action  The action that brought the visitor to the login page.
	 */
	$classes = apply_filters( 'login_body_class', $classes, $action );

	?>
	</head>
	<body class="login no-js <?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<script type="text/javascript">
		document.body.className = document.body.className.replace('no-js','js');
	</script>
	<?php
	/**
	 * Fires in the login page header after the body tag is opened.
	 *
	 * @since 4.6.0
	 */
	do_action( 'login_header' );

	?>
	<div id="login">
		<h1><a href="<?php echo esc_url( $login_header_url ); ?>"><?php echo $login_header_text; ?></a></h1>
	<?php
	/**
	 * Filters the message to display above the login form.
	 *
	 * @since 2.1.0
	 *
	 * @param string $message Login message text.
	 */
	$message = apply_filters( 'login_message', $message );

	if ( ! empty( $message ) ) {
		echo $message . "\n";
	}

	// In case a plugin uses $error rather than the $wp_errors object.
	if ( ! empty( $error ) ) {
		$wp_error->add( 'error', $error );
		unset( $error );
	}

	if ( $wp_error->has_errors() ) {
		$errors   = '';
		$messages = '';

		foreach ( $wp_error->get_error_codes() as $code ) {
			$severity = $wp_error->get_error_data( $code );
			foreach ( $wp_error->get_error_messages( $code ) as $error_message ) {
				if ( 'message' === $severity ) {
					$messages .= '	' . $error_message . "<br />\n";
				} else {
					$errors .= '	' . $error_message . "<br />\n";
				}
			}
		}

		if ( ! empty( $errors ) ) {
			/**
			 * Filters the error messages displayed above the login form.
			 *
			 * @since 2.1.0
			 *
			 * @param string $errors Login error message.
			 */
			echo '<div id="login_error">' . apply_filters( 'login_errors', $errors ) . "</div>\n";
		}

		if ( ! empty( $messages ) ) {
			/**
			 * Filters instructional messages displayed above the login form.
			 *
			 * @since 2.5.0
			 *
			 * @param string $messages Login messages.
			 */
			echo '<p class="message">' . apply_filters( 'login_messages', $messages ) . "</p>\n";
		}
	}
} // End of login_header().

/**
 * Outputs the footer for the login page.
 *
 * @since 3.1.0
 *
 * @global bool|string $interim_login Whether interim login modal is being displayed. String 'success'
 *                                    upon successful login.
 *
 * @param string $input_id Which input to auto-focus.
 */
function login_footer( $input_id = '' ) {
	global $interim_login;

	// Don't allow interim logins to navigate away from the page.
	if ( ! $interim_login ) {
		?>
		<p id="backtoblog">
			<?php
			$html_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( home_url( '/' ) ),
				sprintf(
					/* translators: %s: Site title. */
					_x( '&larr; Go to %s', 'site' ),
					get_bloginfo( 'title', 'display' )
				)
			);
			/**
			 * Filter the "Go to site" link displayed in the login page footer.
			 *
			 * @since 5.7.0
			 *
			 * @param string $link HTML link to the home URL of the current site.
			 */
			echo apply_filters( 'login_site_html_link', $html_link );
			?>
		</p>
		<?php

		the_privacy_policy_link( '<div class="privacy-policy-page-link">', '</div>' );
	}

	?>
	</div><?php // End of <div id="login">. ?>

	<?php
	if (
		! $interim_login &&
		/**
		 * Filters the Languages select input activation on the login screen.
		 *
		 * @since 5.9.0
		 *
		 * @param bool Whether to display the Languages select input on the login screen.
		 */
		apply_filters( 'login_display_language_dropdown', true )
	) {
		$languages = get_available_languages();

		if ( ! empty( $languages ) ) {
			?>
			<div class="language-switcher">
				<form id="language-switcher" action="" method="get">

					<label for="language-switcher-locales">
						<span class="dashicons dashicons-translation" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php _e( 'Language' ); ?></span>
					</label>

					<?php
					$args = array(
						'id'                          => 'language-switcher-locales',
						'name'                        => 'wp_lang',
						'selected'                    => determine_locale(),
						'show_available_translations' => false,
						'explicit_option_en_us'       => true,
						'languages'                   => $languages,
					);

					/**
					 * Filters default arguments for the Languages select input on the login screen.
					 *
					 * @since 5.9.0
					 *
					 * @param array $args Arguments for the Languages select input on the login screen.
					 */
					wp_dropdown_languages( apply_filters( 'login_language_dropdown_args', $args ) );
					?>

					<?php if ( $interim_login ) { ?>
						<input type="hidden" name="interim-login" value="1" />
					<?php } ?>

					<?php if ( isset( $_GET['redirect_to'] ) && '' !== $_GET['redirect_to'] ) { ?>
						<input type="hidden" name="redirect_to" value="<?php echo esc_url_raw( $_GET['redirect_to'] ); ?>" />
					<?php } ?>

					<?php if ( isset( $_GET['action'] ) && '' !== $_GET['action'] ) { ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( $_GET['action'] ); ?>" />
					<?php } ?>

						<input type="submit" class="button" value="<?php esc_attr_e( 'Change' ); ?>">

					</form>
				</div>
		<?php } ?>
	<?php } ?>
	<?php

	if ( ! empty( $input_id ) ) {
		?>
		<script type="text/javascript">
		try{document.getElementById('<?php echo $input_id; ?>').focus();}catch(e){}
		if(typeof wpOnload==='function')wpOnload();
		</script>
		<?php
	}

	/**
	 * Fires in the login page footer.
	 *
	 * @since 3.1.0
	 */
	do_action( 'login_footer' );

	?>
	<div class="clear"></div>
	</body>
	</html>
	<?php
}

/**
 * Outputs the JavaScript to handle the form shaking on the login page.
 *
 * @since 3.0.0
 */
function wp_shake_js() {
	?>
	<script type="text/javascript">
	document.querySelector('form').classList.add('shake');
	</script>
	<?php
}

/**
 * Outputs the viewport meta tag for the login page.
 *
 * @since 3.7.0
 */
function wp_login_viewport_meta() {
	?>
	<meta name="viewport" content="width=device-width" />
	<?php
}

/**
 * Handles sending password retrieval email to user in case an attempt was made to register
 * another email with the user.
 *
 * @since calmPress 1.0
 *
 * @param string $user_email The email of the user to which to send the password reset.
 */
function login_retrieve_password( $user_email ) {

	$user_data = get_user_by( 'email', $user_email );

	$key = get_password_reset_key( $user_data );

	if ( is_multisite() ) {
		$site_name = get_network()->site_name;
	} else {
		/*
		 * The blogname option is escaped with esc_html on the way into the database
		 * in sanitize_option we want to reverse this for the plain text arena of emails.
		 */
		$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	}

	$message = __( 'Someone has tried to create a new user with this email. If it was you and you just forgot your password, a password reset instructions are added:' ) . "\r\n\r\n";
	/* translators: %s: site name */
	$message .= sprintf( __( 'Site Name: %s'), $site_name ) . "\r\n\r\n";
	/* translators: %s: user Email */
	$message .= sprintf( __( 'Email: %s'), $user_email ) . "\r\n\r\n";
	$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
	$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
	$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&email=" . rawurlencode( $user_email ), 'user_email' ) . ">\r\n";

	/* translators: Password reset email subject. %s: Site name */
	$title = sprintf( __( '[%s] User creation' ), $site_name );

	wp_mail( $user_email, wp_specialchars_decode( $title ), $message );
}

//
// Main.
//

$action = isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'login';
$errors = new WP_Error();

if ( isset( $_GET['key'] ) ) {
	$action = 'resetpass';
}

if ( isset( $_GET['checkemail'] ) ) {
	$action = 'checkemail';
}

$default_actions = array(
	'postpass',
	'logout',
	'lostpassword',
	'retrievepassword',
	'resetpass',
	'rp',
	'register',
	'checkemail',
	'confirmaction',
	'login',
);

// Validate action so as to default to the login screen.
if ( ! in_array( $action, $default_actions, true ) && false === has_filter( 'login_form_' . $action ) ) {
	$action = 'login';
}

nocache_headers();

header( 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) );

// Set a cookie now to see if they are supported by the browser.
$secure = ( 'https' === parse_url( wp_login_url(), PHP_URL_SCHEME ) );
setcookie( TEST_COOKIE, 'WP Cookie check', 0, COOKIEPATH, COOKIE_DOMAIN, $secure );

if ( SITECOOKIEPATH !== COOKIEPATH ) {
	setcookie( TEST_COOKIE, 'WP Cookie check', 0, SITECOOKIEPATH, COOKIE_DOMAIN, $secure );
}

if ( isset( $_GET['wp_lang'] ) ) {
	setcookie( 'wp_lang', sanitize_text_field( $_GET['wp_lang'] ), 0, COOKIEPATH, COOKIE_DOMAIN, $secure );
}

/**
 * Fires when the login form is initialized.
 *
 * @since 3.2.0
 */
do_action( 'login_init' );

/**
 * Fires before a specified login form action.
 *
 * The dynamic portion of the hook name, `$action`, refers to the action
 * that brought the visitor to the login form.
 *
 * Possible hook names include:
 *
 *  - `login_form_checkemail`
 *  - `login_form_confirm_admin_email`
 *  - `login_form_confirmaction`
 *  - `login_form_login`
 *  - `login_form_logout`
 *  - `login_form_lostpassword`
  *  - `login_form_register`
 *  - `login_form_resetpass`
 *  - `login_form_retrievepassword`
 *  - `login_form_rp`
 *
 * @since 2.8.0
 */
do_action( "login_form_{$action}" );

$http_post     = ( 'POST' === $_SERVER['REQUEST_METHOD'] );
$interim_login = isset( $_REQUEST['interim-login'] );

/**
 * Filters the separator used between login form navigation links.
 *
 * @since 4.9.0
 *
 * @param string $login_link_separator The separator used between login form navigation links.
 */
$login_link_separator = apply_filters( 'login_link_separator', ' | ' );

switch ( $action ) {

	case 'logout':
		check_admin_referer( 'log-out' );

		$user = wp_get_current_user();

		wp_logout();

		if ( ! empty( $_REQUEST['redirect_to'] ) ) {
			$redirect_to           = $_REQUEST['redirect_to'];
			$requested_redirect_to = $redirect_to;
		} else {
			$redirect_to = add_query_arg(
				array(
					'loggedout' => 'true',
					'wp_lang'   => get_user_locale( $user ),
				),
				wp_login_url()
			);

			$requested_redirect_to = '';
		}

		/**
		 * Filters the log out redirect URL.
		 *
		 * @since 4.2.0
		 *
		 * @param string  $redirect_to           The redirect destination URL.
		 * @param string  $requested_redirect_to The requested redirect destination URL passed as a parameter.
		 * @param WP_User $user                  The WP_User object for the user that's logging out.
		 */
		$redirect_to = apply_filters( 'logout_redirect', $redirect_to, $requested_redirect_to, $user );

		wp_safe_redirect( $redirect_to );
		exit;

	case 'lostpassword':
	case 'retrievepassword':
		if ( $http_post ) {
			$errors = retrieve_password();

			if ( ! is_wp_error( $errors ) ) {
				$redirect_to = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : 'wp-login.php?checkemail=confirm';
				wp_safe_redirect( $redirect_to );
				exit;
			}
		}

		if ( isset( $_GET['error'] ) ) {
			if ( 'invalidkey' === $_GET['error'] ) {
				$errors->add( 'invalidkey', __( '<strong>Error</strong>: Your password reset link appears to be invalid. Please request a new link below.' ) );
			} elseif ( 'expiredkey' === $_GET['error'] ) {
				$errors->add( 'expiredkey', __( '<strong>Error</strong>: Your password reset link has expired. Please request a new link below.' ) );
			}
		}

		$lostpassword_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
		/**
		 * Filters the URL redirected to after submitting the lostpassword/retrievepassword form.
		 *
		 * @since 3.0.0
		 *
		 * @param string $lostpassword_redirect The redirect destination URL.
		 */
		$redirect_to = apply_filters( 'lostpassword_redirect', $lostpassword_redirect );

		/**
		 * Fires before the lost password form.
		 *
		 * @since 1.5.1
		 * @since 5.1.0 Added the `$errors` parameter.
		 *
		 * @param WP_Error $errors A `WP_Error` object containing any errors generated by using invalid
		 *                         credentials. Note that the error object may not contain any errors.
		 */
		do_action( 'lost_password', $errors );

		login_header( __( 'Lost Password' ), '<p class="message">' . __( 'Please enter your email address. You will receive an email message with instructions on how to reset your password.' ) . '</p>', $errors );

		$user_login = '';

		if ( isset( $_POST['user_login'] ) && is_string( $_POST['user_login'] ) ) {
			$user_login = wp_unslash( $_POST['user_login'] );
		}

		?>

		<form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=lostpassword', 'login_post' ) ); ?>" method="post">
			<p>
				<label for="user_login"><?php _e( 'Email Address' ); ?></label>
				<input type="text" name="user_login" id="user_login" class="input" value="<?php echo esc_attr( $user_login ); ?>" size="20" autocapitalize="off" />
			</p>
			<?php

			/**
			 * Fires inside the lostpassword form tags, before the hidden fields.
			 *
			 * @since 2.1.0
			 */
			do_action( 'lostpassword_form' );

			?>
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Get New Password' ); ?>" />
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
			<?php

			if ( get_option( 'users_can_register' ) ) {
				$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );

				echo esc_html( $login_link_separator );

				/** This filter is documented in wp-includes/general-template.php */
				echo apply_filters( 'register', $registration_url );
			}

			?>
		</p>
		<?php

		login_footer( 'user_login' );
		break;

	case 'resetpass':
	case 'rp':
		list( $rp_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$rp_cookie       = 'wp-resetpass-' . COOKIEHASH;

		if ( isset( $_GET['key'] ) && isset( $_GET['email'] ) ) {
			$value = sprintf( '%s:%s', wp_unslash( $_GET['email'] ), wp_unslash( $_GET['key'] ) );
			setcookie( $rp_cookie, $value, 0, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

			wp_safe_redirect( remove_query_arg( array( 'key', 'email' ) ) );
			exit;
		}

		if ( isset( $_COOKIE[ $rp_cookie ] ) && 0 < strpos( $_COOKIE[ $rp_cookie ], ':' ) ) {
			list( $rp_email, $rp_key ) = explode( ':', wp_unslash( $_COOKIE[ $rp_cookie ] ), 2 );

			$user = check_password_reset_key( $rp_key, $rp_email );

			if ( isset( $_POST['pass1'] ) && ! hash_equals( $rp_key, $_POST['rp_key'] ) ) {
				$user = false;
			}
		} else {
			$user = false;
		}

		if ( ! $user || is_wp_error( $user ) ) {
			setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );

			if ( $user && $user->get_error_code() === 'expired_key' ) {
				wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=expiredkey' ) );
			} else {
				wp_redirect( site_url( 'wp-login.php?action=lostpassword&error=invalidkey' ) );
			}

			exit;
		}

		$errors = new WP_Error();

		if ( isset( $_POST['pass1'] ) && $_POST['pass1'] !== $_POST['pass2'] ) {
			$errors->add( 'password_reset_mismatch', __( '<strong>Error</strong>: The passwords do not match.' ) );
		}

		/**
		 * Fires before the password reset procedure is validated.
		 *
		 * @since 3.5.0
		 *
		 * @param WP_Error         $errors WP Error object.
		 * @param WP_User|WP_Error $user   WP_User object if the login and reset key match. WP_Error object otherwise.
		 */
		do_action( 'validate_password_reset', $errors, $user );

		if ( ( ! $errors->has_errors() ) && isset( $_POST['pass1'] ) && ! empty( $_POST['pass1'] ) ) {
			reset_password( $user, $_POST['pass1'] );
			setcookie( $rp_cookie, ' ', time() - YEAR_IN_SECONDS, $rp_path, COOKIE_DOMAIN, is_ssl(), true );
			login_header( __( 'Password Reset' ), '<p class="message reset-pass">' . __( 'Your password has been reset.' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . __( 'Log in' ) . '</a></p>' );
			login_footer();
			exit;
		}

		wp_enqueue_script( 'utils' );
		wp_enqueue_script( 'user-profile' );

		login_header( __( 'Reset Password' ), '<p class="message reset-pass">' . __( 'Enter your new password below or generate one.' ) . '</p>', $errors );

		?>
		<form name="resetpassform" id="resetpassform" action="<?php echo esc_url( network_site_url( 'wp-login.php?action=resetpass', 'login_post' ) ); ?>" method="post" autocomplete="off">
			<p>
				<label for="user_login"><?php _e( 'Email Address' ); ?></label>
				<input type="text" name="log" id="user_login" class="input" value="<?php echo esc_attr( $rp_email ); ?>" readonly="readonly" autocapitalize="off" />
			</p>

			<div class="user-pass1-wrap">
				<p>
					<label for="pass1"><?php _e( 'New password' ); ?></label>
				</p>

				<div class="wp-pwd">
					<input type="password" data-reveal="1" data-pw="<?php echo esc_attr( wp_generate_password( 16 ) ); ?>" name="pass1" id="pass1" class="input password-input" size="24" value="" autocomplete="off" aria-describedby="pass-strength-result" />

					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Hide password' ); ?>">
						<span class="dashicons dashicons-hidden" aria-hidden="true"></span>
					</button>
					<div id="pass-strength-result" class="hide-if-no-js" aria-live="polite"><?php _e( 'Strength indicator' ); ?></div>
				</div>
				<div class="pw-weak">
					<input type="checkbox" name="pw_weak" id="pw-weak" class="pw-checkbox" />
					<label for="pw-weak"><?php _e( 'Confirm use of weak password' ); ?></label>
				</div>
			</div>

			<p class="user-pass2-wrap">
				<label for="pass2"><?php _e( 'Confirm new password' ); ?></label>
				<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="off" />
			</p>

			<p class="description indicator-hint"><?php echo wp_get_password_hint(); ?></p>
			<br class="clear" />

			<?php

			/**
			 * Fires following the 'Strength indicator' meter in the user password reset form.
			 *
			 * @since 3.9.0
			 *
			 * @param WP_User $user User object of the user whose password is being reset.
			 */
			do_action( 'resetpass_form', $user );

			?>
			<input type="hidden" name="rp_key" value="<?php echo esc_attr( $rp_key ); ?>" />
			<p class="submit reset-pass-submit">
				<button type="button" class="button wp-generate-pw hide-if-no-js" aria-expanded="true"><?php _e( 'Generate Password' ); ?></button>
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Password' ); ?>" />
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
			<?php

			if ( get_option( 'users_can_register' ) ) {
				$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );

				echo esc_html( $login_link_separator );

				/** This filter is documented in wp-includes/general-template.php */
				echo apply_filters( 'register', $registration_url );
			}

			?>
		</p>
		<?php

		login_footer( 'pass1' );
		break;

	case 'register':
		if ( is_multisite() ) {
			/**
			 * Filters the Multisite sign up URL.
			 *
			 * @since 3.0.0
			 *
			 * @param string $sign_up_url The sign up URL.
			 */
			wp_redirect( apply_filters( 'wp_signup_location', network_site_url( 'wp-signup.php' ) ) );
			exit;
		}

		if ( ! get_option( 'users_can_register' ) ) {
			wp_redirect( site_url( 'wp-login.php?registration=disabled' ) );
			exit;
		}

		$user_email = '';

		if ( $http_post ) {

			if ( isset( $_POST['user_email'] ) && is_string( $_POST['user_email'] ) ) {
				$user_email = wp_unslash( $_POST['user_email'] );
			}
		}

		$errors = register_new_user( md5( $user_email ), $user_email );
		if ( ! is_wp_error( $errors ) ) {
			$redirect_to = !empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : 'wp-login.php?checkemail=registered';
			wp_safe_redirect( $redirect_to );
			exit();
		} elseif ( email_exists( $user_email ) ) {
			// We got an error because someone tried to register with an email address of an existing user.
			// In that case send an email with a link to password reset.
			login_retrieve_password( $user_email );
			$redirect_to = !empty( $_POST['redirect_to'] ) ? $_POST['redirect_to'] : 'wp-login.php?checkemail=registered';
			wp_safe_redirect( $redirect_to );
			exit();
		}

		$registration_redirect = ! empty( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';

		/**
		 * Filters the registration redirect URL.
		 *
		 * @since 3.0.0
		 * @since 5.9.0 Added the `$errors` parameter.
		 *
		 * @param string       $registration_redirect The redirect destination URL.
		 * @param int|WP_Error $errors                User id if registration was successful,
		 *                                            WP_Error object otherwise.
		 */
		$redirect_to = apply_filters( 'registration_redirect', $registration_redirect, $errors );

		login_header( __( 'Registration Form' ), '<p class="message register">' . __( 'Register For This Site' ) . '</p>', $errors );

		?>
		<form name="registerform" id="registerform" action="<?php echo esc_url( site_url( 'wp-login.php?action=register', 'login_post' ) ); ?>" method="post" novalidate="novalidate">
		<p>
			<label for="user_email"><?php _e('Email') ?><br />
			<input type="email" name="user_email" id="user_email" class="input" value="<?php echo esc_attr( wp_unslash( $user_email ) ); ?>" size="25" /></label>
		</p>
			<?php
			/**
			 * Fires following the 'Email' field in the user registration form.
			 *
			 * @since 2.1.0
			 */
			do_action( 'register_form' );
			?>

			<p id="reg_passmail">
				<?php _e( 'Registration confirmation will be emailed to you.' ); ?>
			</p>
			<br class="clear" />
			<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Register' ); ?>" />
			</p>
		</form>

		<p id="nav">
			<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php _e( 'Log in' ); ?></a>
				<?php echo esc_html( $login_link_separator ); ?>
			<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php _e( 'Lost your password?' ); ?></a>
		</p>
		<?php

		login_footer( 'user_login' );
		break;

	case 'checkemail':
		$redirect_to = admin_url();
		$errors      = new WP_Error();

		if ( 'confirm' === $_GET['checkemail'] ) {
			$errors->add(
				'confirm',
				sprintf(
					/* translators: %s: Link to the login page. */
					__( 'Check your email for the confirmation link, then visit the <a href="%s">login page</a>.' ),
					wp_login_url()
				),
				'message'
			);
		} elseif ( 'registered' === $_GET['checkemail'] ) {
			$errors->add(
				'registered',
				sprintf(
					/* translators: %s: Link to the login page. */
					__( 'Registration complete. Please check your email, then visit the <a href="%s">login page</a>.' ),
					wp_login_url()
				),
				'message'
			);
		}

		/** This action is documented in wp-login.php */
		$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

		login_header( __( 'Check your email' ), '', $errors );
		login_footer();
		break;

	case 'confirmaction':
		if ( ! isset( $_GET['request_id'] ) ) {
			wp_die( __( 'Missing request ID.' ) );
		}

		if ( ! isset( $_GET['confirm_key'] ) ) {
			wp_die( __( 'Missing confirm key.' ) );
		}

		$request_id = (int) $_GET['request_id'];
		$key        = sanitize_text_field( wp_unslash( $_GET['confirm_key'] ) );
		$result     = wp_validate_user_request_key( $request_id, $key );

		if ( is_wp_error( $result ) ) {
			wp_die( $result );
		}

		/**
		 * Fires an action hook when the account action has been confirmed by the user.
		 *
		 * Using this you can assume the user has agreed to perform the action by
		 * clicking on the link in the confirmation email.
		 *
		 * After firing this action hook the page will redirect to wp-login a callback
		 * redirects or exits first.
		 *
		 * @since 4.9.6
		 *
		 * @param int $request_id Request ID.
		 */
		do_action( 'user_request_action_confirmed', $request_id );

		$message = _wp_privacy_account_request_confirmed_message( $request_id );

		login_header( __( 'User action confirmed.' ), $message );
		login_footer();
		exit;

	case 'login':
	default:
		$secure_cookie   = '';
		$customize_login = isset( $_REQUEST['customize-login'] );

		if ( $customize_login ) {
			wp_enqueue_script( 'customize-base' );
		}

		if ( isset( $_REQUEST['redirect_to'] ) ) {
			$redirect_to = $_REQUEST['redirect_to'];
			// Redirect to HTTPS if user wants SSL.
			if ( $secure_cookie && false !== strpos( $redirect_to, 'wp-admin' ) ) {
				$redirect_to = preg_replace( '|^http://|', 'https://', $redirect_to );
			}
		} else {
			$redirect_to = admin_url();
		}

		$reauth = empty( $_REQUEST['reauth'] ) ? false : true;

		$user = wp_signon( array(), $secure_cookie );

		if ( empty( $_COOKIE[ LOGGED_IN_COOKIE ] ) ) {
			if ( headers_sent() ) {
				$user = new WP_Error(
					'test_cookie',
					sprintf(
						/* translators: 1: Browser cookie documentation URL, 2: Support forums URL. */
						__( '<strong>Error</strong>: Cookies are blocked due to unexpected output. For help, please see <a href="%1$s">this documentation</a> or try the <a href="%2$s">support forums</a>.' ),
						__( 'https://wordpress.org/support/article/cookies/' ),
						__( 'https://wordpress.org/support/forums/' )
					)
				);
			} elseif ( isset( $_POST['testcookie'] ) && empty( $_COOKIE[ TEST_COOKIE ] ) ) {
				// If cookies are disabled, we can't log in even with a valid user and password.
				$user = new WP_Error(
					'test_cookie',
					sprintf(
						/* translators: %s: Browser cookie documentation URL. */
						__( '<strong>Error</strong>: Cookies are blocked or not supported by your browser. You must <a href="%s">enable cookies</a> to use WordPress.' ),
						__( 'https://wordpress.org/support/article/cookies/#enable-cookies-in-your-browser' )
					)
				);
			}
		}

		$requested_redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : '';
		/**
		 * Filters the login redirect URL.
		 *
		 * @since 3.0.0
		 *
		 * @param string           $redirect_to           The redirect destination URL.
		 * @param string           $requested_redirect_to The requested redirect destination URL passed as a parameter.
		 * @param WP_User|WP_Error $user                  WP_User object if login was successful, WP_Error object otherwise.
		 */
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );

		if ( ! is_wp_error( $user ) && ! $reauth ) {
			if ( $interim_login ) {
				$message       = '<p class="message">' . __( 'You have logged in successfully.' ) . '</p>';
				$interim_login = 'success';
				login_header( '', $message );

				?>
				</div>
				<?php

				/** This action is documented in wp-login.php */
				do_action( 'login_footer' );

				if ( $customize_login ) {
					?>
					<script type="text/javascript">setTimeout( function(){ new wp.customize.Messenger({ url: '<?php echo wp_customize_url(); ?>', channel: 'login' }).send('login') }, 1000 );</script>
					<?php
				}

				?>
				</body></html>
				<?php

				exit;
			}

			if ( ( empty( $redirect_to ) || 'wp-admin/' === $redirect_to || admin_url() === $redirect_to ) ) {
				// If the user doesn't belong to a blog, send them to user admin. If the user can't edit posts, send them to their profile.
				if ( is_multisite() && ! get_active_blog_for_user( $user->ID ) && ! is_super_admin( $user->ID ) ) {
					$redirect_to = user_admin_url();
				} elseif ( is_multisite() && ! $user->has_cap( 'read' ) ) {
					$redirect_to = get_dashboard_url( $user->ID );
				} elseif ( ! $user->has_cap( 'edit_posts' ) ) {
					$redirect_to = $user->has_cap( 'read' ) ? admin_url( 'profile.php' ) : home_url();
				}

				wp_redirect( $redirect_to );
				exit;
			}

			wp_safe_redirect( $redirect_to );
			exit;
		}

		$errors = $user;
		// Clear errors if loggedout is set.
		if ( ! empty( $_GET['loggedout'] ) || $reauth ) {
			$errors = new WP_Error();
		}

		if ( empty( $_POST ) && $errors->get_error_codes() === array( 'empty_username', 'empty_password' ) ) {
			$errors = new WP_Error( '', '' );
		}

		if ( $interim_login ) {
			if ( ! $errors->has_errors() ) {
				$errors->add( 'expired', __( 'Your session has expired. Please log in to continue where you left off.' ), 'message' );
			}
		} else {
			// Some parts of this script use the main login form to display a message.
			if ( isset( $_GET['loggedout'] ) && $_GET['loggedout'] ) {
				$errors->add( 'loggedout', __( 'You are now logged out.' ), 'message' );
			} elseif ( isset( $_GET['registration'] ) && 'disabled' === $_GET['registration'] ) {
				$errors->add( 'registerdisabled', __( 'User registration is currently not allowed.' ) );
			} elseif ( isset( $_GET['checkemail'] ) && 'confirm' === $_GET['checkemail'] ) {
				$errors->add( 'confirm', __( 'Check your email for the confirmation link.' ), 'message' );
			} elseif ( isset( $_GET['checkemail'] ) && 'newpass' === $_GET['checkemail'] ) {
				$errors->add( 'newpass', __( 'Check your email for your new password.' ), 'message' );
			} elseif ( isset( $_GET['checkemail'] ) && 'registered' === $_GET['checkemail'] ) {
				$errors->add( 'registered', __( 'Registration complete. Please check your email.' ), 'message' );
			} elseif ( isset( $_GET['redirect_to'] ) && false !== strpos( $_GET['redirect_to'], 'wp-admin/authorize-application.php' ) ) {
				$query_component = wp_parse_url( $_GET['redirect_to'], PHP_URL_QUERY );
				$query           = array();
				if ( $query_component ) {
					parse_str( $query_component, $query );
				}

				if ( ! empty( $query['app_name'] ) ) {
					/* translators: 1: Website name, 2: Application name. */
					$message = sprintf( 'Please log in to %1$s to authorize %2$s to connect to your account.', get_bloginfo( 'name', 'display' ), '<strong>' . esc_html( $query['app_name'] ) . '</strong>' );
				} else {
					/* translators: %s: Website name. */
					$message = sprintf( 'Please log in to %s to proceed with authorization.', get_bloginfo( 'name', 'display' ) );
				}

				$errors->add( 'authorize_application', $message, 'message' );
			}
		}

		/**
		 * Filters the login page errors.
		 *
		 * @since 3.6.0
		 *
		 * @param WP_Error $errors      WP Error object.
		 * @param string   $redirect_to Redirect destination URL.
		 */
		$errors = apply_filters( 'wp_login_errors', $errors, $redirect_to );

		// Clear any stale cookies.
		if ( $reauth ) {
			wp_clear_auth_cookie();
		}

		login_header( __( 'Log In' ), '', $errors );

		if ( isset( $_POST['log'] ) ) {
			$user_login = ( 'incorrect_password' === $errors->get_error_code() || 'empty_password' === $errors->get_error_code() ) ? esc_attr( wp_unslash( $_POST['log'] ) ) : '';
		}

		$rememberme = ! empty( $_POST['rememberme'] );

		if ( $errors->has_errors() ) {
			$aria_describedby_error = ' aria-describedby="login_error"';
		} else {
			$aria_describedby_error = '';
		}

		wp_enqueue_script( 'user-profile' );
		?>

		<form name="loginform" id="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post">
			<p>
				<label for="user_login"><?php _e( 'Email Address' ); ?></label>
				<input type="text" name="log" id="user_login" <?php echo $aria_describedby_error; ?> class="input" value="<?php echo esc_attr( $user_login ); ?>" size="20" autocapitalize="off" />
			</p>

			<div class="user-pass-wrap">
				<label for="user_pass"><?php _e( 'Password' ); ?></label>
				<div class="wp-pwd">
					<input type="password" name="pwd" id="user_pass"<?php echo $aria_describedby_error; ?> class="input password-input" value="" size="20" />
					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" data-toggle="0" aria-label="<?php esc_attr_e( 'Show password' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
				</div>
			</div>
			<?php

			/**
			 * Fires following the 'Password' field in the login form.
			 *
			 * @since 2.1.0
			 */
			do_action( 'login_form' );

			?>
			<p class="forgetmenot"><input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php checked( $rememberme ); ?> /> <label for="rememberme"><?php esc_html_e( 'Remember Me' ); ?></label></p>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Log In' ); ?>" />
				<?php

				if ( $interim_login ) {
					?>
					<input type="hidden" name="interim-login" value="1" />
					<?php
				} else {
					?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $redirect_to ); ?>" />
					<?php
				}

				if ( $customize_login ) {
					?>
					<input type="hidden" name="customize-login" value="1" />
					<?php
				}

				?>
				<input type="hidden" name="testcookie" value="1" />
			</p>
		</form>

		<?php

		if ( ! $interim_login ) {
			?>
			<p id="nav">
				<?php

				if ( get_option( 'users_can_register' ) ) {
					$registration_url = sprintf( '<a href="%s">%s</a>', esc_url( wp_registration_url() ), __( 'Register' ) );

					/** This filter is documented in wp-includes/general-template.php */
					echo apply_filters( 'register', $registration_url );

					echo esc_html( $login_link_separator );
				}

				?>
				<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php _e( 'Lost your password?' ); ?></a>
			</p>
			<?php
		}

		$login_script  = 'function wp_attempt_focus() {';
		$login_script .= 'setTimeout( function() {';
		$login_script .= 'try {';

		if ( $user_login ) {
			$login_script .= 'd = document.getElementById( "user_pass" ); d.value = "";';
		} else {
			$login_script .= 'd = document.getElementById( "user_login" );';

			if ( $errors->get_error_code() === 'invalid_username' ) {
				$login_script .= 'd.value = "";';
			}
		}

		$login_script .= 'd.focus(); d.select();';
		$login_script .= '} catch( er ) {}';
		$login_script .= '}, 200);';
		$login_script .= "}\n"; // End of wp_attempt_focus().

		/**
		 * Filters whether to print the call to `wp_attempt_focus()` on the login screen.
		 *
		 * @since 4.8.0
		 *
		 * @param bool $print Whether to print the function call. Default true.
		 */
		if ( apply_filters( 'enable_login_autofocus', true ) && ! $error ) {
			$login_script .= "wp_attempt_focus();\n";
		}

		// Run `wpOnload()` if defined.
		$login_script .= "if ( typeof wpOnload === 'function' ) { wpOnload() }";

		?>
		<script type="text/javascript">
			<?php echo $login_script; ?>
		</script>
		<?php

		if ( $interim_login ) {
			?>
			<script type="text/javascript">
			( function() {
				try {
					var i, links = document.getElementsByTagName( 'a' );
					for ( i in links ) {
						if ( links[i].href ) {
							links[i].target = '_blank';
							links[i].rel = 'noopener';
						}
					}
				} catch( er ) {}
			}());
			</script>
			<?php
		}

		login_footer();
		break;
} // End action switch.
