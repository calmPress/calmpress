<?php
/**
 * Implementation of lock files access with the PHP file system APIs.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Class for accessing files with the PHP file API while locking access to
 * them to the current process.
 *
 * @since 1.0.0
 */
class Locked_File_Direct_Access extends Locked_File_Access {

	/**
	 * Write a string to the file, erasing the current content. Will create the
	 * file if do not exist.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contents The string to write.
	 */
	public function put_contents( string $contents ) {
		if ( false === @file_put_contents( $this->file_path, $contents ) ) {
			$this->raise_exception_from_error();
		}
	}

	/**
	 * Append a string to the end of the file. Will create the file if do not exist.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contents The string to append.
	 */
	public function append_contents( string $contents ) {
		if ( false === @file_put_contents( $this->file_path, $contents, FILE_APPEND ) ) {
			$this->raise_exception_from_error();
		}
	}

	/**
	 * Helper function for the copy method implementing the actual file copy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Path to the destination file.
	 */
	protected function file_copy( string $destination ) {
		if ( ! @copy( $this->file_path, $destination ) ) {
			$this->raise_exception_from_error();
		}
	}

	/**
	 * Helper function for the rename method implementing the actual file rename.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Path to the destination file.
	 */
	protected function file_rename( string $destination ) {
		if ( ! @rename( $this->file_path, $destination ) ) {
			$this->raise_exception_from_error();
		}
	}

	/**
	 * Helper function for the unlink method implementing the actual file unlink.
	 *
	 * @since 1.0.0
	 */
	protected function file_unlink() {
		if ( ! @unlink( $this->file_path ) ) {
			$this->raise_exception_from_error();
		}
	}
}
