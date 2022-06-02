<?php
/**
 * Implementation of temporary backup storage used when adding files to local storage.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A representation of a temporary backup storage medium used for storing files while
 * backuping to a local storage is in progress, with the intetion of "commiting" it to the main storage once
 * applicable.
 * 
 * It is assumed that such storages start at a "blank" state with files being added to them, without a need
 * for any explicit way to modify or delete them.
 *
 * @since 1.0.0
 */
class Local_Storage_Temporary_Backup_Storage extends Temporary_Backup_Storage {

	/**
	 * The root directory holding the files.
	 *
	 * @var string
	 */
	protected string $root;

	/**
	 * The root directory to which to transfer files in the "store" step..
	 *
	 * @var string
	 */
	protected string $dest_root_path;

	public function __construct( string $dest_root_path ) {
		$this->root = get_temp_dir() . uniqid( 'backup-', true );
		\calmpress\utils\ensure_dir_exists( $this->root );

		$this->dest_root_path = trailingslashit( $dest_root_path );
	}

	/**
	 * Copy a local file to the storage at a specific location relative to the root directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source   The absolute path (or URI) to the file to copy.
	 * @param string $dest_uri The copied file's path relative to temporary storage root.
	 *
	 * @throws \Exception If the copy fails.
	 */
	protected function copy_file_implementation( string $source, string $dest_uri ) {
		$dest_path = $this->root . '/' . $dest_uri;

		$dest_dir = dirname( $dest_path );
		\calmpress\utils\ensure_dir_exists( $dest_dir );

		$res = @copy( $source, $dest_path );
		if ( ! $res ) {
			throw new \Exception( 'Failed to copy file, reason: ' . \calmpress\utils\last_error_message() );
		}
	}

	/**
	 * Create/overwrite a file with a specific content 
	 * 
	 * @since 1.0.0
	 *
	 * @param string $dest_uri The created file's path relative to temporary storage root.
	 * @param string $content  The content to write to the file.
	 *
	 * @throws \Exception If the file creation fails.
	 */
	protected function file_put_contents_implementation( string $dest_uri, string $content ) {

	}

	/**
	 * Implement the transfer of temporary file to the actual storage by rename the temp root
	 * directory to the "end" one.
	 *
	 * @since 1.0.0
	 *
	 * @throws \Exception If rename fails.
	 */
	protected function store_to_storage() {

		\calmpress\utils\ensure_dir_exists( dirname( $this->dest_root_path ) );
		$res = rename( $this->root, $this->dest_root_path );
		if ( ! $res ) {
			throw new \Exception( 'Failed to rename directory, reason: ' . \calmpress\utils\last_error_message() );
		}
	}

	/**
	 * Remove all temporary files and directories.
	 *
	 * Ignores failures, do not throw exceptions.
	 *
	 * @since 1.0.0
	 */
	protected function cleanup() {

		try {
			$dir_iterator = new \RecursiveDirectoryIterator( $this->root, \RecursiveDirectoryIterator::SKIP_DOTS );
			$files        = new \RecursiveIteratorIterator( $dir_iterator, \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				if ( $file->isDir() ) {
					@rmdir( $file->getRealPath() );
				} else {
					@unlink( $file->getRealPath() );
				}
			}
			@rmdir( $this->root );
		} catch ( \Exception $e ) {}
	}
}
