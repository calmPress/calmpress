<?php
/**
 * Observer for backup manager initialization.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

use calmpress\observer\Observer;

/**
 * Registers backup engines and storages with a new manager.
 *
 * @since 1.0.0
 */
interface Backup_Manager_Initialization_Observer extends Observer {
	/**
	 * Register engines and storages with a new backup manager.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Manager $manager The manager being initialized.
	 *
	 * @return void
	 */
	public function register_with( Backup_Manager $manager ): void;
}
