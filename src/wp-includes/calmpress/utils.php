<?php
/**
 * Utility functions used by calmPress code.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\utils;

/**
 * The message text of the last error reported.
 *
 * A sweetener over error_get_last.
 *
 * @return string The message, or empty string if no error was repoted. 
 */
function last_error_message(): string {
	$error = error_get_last();
	
	if ( null === $error ) {
		return '';
	}

	return $error['message'];
}

/**
 * Verify that a writable directory exists at a specific path,
 * and if does not try to create a one.
 *
 * @param string $path The absolute path of the directory.
 *
 * @throws \RuntimeException If path exists but it is not a directory (exception code is 2),
 *                    path exists but it is not a writable (exception code 3),
 *                    or if directory creation fails (exception code is 1).
 */
function ensure_dir_exists( string $path ) {

	if ( ! file_exists( $path ) ) {
		$res = @mkdir( $path, 0755, true );
		if ( ! $res ) {
			throw new \RuntimeException( sprintf( 'Failed creating directiory %1s reason is %2s', $path, last_error_message() ), 1 );
		}
	} elseif ( ! is_dir( $path ) ) {
		throw new \RuntimeException( $path . ' exists but is not a directory', 2 );
	} elseif ( ! wp_is_writable( $path ) ) {
		throw new \RuntimeException( $path . ' directory exists but is not a writeable', 3 );
	}
}

/**
 * Redirect to an admin page while indicating there is an available status
 * of the last operation by adding a cp-action-result parameter to the URL.
 *
 * Intended to work to gether with previous_action_results.
 *
 * @param string $url     The admin page to redirect to.
 * @param array  $notices The admin notices handler to be used for displaying the notices.
 *                        The relevant data is stored in a transansient, named based on a user id.
 *
 * @since 1.0.0
 */
function redirect_admin_with_action_results( string $url, \calmpress\admin\Admin_Notices_Handler $notices ) {
	set_transient(
		'cp_action_result_' . get_current_user_id(), 
		[ 
			'class' => get_class( $notices ),
			'data'  => $notices->json(),
		],
		30
	);
	$redirect_to = add_query_arg( 'cp-action-result', 'true', $url );
	wp_redirect( $redirect_to );
	exit;
}

/**
 * Display the results of the previous admin action if there is one.
 * A complimentry part of redirect_admin_with_action_results and assumes it was used to report
 * results.
 *
 * Results are available if cp-action-result url parameter is set to "true" and there are value
 * in the user specific transient.
 * 
 * should be called before 'admin_notices' action is triggered.
 *
 * @since 1.0.0
 */
function display_previous_action_results() {
	if ( did_action( 'admin_notices' ) ) {
		_doing_it_wrong( __FUNCTION__, 'Has to be called before "admin_notices" action is run', 'calmPress 1.0.0' );
	}

	if ( isset( $_GET['cp-action-result'] ) && 'true' === $_GET['cp-action-result'] ) {
		$value = get_transient( 'cp_action_result_' . get_current_user_id() );
		if ( is_array( $value ) ) {
			$class = $value['class'];
			$data  = $value['data'];
			try {
				$notices = new $class( $data );
				add_action( 'admin_notices', [ $notices, 'output_notices'] );
			} catch ( \Exception $e ) {
				trigger_error( 'Failed to display notices for class ' . $class . ' data ' . $data );
			}
			delete_transient( 'cp_action_result_' . get_current_user_id() );
		}
	}
}

/**
 * Encode a string as a base64URL.
 * 
 * The base64URL format allows a base64 string to be used as a URL arameter by
 * eliminating characters that might break the expected structure of the URL.
 * This is done by replacing "+" with "-" and "/" with "_", and eliminating
 * the "=" right side padding of generated base64 strings.
 * 
 * Strings encoded this way should be decoded with base64URL_decode
 * 
 * @since 1.0.0
 * 
 * @param string $encode The string to encode.
 * 
 * @return string A base64 like encoding of $encode which is suitable to be used in URLs.
 */
