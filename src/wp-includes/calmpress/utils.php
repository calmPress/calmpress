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