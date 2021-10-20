<?php
/**
 * Implementation of exception of an "out of execution time" exception.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\calmpress;

/**
 * An exception for an "out of execution time" exception. Used to indicate the specific
 * exception cause with no additional modifications.
 *
 * @since 1.0.0
 */
class Timeout_Exception extends \RuntimeException{
}
