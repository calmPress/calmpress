<?php
/**
 * Interface specification of a virtual backup storage.
 * This can be a specific location on the hard drive, cloud storage, etc...
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An abstract representation of a backup storage medium.
 *
 * @since 1.0.0
 */
interface Backup_Storage {
	/**
	 * Human redable description of the storage.
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
}
