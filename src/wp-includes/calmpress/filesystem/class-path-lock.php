<?php
/**
 * Implementation of class of locks for filesytem paths.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Provides a mechanism to lock access to path. This is locking a newly created file
 * based on the path and locking access to it.
 * 
 * The lock locks out other processes which is a perfect match for apache prefork mpm
 * and fastcgi FPM. Less useful on multithreaded enviroments.
 * 
 * One implication of being locked for a process is that you can lock a path several times
 * in the same execution path. In other words, you can lock a path anywhere you want with no ill
 * efects. The lock will be freed only when the object which created the first lock will destruct.
 * 
 * The assumption is that every access to a path will be preceded by creating
 * a lock via this class and therefor locking will be consistant.
 *
 * The lock is released on destruction of the object, therefor you
 * should limit the time in which the object is "alive" by creating them only
 * in local scope.
 * The alternative is to explicitly unset or nullify the variable holding it when
 * it is not needed any more.
 *
 * If the object is not destroyed during the normal code flow, it will be destroyed when
 * PHP execution will terminate, which might not be a problem if it takes a second
 * to terminate, but might be problematic if the process is executing some time consuming
 * logic.
 *
 * @since 1.0.0
 */
class Path_Lock {

	/**
	 * The path of the file on which the virtual lock is applied.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $path;

	/**
	 * The file pointer for the lock file. Need to keep it around for it not to
	 * be garbage collected.
	 *
	 * @since 1.0.0
	 * @var resource
	 */
	protected $hash_file_fp;

	/**
	 * The paths for which a lock is active.
	 * Used to prevent attempt to double lock.
	 *
	 * @since 1.0.0
	 * @var string[]
	 */
	private static $locked_paths = [];

	/**
	 * Construct the object.
	 *
	 * Creates the file in the temp directory on which locking will be used
	 * as a proxy to locking the actual file.
	 *
	 * @since 1.0.0
	 *
	 * @throws DomainException If the $path is not an absolute path.
	 *
	 * @param string $path The path to lock.
	 */
	public function __construct( string $path ) {
		if ( ! path_is_absolute( $path ) ) {
			throw new \DomainException( '"' . $path . '" is not an absolute path' );
		}

		$this->hash_file_fp = null; // Indicate it is unlocked.

		if ( ! isset( self::$locked_paths[ $path ] ) ) {
			$this->seize_lock( $path );
		}
	}

	/**
	 * Destruct the object.
	 *
	 * Unlock and delete the proxy lock file.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		$this->release_lock();
	}

	/**
	 * Utility function to seize the lock by creating a proxy file and locking it.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path being seized.
	 */
	protected function seize_lock( string $path ) {
		$this->path         = $path;
		$hash               = md5( $this->path );
		$filename           = get_temp_dir() . 'calmpress-filelock-' . $hash;
		$this->hash_file_fp = fopen( $filename, 'w+' );
		flock( $this->hash_file_fp, LOCK_EX );
		self::$locked_paths[ $path ] = true;
	}

	/**
	 * Utility function to release the lock by closing the proxy file pointer
	 * and deleting it.
	 *
	 * @since 1.0.0
	 */
	protected function release_lock() {
		if ( null === $this->hash_file_fp ) {
			// not actually locked with flock.
			return;
		}

		flock( $this->hash_file_fp, LOCK_UN );
		fclose( $this->hash_file_fp );
		unset( self::$locked_paths[ $this->path ] );
		$hash     = md5( $this->path );
		$filename = get_temp_dir() . 'calmpress-filelock-' . $hash;
		if ( file_exists( $filename ) ) {
			unlink( $filename );
		}
	}
}
