<?php
/**
 * An implementation of the backup storage located at the 
 * default backup location on the disk.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An implementation of the backup storage located at the
 * default backup location on the disk.
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
	 * The directory in which backup files are located relative to the
	 * site's uploads directory.
	 */
	const BACKUP_ROOT_DIR = WP_CONTENT_DIR . '/.private/backup/';

	/**
	 * Human redable description of the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string {
		return 'Backups located at ' . static::BACKUP_ROOT_DIR;
	}

	/**
	 * A unique identifier of the storage. Anything may be used
	 * as long as it is consistant between page reloads.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public function identifier() : string {
		return 'default_local';
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

		foreach ( glob( static::BACKUP_ROOT_DIR . '*.meta' ) as $file ) {
			try {
				$local_backup = new Local_Backup( $file );
			} catch ( Exception $e ) {
				// Failed to create an object for the backup, move on to the next.
				continue;
			}
			$container->Add( $local_backup );
		}

		return $container;
	}

	/**
	 * Do a local backup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description The description to be used for the backup.
	 *
	 * @throws \Exception if the backup fails.
	 */
	public function Backup( string $description ) {
		Local_Backup::create_backup( $description, static::BACKUP_ROOT_DIR );
	}

}
