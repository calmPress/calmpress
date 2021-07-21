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
    private $container = [];

    public function add( calmpress\backup\Backup $backup ) {
        $this->container[] = $backup;
    }

    public function as_array() : array {
        return $this->container;
    }
}