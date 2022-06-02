<?php
/**
 * Interface specification of a virtual backup storage using an incremental backup.
 * This can be a specific location on the hard drive, cloud storage, etc...
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An abstract representation of a backup storage medium used for incremental backups.
 *
 * @since 1.0.0
 */
interface Backup_Storage {

	/**
	 * Human readable description of the storage. Shoiuld not contain HTML (it will be escaped),
	 * and be translated where appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public function description() : string;

	/**
	 * A unique identifier of the storage. Anything may be used
	 * as long as it is consistant between page reloads.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public function identifier() : string;

	/**
	 * The backups stored at this storage.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Container A container which contains the backups.
	 */
	public function backups() : Backup_Container;

	/**
	 * Copy a local file to the storage at a specific location (URI).
	 *
	 * Should raise an exception on failure of any type. The exception's message should be translatable
	 * wherever possible as it will most likely be presented to the user.
	 *
	 * It is the responsability of the implementation to create "directories" or any other meta
	 * information whenever needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source   The absolute path (or URI) to the file to copy.
	 * @param string $dest_uri The copied file's path relative to storage root.
	 */
	public function copy_file( string $source, string $dest_uri );

	/**
	 * Check if a specific backup section exists. A section is a target to an incremental
	 * backup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $uri The path to check relative to the storage's root.
	 *
	 * @return bool true if the directory exists, otherwise false.
	 */
	public function section_exists( string $uri ): bool;

	/**
	 * Get a temporary storage intended to be used to create working area for backed files
	 * under a specific section. Once the backup reaches some atomic integrety
	 * (have all the relevant files assembeled) at which it can be "committed" as proper 
	 * part of the backup.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dest_uri The directory to which the files should be commited once
	 *                         all files are assembled. Relative path to the storeage root.
	 *
	 * @return Temporary_Backup_Storage A temporary storage instance.
	 */
	public function section_working_area_storage( string $dest_uri ): Temporary_Backup_Storage;

	/**
	 * Gets a "Read Only" file handler that provides access to reading the file at the specific
	 * URI.
	 *
	 * Should raise an exception on failure of any type. The exception's message should be translatable
	 * wherever possible as it will most likely be presented to the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $relative_path The file's path relative to storage root.
	 * 
	 * @return \calmpress\filesystem\Read_Only_File An read only file representation that enables read
	 *                                              access to the file.
	 */
	public function read_handler_for( string $relative_path ): \calmpress\filesystem\Read_Only_File;

	/**
	 * Store a backup meta information at the location where such information is stored on the
	 * specific storage.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description  The description of the speific backup.
	 * @param int    $time         The UTC time at which the backup creation was completed.
	 * @param array  $engines_data The engine specific data for the backup. The keys are the engine identifiers
	 *                             and the value contains the actual datya.
	 */
	public function store_backup_meta( string $description, int $time, array $engines_data );
}
