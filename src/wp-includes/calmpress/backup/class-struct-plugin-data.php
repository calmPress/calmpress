<?php
/**
 * Implememntation of a struct like data holder for backup plugin info.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * Not an actual representation of an object, just a structure to hold relevant directory and version info.
 */
class Struct_Plugin_Data extends Struct_Versioned_Data {
	/**
	 * Indicates if the plugin is loctaed on the root plugins directory.
	 *
	 * @var bool
	 *
	 * @since 1.0.0
	 */
	private bool $is_root;

	/**
	 * Construct the item.
	 *
	 * @param string $version   The version.
	 * @param string $directory The path to the backup directory.
	 * @param bool   $is_root   Indicates if the plugin is loctaed on the root
	 *                          plugins directory.
	 */
	public function __construct( string $version, string $directory, bool $is_root ) {
		parent::__construct( $version, $directory );
		$this->is_root = $is_root;
	}

	/**
	 * Whether the plugin is a file on root directory
	 *
	 * @return bool True is plugin single file at root plugins directory.
	 */
	public function is_root(): bool {
		return $this->is_root;
	}
}
