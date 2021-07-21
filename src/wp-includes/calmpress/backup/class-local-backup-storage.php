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
	const PATH_RELATIVE_TO_UPLOADS = '.private/backup/';

	/**
	 * The directory in which backup files are located.
	 *
	 * @since 1.0.0
	 *
	 * @return string The directory path.
	 */
	protected function backup_dir() : string {
		$data = wp_upload_dir( null, false );
		return $data['basedir'] . self::PATH_RELATIVE_TO_UPLOADS;
	}

	/**
	 * Human redable description of the storage.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string {
		return 'Backups located at ' . $this->backup_dir();
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
		$container = new Backup_Container;

		foreach ( glob( $this->backup_dir() . '*.meta' ) as $file ) {
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
	 * A utility function to recursively add file names to a zip.
	 *
	 * @since 1.0.0
	 *
	 * @param ZipArchive $zip     The zip file to add the files into, should be open.
	 * @param string     $dirname The directory relative to calmPress root directory to zip.
	 */
	private static function zip_directory( ZipArchive $zip, string $dirname ) {

		$root_path = ABSPATH . '/' . $dirname;
		$zip->addEmptyDir( $dirname );

		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( ABSPATH . '/' . $dirname ),
			RecursiveIteratorIterator::LEAVES_ONLY
		);

		foreach ( $files as $name => $file ) {
			// Get file path relative to backup root.
			$relative_path = substr( $file->getRealPath(), strlen( $root_path ) + 1);

			if ( ! $file->isDir() )	{
				$zip->addFile( $file->getRealPath(), $relative_path );
			} else {
				$zip->addEmptyDir( $relative_path );
			}
		}
	}

	/**
	 * Do a back up.
	 *
	 * @since 1.0.0
	 *
	 * @return Local_Backup The backup object representing the backup.
	 */
	public static function backup() : Local_Backup {

		// Use a temp directory to avoid possible collosions.
		$tempdir = get_temp_dir();

		$zipfile = ZipArchive::open( 'backup.zip', ZipArchive::CREATE );
		self::zip_directory( $zipfile, 'wp-admin' );
		self::zip_directory( $zipfile, 'wp-includes' );
	}
}
