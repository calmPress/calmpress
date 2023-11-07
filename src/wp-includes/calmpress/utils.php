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
		_doing_it_wrong( __FUNCTION__, 'Has to be called before "admin_noticess" action is run', 'calmPress 1.0.0' );
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
