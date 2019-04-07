<?php
/**
 * Declaration and partial implementation of base class for using locks when
 * accessing files.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Base class for accessing files while locking access to them to the current process.
 *
 * The non abstract methods implement the locking logic. A lock is obtained when
 * an object is created and released when it is destroyed.
 *
 * While the name indicates a strong association with files, you can use this
 * object for paths in which the file do not exist. You should not use it for
 * locking directories although it might get the work done as you will get the
 * same effect as using flock directly with no improvement in code readability.
 *
 * You should use a locked file access even if you only need to read from a file
 * which might use locking when writing to it.
 *
 * Keep in mind that the lock is released on destruction of the object, therefor you
 * should limit the time in which the object is "a life" by creating them only
 * in local scope.
 * The alternative is to explicitly unset or nullify the variable holding it when
 * it is not needed any more.
 *
 * If the object is not destroyed during the normal code flow, it will be destroyed when
 * PHP execution will terminate, which might not be a problem if it takes a second
 * to terminate, but might be problematic if the process is executing some time consuming
 * logic.
 *
 * For the locking logic it is not the file itself that is being locked, but
 * a "proxy" file created based on hashing the file path in the temp directory.
 * The reason for this is that an actual file lock will prevent updating the file
 * with FTP, as the FTP process will be locked out of accessing the file.
 *
 * @since 1.0.0
 */
abstract class Locked_File_Access {

	/**
	 * The path of the file on which the virtual lock is applied.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $file_path;

	/**
	 * The file pointer for the lock file. Need to keep it around for it not to
	 * be garbage collected.
	 *
	 * @since 1.0.0
	 * @var resource
	 */
	private $hash_file_fp;

	/**
	 * Construct the object.
	 *
	 * Creates the file in the temp directory on which locking will be used
	 * as a proxy to locking the actual file.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception If the $file_path is not an absolute path.
	 *
	 * @param string $file_path The path of the file.
	 */
	 function __construct( string $file_path ) {
		 if ( ! path_is_absolute( $file_path ) ) {
			 throw new Locked_File_Exception( '"' . $file_path . '"' . ' is not an absolute path', Locked_File_Exception::PATH_NOT_ABSOLUTE );
		 }

		 $this->seize_lock( $file_path );
	 }

	 /**
 	 * Destruct the object.
 	 *
 	 * Unlock and delete the proxy lock file.
 	 *
 	 * @since 1.0.0
 	 */
	function __destruct( ) {
		$this->release_lock();
 	}

	/**
	 * Check if a file path is locked.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception If the $file_path is not an absolute path.
	 *
	 * @param string $file_path The path of the file.
	 *
	 * @return bool True if the file path is locked, false otherwise.
	 */
	static public function locked( $file_path ) {
		if ( ! path_is_absolute( $file_path ) ) {
			throw new Locked_File_Exception( '"' . $file_path . '"' . ' is not an absolute path', Locked_File_Exception::PATH_NOT_ABSOLUTE );
		}

		$hash     = md5( $file_path );
		$filename = get_temp_dir() . 'calmpress-filelock-' . $hash;
		return file_exists( $filename );
	}

	/**
	 * Utility function to seize the lock by creating a proxy file and locking it.
	 *
	 * @since 1.0.0
	 */
	protected function seize_lock( $file_path ) {
		$this->file_path    = $file_path;
		$hash               = md5( $this->file_path );
		$filename           = get_temp_dir() . 'calmpress-filelock-' . $hash;
		$this->hash_file_fp = fopen( $filename, 'w+');
		flock( $this->hash_file_fp, LOCK_EX );
	}

	/**
	 * Utility function to release the lock by closing the proxy file pointer
	 * and deleting it.
	 *
	 * @since 1.0.0
	 */
	protected function release_lock() {
		flock( $this->hash_file_fp, LOCK_UN );
		fclose( $this->hash_file_fp );
		$hash     = md5( $this->file_path );
		$filename = get_temp_dir() . 'calmpress-filelock-' . $hash;
		if ( file_exists( $filename ) ) {
			unlink( $filename );
		}
	}

	/**
	 * Read the entire content of the file into a string.
	 *
	 * Might exception if the file do not exists. If unsure use the exists() method
	 * to make sure it exists before calling this one.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception When the read fails.
	 *
	 * @return string The file content as a string.
	 */
	public function get_contents() {
		$content = @file_get_contents( $this->file_path );
		if ( false === $content ) {
			$this->raise_exception_from_error();
		}

		return $content;
	}

