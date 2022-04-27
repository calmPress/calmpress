<?php
/**
 * Implementation of class for backups being managed in the admin.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A representation of a specific backup.
 *
 * @since 1.0.0
 */
class Managed_Backup {

	private $backup;
	private $uniqe_id;

    public function __construct( Backup $backup, string $uniqe_id) {

    }

    public function time_created() : DateTimeImmutable {
		return $this->backup->time_created();
	}

    public function description() : string {
		return $this->backup->description();
	}

    public function uniqe_id(){
		return $this->unique_id;
    }

	public function type() : string {

	}

	public function storage() : Backup_Storage {
		return $this->backup->storage();
	}
}