function base64URL_encode( string $encode ): string {
	// Encode as base64.
	$base64 = base64_encode( $encode );

	// Replace + with - and / with _. remove = as right hand padding.
	$ret = strtr( $base64, '+/', '-_');
	$ret = rtrim( $ret, '=' );

	return $ret;
}

/**
 * Decodes a base64 style string encoded with base64URL_encode.
 * 
 * @since 1.0.0
 * 
 * @param string $decode The string to decode.
 * 
 * @return string|false The decoded string of false if decoding fails.
 */
function base64URL_decode( string $decode ): string|false {

	// Inverse of encoding, Replace - with + and _ with /.
	$base64 = strtr( $decode, '-_', '+/' );

	return base64_decode( $base64, true );
}

/**
 * Generate an encrypted string representation of an int value which can be
 * decrypted using decrypt_int_from_base64URL.
 * 
 * A nonce can be added to the encryption for processes that will need to validate
 * the validity of the value based on additional logic.
 * 
 * The string generate is fitting to use in URL parameters avoiding URL breaking characters
 *
 * @since 1.0.0
 * 
 * @param int $value The string which to decrypt.
 * @param int $nonce The nonce value to encrypt with the value.
 *
 * @return string A base64 format string of the encryption result.
 */
function encrypt_int_to_base64URL( int $value, int $nonce ): string {

	$ekey   = substr( AUTH_KEY, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	$enonce = substr( AUTH_SALT, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

	$encrypted = sodium_crypto_secretbox( $value . '|' . $nonce, $enonce, $ekey );
	return base64_encode( $encrypted );
}

/**
 * Decrypt a string generated with encrypt_int_to_base64URL and extract the value
 * encoded in it.
 *
 * @since 1.0.0
 * 
 * @param string $encrypted_value The string which to decrypt.
 *
 * @return Decryption_Result The structure containing the value and nonce which were encrypted.
 *
 * @throws Exception If decryption had failed.
 */
function decrypt_int_from_base64URL( string $encrypted_value ): Decryption_Result {

	$raw_encrypted = base64URL_decode( $encrypted_value );

	// was it a valid base64
	if ( false === $raw_encrypted ) {
		throw new \Exception();
	}

	$ekey   = substr( AUTH_KEY, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	$enonce = substr( AUTH_SALT, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

	$decrypted = sodium_crypto_secretbox_open( $raw_encrypted, $enonce, $ekey );

	// Failed decryption 
	if ( false === $decrypted ) {
		throw new \Exception();
	}

	// Check it is in the format encrypted by encrypt_int_to_base64 which contains
	// value and nonce separate by a "|".
	$parts = explode( '|', $decrypted );
	if ( count( $parts ) !== 2 ) {
		throw new \Exception();
	}

	// Check if string is valid format but value not an int.
	$value = filter_var( $parts[0], FILTER_VALIDATE_INT ); 
	if ( $value === false ) {
		throw new \Exception();
	}

	// String is valid format but value not an int.
	$nonce = filter_var( $parts[1], FILTER_VALIDATE_INT ); 
	if ( $nonce === false ) {
		throw new \Exception();
	}

	return new Decryption_Result( $value, $nonce );
}

/**
 * Register and enqueue an "inline" style and do not enque styles added after
 * the first one was enqueued.
 * 
 * This provides a way to enque inline styles without duplicating them as long
 * as the same handle is being used.
 * 
 * @since 1.0.0
 * 
 * @param string The handle to use to identofy the style.
 * @param string The style to enqueue.
 */
function enqueue_inline_style_once( string $handle, string $style ): void {

	// Bail out if handle was already enqueued.
	if ( wp_style_is( $handle, 'enqueued' ) ) {
		return;
	}

	wp_register_style( $handle, false ); // 'false' means no external file, just inline
	wp_add_inline_style( $handle, $style );
	wp_enqueue_style( $handle );
}

/**
 * Register and enqueue an "inline" style which is common to avatars.
 * 
 * @since 1.0.0
 */
function enqueue_avatar_inline_style(): void {

	enqueue_inline_style_once( 'avatar-default-style', '.avatar {border-radius:50%;}' );
}

/**
 * Insert enqueued styles into the head element of an HTML.
 * 
 * @since 1.0.0
 * 
 * @param string $html The HTML to insert into.
 * 
 * @return string The HTML with enqued styles in the head section.
 */
function insert_style_into_html_head( string $html): string {
	$position = strpos( $html, '</head>' );

	// Verify we handling a proper HTML including an head element before adding CSS in the header.
	if ( $position === false ) {
		calmpress\logger\Controller::log_warning_message(
			'could not find </head> in the genrated HTML, you should check it is there and with lower case and no extra spaces',
			__FILE__,
			__LINE__,
			get_current_user_id(),
			'',
			calmpress\logger\Controller::request_info( 20 )
		);
	} else {
		// fetch the CSS links and inlines just the way it would have been done as a wp_head action.

		ob_start();
		wp_maybe_inline_styles();
		wp_print_styles();
		//print_late_styles();
		$css = ob_get_clean();
		
		$html = substr_replace( $html, $css . '</head>', $position, strlen( '</head>' ) );
	}
	return $html;
}

/**
 * Check if the current user session is "fresh", verifying that user authentication
 * was less than 12 hours ago*, and prompting a login interface if it is not.
 * 
 * If the session is not fresh and requires reauthetication mark it as such.
 * 
 * It is assumed to be called for an authenticated user.
 * 
 * *The actuall freshness interval can be controlled by setting the SESSION_FRESHNESS_TIME
 * constant to some different value of number of seconds a session is fresh.
 * 
 * @since 1.0.0
 * 
 * @return bool true if session is fresh or no session, false if reauthentication
 *              is triggered.
 */
function is_fresh_logged_in_session(): bool {
	$session_token = wp_get_session_token();

	// No token? not a browser session.
	if ( ! $session_token ) {
		return true;
	}

	$current_user = wp_get_current_user();
	$session_manager = \WP_Session_Tokens::get_instance( $current_user->ID );
	$fresh = $session_manager->is_fresh( SESSION_FRESHNESS_TIME, $session_token );

	if ( ! $fresh ) {

		/*
			If its a page request trigger display of login dialog.
			If not, set an indication that heartbeat should request reauthentication.
		*/
		$session_data = $session_manager->get( $session_token );
		$session_data['reauthentication_needed'] = 1;
		$session_manager->update( $session_token, $session_data );
	}

	return $fresh;
}

/**
 * Check if session is fresh enough to use the capabilities passed as part
 * of heartbeat.
 *
 * If the session is not fresh enough for any of the indiate ccapabilities
 * the wp-auth-check field in the response will be set to false to indicate
 * that reauthentication is needed.
 * 
 * @since 1.0.o
 *
 * @param array  $response  The Heartbeat response.
 * @param array  $data      The $_POST data sent.
 * @param string $screen_id The screen ID.
 * 
 * @return array The Heartbeat response.
 */
function heartbeat_check_session_freshness( $response, $data, $screen_id ) {
	if ( isset( $data['reauth_capabilities'] ) && is_array( $data['reauth_capabilities'] ) ) {
		if ( capabilities_requiring_refresh ( $data['reauth_capabilities'] ) ) {

			// Used to mark session as requiring reaith for the heartneat_send filter.
			is_fresh_logged_in_session();
		}
	}

	return $response;
}

function asset_version( string $asset ): string {
	static $versions = [];

	$asset = str_replace( '.min.', '.', $asset );
	
	if ( empty( $versions ) ) {
		if ( file_exists( ABSPATH . WPINC . '/assets/assets_versions.php' ) ) {
			$versions = include ABSPATH . WPINC . '/assets/assets_versions.php';
		}
	}

	if ( isset( $versions[ $asset ] ) ) {
		return calm_version_hash( $versions[ $asset ] );
	} else {
		return '';
	}
}