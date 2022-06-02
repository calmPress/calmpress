<?php
/**
 * Implementation of a backups container
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * A container of backups, main usage is to generate a type checked array of backups.
 *
 * @since 1.0.0
 */
class Backup_Container {

    /**
     * Holds the backup objects.
     *
     * @var Backup[]
     */
    private array $container = [];

    public function add( Backup $backup ) {
        $this->container[] = $backup;
    }

    public function as_array() : array {
        return $this->container;
    }
}