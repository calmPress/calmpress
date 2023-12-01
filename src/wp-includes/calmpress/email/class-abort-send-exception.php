<?php
/**
 * Implementation of an the Abort_Send_Exception exception.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Used to provide a unique exception type which can be used by mutators to signal
 * that email should not be sent at all.
 *
 * @since 1.0.0
 */
class Abort_Send_Exception extends \Exception {}
