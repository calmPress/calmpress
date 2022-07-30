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
 * An abstract interface for a specific backup and restore algorithm.
 *
 * @since 1.0.0
 */
interface Engine_Specific_Backup {

	/**
	 * Create a backup.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $backup_root The storage to which to write files.
	 * @param int            $max_time    The maximum amount of time in seconds the backup
	 *                                    should run before terminating.
	 *                                    In practice the amount of time after which no new atomic
	 *                                    type of backup should start.
	 *
	 * @return array An unstructured data that the engine need for restoring the backup.
	 *
	 * @throws \Exception if the backup creation fails.
	 * @throws Timeout_Exception If the backup timeed out and need more "time slices" to complete.
	 */
	public static function backup( Backup_Storage $storage, int $max_time ): array;

	/**
	 * Prepare to restore data by doing data validation, permission checks, and whatever
	 * else can be done before the restore is run to have a better chance that the restore itself will succeed
	 * and complete faster.
	 *
	 * @since 1.0.0
	 *
	 * \calmpress\credentials\Credentials $write_credentials The credentials with which it should be possible
	 *                                    to write into code directories.
	 * @param Backup_Storage $storage     The storage from which to retrieve the backuped files.
	 * @param array          $data        An unstructured data that the engine need for restoring the backup.
	 * @param int            $max_time    The maximum amount of time in seconds the function
	 *                                    should run before terminating.
	 *                                    In practice the amount of time after which no new atomic
	 *                                    type of preperations should start.
	 * 
	 * @throws Restore_Exception If restore process fails.
	 * @throws Timeout_Exception If the backup timeed out and need more "time slices" to complete.
	 */
	public static function prepare_restore( \calmpress\credentials\Credentials $write_credentials,
	                                        Backup_Storage $storage,
											array $data,
											int $max_time );

	/**
	 * Restore from a backup based on the engine specific data.
	 *
	 * @since 1.0.0
	 *
	 * \calmpress\credentials\Credentials $write_credentials The credentials with which it should be possible
	 *                                    to write into code directories.
	 * @param Backup_Storage $storage     The storage from which to retrieve the backuped files.
	 * @param array          $data        An unstructured data that the engine need for restoring the backup.
	 *
	 * @throws Restore_Exception If restore process fails.
	 */
	public static function restore( \calmpress\credentials\Credentials $write_credentials,
	                                Backup_Storage $storage,
									array $data );

	/**
	 * Human redable description of the engine. Should not contain HTML (it will be escaped),
	 * and be translated where appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public static function description() : string;

	/**
	 * A unique identiier of the engine. It mey be used in the backup meta files, therefor
	 * best to have it semantically meaningful.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public static function identifier(): string;
}