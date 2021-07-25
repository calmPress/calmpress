<?php
/**
 * Interface specification of the backup class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An abstract representation of an backup.
 *
 * @since 1.0.0
 */
interface Backup {

	/**
	 * The server unix time in which the backup was created.
	 *
	 * @since 1.0.0
	 *
	 * @return int The unix time.
	 */
	public function time_created() : int;

	/**
	 * The human readable description of the backup.
	 *
	 * @since 1.0.0
	 *
	 * @return int The description.
	 */
	public function description() : string;

	/**
	 * The storage in which the backup is located.
	 *
	 * @since 1.0.0
	 *
	 * @return Backup_Storage The storage.
	 */
	public function storage() : Backup_Storage;

    public function type() : string;

	/**
	 * Restore the backup.
	 *
	 * The conceptually easy way to implement a restore for new classes is to bring the
	 * backup file into the local storage, create or bring meta file in relevant format
	 * and cretae a Local_Backup object and restore from it.
	 *
	 * @since 1.0.0
	 */
	public function restore( restore_engines $engines );
}