	/**
	 * Write a string to the file, erasing the current content. Will create the
	 * file if do not exist.
	 *
	 * Implementation should throw Locked_File_Exception when operation fails.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception When the operation fails.
	 *
	 * @param string $contents The string to write.
	 */
	abstract public function put_contents( string $contents );

	/**
	 * Append a string to the end of the file. Will create the file if do not exist.
	 *
	 * Implementation should throw Locked_File_Exception when operation fails.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception When the operation fails.
	 *
	 * @param string $contents The string to append.
	 */
	abstract public function append_contents( string $contents );

	/**
	 * Copy the file.
	 *
	 * The new file will be locked
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception When the copy fails. This can be caused
	 *                               by file manipulation error or by failure to
	 *                               lock the new file.
	 *
	 * @param string $destination Path to the destination file.
	 * @return calmpress\FileSystem\Locked_File_Access The new locked file.
	 */
	public function copy( $destination ) {

		// Clone is used to retain whatever connection information there might
		// be in the current object.
		$newfile = clone $this;

		// Seize lock for the location of the new file.
		$newfile->seize_lock( $destination );

		// Actually copy the file. If copy fails an exception is raised and the
		// new lock is garbage collected
		$this->file_copy( $destination );

		return $newfile;
	}

	/**
	 * Helper function for the copy method implementing the actual file copy.
	 *
	 * Implementation should throw Locked_File_Exception when operation fails.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception When the copy fails.
	 *
	 * @param string $destination Path to the destination file.
	 */
	abstract protected function file_copy( string $destination );

	/**
	 * Move/rename the file.
	 *
	 * The current file will be removed and copied to the new location,
	 * overwriting the file that was there if needed.
	 * The lock for the current path is retained and a lock the new path
	 * is returned.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception When the rename fails. This can be caused
	 *                               by file manipulation error or by failure to
	 *                               lock the new file.
	 *
	 * @param string $destination The new path for the file.
	 * @return calmpress\FileSystem\Locked_File_Access The new locked file.
	 */
	public function rename( $destination ) {

		// Clone is used to retain whatever connection information there might
		// be in the current object.
		$newfile = clone $this;

		// Seize lock for the location of the new file.
		$newfile->seize_lock( $destination );

		// Actually rename the file. If rename fails an exception is raised and the
		// new lock is garbage collected
		$this->file_rename( $destination );

		return $newfile;
	}

	/**
	 * Helper function for the rename method implementing the actual file rename.
	 *
	 * Implementation should throw Locked_File_Exception when operation fails.
	 *
	 * @throws Locked_File_Exception When the rename fails.
	 *
	 * @since 1.0.0
	 *
	 * @param string $destination Path to the destination file.
	 */
	abstract protected function file_rename( string $destination );

	/**
	 * Delete the file.
	 *
	 * Technically, makes sure the file do not exists. In other words, trying to
	 * unlink non existing file will not raise an error.
	 * The lock for the current location is not released after the unlink.
	 *
	 * @throws Locked_File_Exception When the unlink fails.
	 *
	 * @since 1.0.0
	 */
	public function unlink() {
		if ( $this->exists() ) {
			$this->file_unlink();
		}
	}

	/**
	 * Helper function for the unlink method implementing the actual file unlink.
	 *
	 * Implementation should throw Locked_File_Exception when operation fails.
	 *
	 * @throws Locked_File_Exception When the unlink fails.
	 *
	 * @since 1.0.0
	 */
	abstract protected function file_unlink();

	/**
	 * Check if the file exists.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True when the file exists, false otherwise.
	 */
	public function exists() {
		return file_exists( $this->file_path );
	}

	/**
	 * Create and raise an exception based on file system operation failure.
	 *
	 * @throws Locked_File_Exception.
	 *
	 * @since 1.0.0
	 */
	protected function raise_exception_from_error() {
		$error = error_get_last();
		if ( $error ) {
			error_clear_last();
			throw new Locked_File_Exception( $error['message'], Locked_File_Exception::OPERATION_FAILED );
		} else {
			throw new Locked_File_Exception( 'Unknown error', Locked_File_Exception::OPERATION_FAILED );
		}
	}
}
