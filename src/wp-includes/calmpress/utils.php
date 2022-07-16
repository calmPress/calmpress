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
 * Verify that a directory exists at a specific path, and if does not try to create one.
 *
 * @param string $path The absolute path of the directory.
 *
 * @throws \Exception If path exists but it is not a directory, or if directory creation fails.
 */
function ensure_dir_exists( string $path ) {

	if ( ! file_exists( $path ) ) {
		$res = @mkdir( $path, 0755, true );
		if ( ! $res ) {
			throw new \Exception( sprintf( __( 'Failed creating directiory %1s reason is %2s' ), $path, last_error_message() ) );
		}
	} elseif ( ! is_dir( $path ) ) {
		throw new \Exception( sprintf( __( '%s exists but is not a directory' ), $path ) );
	}
}
