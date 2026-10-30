<?php
/**
 * Failure while updating marked content in a file.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Identifies which file operation failed while updating marked content.
 *
 * @since 1.0.0
 */
class Marker_File_Exception extends \RuntimeException {

	/**
	 * Reading the existing file failed.
	 *
	 * @since 1.0.0
	 */
	public const READ_FAILED = 1;

	/**
	 * Writing the updated file failed.
	 *
	 * @since 1.0.0
	 */
	public const WRITE_FAILED = 2;

	/**
	 * Convert the failure to an error suitable for an administrator.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Error The operation-specific error.
	 */
	public function to_wp_error(): \WP_Error {
		return match ( $this->getCode() ) {
			self::READ_FAILED => new \WP_Error( 'marker_file_read_failed', __( 'Could not read the file.' ) ),
			self::WRITE_FAILED => new \WP_Error( 'marker_file_write_failed', __( 'Could not write the file.' ) ),
			default => new \WP_Error( 'marker_file_failed', __( 'Could not update the file.' ) ),
		};
	}
}
