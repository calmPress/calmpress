<?php
/**
 * Implementation of lock files with write accesse via FTP.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Class for locking files with the ability to write to them via FTP.
 *
 * @since 1.0.0
 */
class Locked_File_FTP_Write_Access extends Locked_File_Access {

	/**
	 * The prefix containing protocol information to use with the file paths.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $protocol_prefix;

	/**
	 * The absolute path to the directory to which the root directory is mapped
	 * in the FTP server.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $base_dir;

	/**
	 * Construct the object.
	 *
	 * Creates the file in the temp directory on which locking will be used
	 * as a proxy to locking the actual file.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception If the $file_path or $base_dir are not an absolute paths.
	 *
	 * @param string $file_path The absolute path of the file.
	 * @param string $host      The host (DNS or IP address) of the FTP server.
	 * @param int    $port      The port on which the FTP server listens.
	 * @param string $username  The user name to use in the authentication to the FTP server.
	 *                          Leading and trailing spaces are treated as significant characters.
	 * @param string $password  The password to use in the authentication to the FTP server.
	 *                          Leading and trailing spaces are treated as significant characters.
	 * @param string $base_dir  The absolute path to the directory to which the
	 *                          root directory is mapped in the FTP server.
	 */
	public function __construct( string $file_path, string $host, int $port, string $username, string $password, string $base_dir ) {
		if ( ! path_is_absolute( $base_dir ) ) {
			throw new Locked_File_Exception( '"' . $base_dir . '"' . ' is not an absolute path', Locked_File_Exception::PATH_NOT_ABSOLUTE );
		}

		parent::__construct( $file_path );

		$this->protocol_prefix = 'ftp://';
		if ( ! empty( $username ) ) {
			$this->protocol_prefix .= rawurlencode( $username );
			if ( ! empty( $password ) ) {
				$this->protocol_prefix .= ':' . rawurlencode( $password );
			}
			$this->protocol_prefix .= '@';
		}

		$this->protocol_prefix .= $host . '/';
		$this->base_dir = $base_dir;
	}

	/**
	 * Check if a path can be accesseable on the FTP server based on the base dir
	 * configuration.
	 *
	 * If the path is outside, raise an exception.
	 *
	 * @since 1.0.0
	 *
	 * @throws Locked_File_Exception If $path is not in base_dir.
	 *
	 * @param string $path The path to check
	 *
	 * @return string The path relative to the FTP server root (base_dir).
	 */
	private function adjust_path_to_ftp_root( $path ) {

		// If the FTP root is the filesystem root, there is nothing to do.
		if ( '/' === $this->base_dir ) {
			return $path;
		}

		// Make sure the location is accessable under the FTP server.
		// Using case insensitive here is not great but probably good enough for
		// real life usage.
		if ( 0 !== stripos( $path, $this->base_dir ) ) {
			throw new Locked_File_Exception( '"' . $path . '"' . ' is not accessible as FTP root is ' . '"' . $this->base_dir . '"', Locked_File_Exception::PATH_NOT_ACESSABLE );
		}

		// Remove the base dir part of the file's path.
		$adjusted_path = substr( $path, strlen( $this->base_dir ) );

		return $adjusted_path;
	}

	/**
	 * Helper to generate a full ftp:// based path to a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file The file path.
	 *
	 * @return string The ftp:// representation of the file.
	 */
	private function ftp_path( string $file ) {
		return $this->protocol_prefix . $this->adjust_path_to_ftp_root( ltrim( $file ) );
	}

	/**
	 * Helper to generate the context with overwrite option.
	 *
	 * @since 1.0.0
	 *
	 * @return resource stream context allowing file overwrite.
	 */
	private function stream_context() {
		// Allows overwriting of existing files on the remote FTP server
		$stream_options = array('ftp' => array('overwrite' => true));

		// Creates a stream context resource with the defined options
		$stream_context = stream_context_create($stream_options);

		return $stream_context;
	}

	/**
	 * Write a string to the file, erasing the current content. Will create the
	 * file if do not exist.
	 *
	 * @since 1.0.0
	 *
	 * @param string $contents The string to write.
	 */
	public function put_contents( string $contents ) {
		if ( false === @file_put_contents( $this->ftp_path( $this->file_path ), $contents, 0, $this->stream_context() ) ) {
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
		if ( false === @file_put_contents( $this->ftp_path( $this->file_path ), $contents, FILE_APPEND, $this->stream_context() ) ) {
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
		if ( ! @copy( $this->ftp_path( $this->file_path ), $this->ftp_path( $destination ), $this->stream_context() ) ) {
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
		if ( ! @rename( $this->ftp_path( $this->file_path ), $this->ftp_path( $destination ), $this->stream_context() ) ) {
			$this->raise_exception_from_error();
		}
	}

	/**
	 * Helper function for the unlink method implementing the actual file unlink.
	 *
	 * @since 1.0.0
	 */
	protected function file_unlink() {
		if ( ! @unlink( $this->ftp_path( $this->file_path ), $this->stream_context() ) ) {
			$this->raise_exception_from_error();
		}
	}
}
