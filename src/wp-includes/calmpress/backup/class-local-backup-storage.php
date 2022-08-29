<?php
/**
 * An implementation of the backup storage located at the 
 * a backup location accessable via "normal" file paths.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An implementation of the backup storage located at the 
 * a backup location accessable via "normal" file paths.
 *
 * The name "local" refers to either backups on the same disk where the app is located,
 * or disks attached via network protocols (NFS and similar).
 * Can be used to RAM based disk volume and other non persistant storage,
 * but that is obviously not recommended.
 *
 * The files in the directory should be only the zipped files and meta files named *.meta
 * which contains information about the backup.
 * The meta files contain json information about the backup which must include at least
 * the following fields:
 *  - 'backup_file'    - A string, the file name of the actual backup file relative to
 *                       the backup directory.
 *  - 'description'    - A string, the human readable description of the backup.
 *  - 'backup_engines' - An array of strings used to identify which engines created parts
 *                       or all of the backup. a value of 'core' indicates the minimal core
 *                       backup.
 *  - 'time_created'   - An integer, the unix time in which the backup was created.
 * Meta files can include any other information as well, as long as the json is valid and
 * contains the mandatory fields.
 *
 * @since 1.0.0
 */
class Local_Backup_Storage implements Backup_Storage {

	/**
	 * The root directory at which backups are stored.
	 * Defaults to wp-content/.private/backup/ (in the constructor).
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $root;

	/**
	 * The identifier of the storage.
	 * Defaults to "default_local_storage".
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	protected string $id;

	/**
	 * Create a storage object based at specific root directory.
	 *
	 * When using this constructor from outside core code, use explicit and different
	 * parameter values. Using same $id will cause problems at some point. Same $root might work but
	 * unlikely to make sense.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $root The absolute path of the backups root directory.
	 * @param string $id   The identifier to be used when internally identifying the storage.
	 */
	public function __construct( string $root = WP_CONTENT_DIR . '/.private/backup/', $id = 'default_local_storage' ) {
		$this->root = $root;
		$this->id   = $id;
	}

	/**
	 * Human redable description of the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string {
		return sprintf( __( 'Backups located at %s' ), $this->root );
	}

	/**
	 * A unique identifier of the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public function identifier() : string {
		return $this->id;
	}

	/**
	 * The backups stored at this storage.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Container A container which contains the backups.
	 */
	public function backups() : Backup_Container {
		$container = new Backup_Container();

		foreach ( glob( $this->root . 'meta-*.json' ) as $file ) {
			try {
				$meta = @file_get_contents( $file );
				if ( $meta === false ) {
					// could not read the file, log the even and continue to next file. 
					trigger_error( calmpress\utils\last_error_message() );
					continue;
				} 
				$backup = new Backup( $meta, $this, $file );
			} catch ( \Exception $e ) {
				// Failed to create an object for the backup, log it and move on to the next.
				trigger_error( 'Failed parsing the backup meta file ' . $file . ' because: ' . $e->getMessage() );
				continue;
			}
			$container->Add( $backup );
		}

		return $container;
	}

	/**
	 * Check if a specific backup section. A section in the context of this storage is a directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $uri The path to check relative to the storage's root.
	 *
	 * @return bool true if the directory exists, otherwise false.
	 */
	public function section_exists( string $uri ): bool {
		$dir = $this->root . '/' . ltrim( $uri, '/' );

		if ( ! file_exists( $dir ) ) {
			return false;
		}

		return is_dir( $dir );
	}

	/**
	 * Copy a file to storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source   The absolute path to the file to copy.
	 * @param string $dest_uri The copied file's path relative to storage root.
	 *
	 * @throws \Exception If source file do not exist or dest could not be created.
	 */
	public function copy_file( string $source, string $dest_path ) {
		$dest = $this->root . $dest_path;

		$dir = dirname( $dest_uri );
		\calmpress\utils\ensure_dir_exists( $dir );

		if ( ! is_file( $source ) ) {
			throw new \Exception( sprintf( __( '%s is not a file or do not exist', $source ) ) );
		}

		$res = @copy( $source, $dest_uri );
		if ( ! $res) {
			throw new \Exception( sprintf( __( 'copy of %1s to %2s reason is %3s', $source, $dest_uri, \calmpress\utils\last_error_message() ) ) );
		}
	}

	/**
	 * Gets a "Read Only" file handler that provides access to reading a file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path The file's path relative to storage root.
	 * 
	 * @return \calmpress\filesystem\Read_Only_File An read only file representation that enables read
	 *                                              access to the file.
	 */
	public function read_handler_for( string $relative_path ): \calmpress\filesystem\Read_Only_File {
		return new \calmpress\filesystem\Read_Only_File( $this->root . $relative_path );
	}

	/**
	 * Store a backup meta information at the location where such information is stored on the
	 * specific storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $meta The meta information of a backup to be stored.
	 */
	public function store_backup_meta( string $meta ) {
		file_put_contents( $this->root . 'meta-' . current_time( 'U', true ) . '.json', $meta );
	}

	/**
	 * Delete the backup meta information of a specific backup
	 *
	 * @since 1.0.0
	 *
	 * @param string $id In this storage it is expected to be the full path to the meta file.
	 */
	public function delete_backup_meta( string $id ) {
		unlink( $id );
	}

	/**
	 * Get a temporary storage intended to be used to create working area for backed files
	 * under a specific section. Once the backup reaches some atomic integrety
	 * (have all the relevant files assembeled) at which it can be "committed" as proper 
	 * part of the backup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dest_uri The relative directory to which the files should be committed once
	 *                         all files are assembled. Relative path to the storeage root.
	 *
	 * @return Temporary_Backup_Storage A temporary storage instance.
	 */
	public function section_working_area_storage( string $dest_uri ): Temporary_Backup_Storage {
		return new Local_Storage_Temporary_Backup_Storage( $this->root . '/' . $dest_uri );
	}
}
