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
interface Backup_Engine {
    public function backup( string $backupfile ) : Backup_meta;
}