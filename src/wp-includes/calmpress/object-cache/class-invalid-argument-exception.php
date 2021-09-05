<?php
/**
 * Implementation of the invalid argument exception.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * Exception raised when invalid argument is passed to a cache API.
 *
 * When an invalid argument is passed, it must throw an exception which implements
 * this interface.
 */
class Invalid_Argument_Exception extends \Exception implements \Psr\SimpleCache\InvalidArgumentException {
}