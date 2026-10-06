<?php
/**
 * Definition of a duplicate backup section exception.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * Thrown when a storage already contains a section with an identity.
 *
 * @since 1.0.0
 */
class Backup_Section_Already_Exists_Exception extends \RuntimeException {
}
