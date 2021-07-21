<?php
/**
 * Implementation of a bacup manager class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A local backup class representing the backups stored at the default core backup folder.
 *
 * @since 1.0.0
 */
class Backup_Manager {

	private $storages = [];

	/**
	 * Initialize the manager, mainly give a backup storages a chance to register.
	 */
	public function __construct() {
/*
		$local = new Local_Storage();
		$this->storages[ $local->identifier ] = $local;
*/
		// Trigger for additional storages to register.
		do_action( 'calm_backup_manager_init' );
	}

    public function register_storage( Backup_Storage $storage ) {
        if ( isset( $this->storages[ $storage->identifier ] ) ) {
            trigger_error( 'An attempt to register storage with an already used identifier' );
            return;
        }
        $this->storages[ $storage->identifier ] = $storage;
	}

    public function existing_backups() {
        $backups = [];

        foreach ( $this->storages as $storage ) {
            foreach ( $storage->backups as $backup ) {
                $backups[] = $backup;
            }
        }

        return $backups;
    }

	public function backups() {

	}
}