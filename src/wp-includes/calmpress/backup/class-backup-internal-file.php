<?php
/**
 * Specification and implementation of an internal file used to store backed up information.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An internal file used to hold some of a backup content.
 *
 * An extension of a read only file with relativr path information API.
 *
 * @since 1.0.0
 */
class Backup_Internal_File extends \calmpress\filesystem\Read_Only_File {

	/**
	 * The root of the backup files.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $root;

	/**
	 * Construct the object from the backup root URI and relative file path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $root          The path to the root directory (URI) of the backup.
	 * @param string $relative_path The files relative path.
	 *
	 * @throws DomainException If the resulting path is not a file.
	 */
	public function __construct( string $root, string $relative_path ) {
		$this->root = $root;
		parent::__construct( rtrim( $root, '/' ) . '/' . $relative_path );
	}

	/**
	 * The relative path to the backup's root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description.
	 */
	public function relative_path():string {
		return $this->relative_path;
	}
}