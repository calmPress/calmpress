<?php
/**
 * Implememntation of a struct like data holder for backup directory and version info.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * Not an actual representation of an object, just a structure to hold relevant directory and version info.
 */
class Struct_Versioned_Data {
	/**
	 * The version of the item which was backedup.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private string $version;

	/**
	 * The path to the directory in which the files are stored.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	private string $directory;

	/**
	 * Construct the item.
	 *
	 * @param string $version   The version.
	 * @param string $directory The path to the backup directory.
	 */
	public function __construct( string $version, string $directory ) {
		$this->version   = $version;
		$this->directory = $directory;
	}

	/**
	 * The version strored by the object
	 *
	 * @return string The version.
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * The directory strored by the object
	 *
	 * @return string The directory.
	 */
	public function directory(): string {
		return $this->directory;
	}
}
