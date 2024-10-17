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
 * Generate an encrypted string representation of an int value which can be
 * decrypted using decrypt_int_from_base64.
 * 
 * A nonce can be added to the encryption for processes that will need to validate
 * the validity of the value based on additional logic.
 *
 * @since 1.0.0
 * 
 * @param int $value The string which to decrypt.
 * @param int $nonce The nonce value to encrypt with the value.
 *
 * @return string A base64 format string of the encryption result.
 */
function encrypt_int_to_base64( int $value, int $nonce ): string {

	$ekey   = substr( AUTH_KEY, 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
	$enonce = substr( AUTH_SALT, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

	return base64_encode( sodium_crypto_secretbox( $value . '|' . $nonce, $enonce, $ekey ) );
}

/**
 * Decrypt a string generated with encrypt_int_to_base64 and extract the value
 * encoded in it.
 *
 * @since 1.0.0
 * 
 * @param string $encrypted_value The string which to decrypt.
 *
 * @return int The value which was encrypted.
 *
 * @throws Exception If decryption had failed.
 */
function decrypt_int_from_base64( string $encrypted_value ): Decryption_Result {
	$raw_encrypted = base64_decode( $encrypted_value );

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