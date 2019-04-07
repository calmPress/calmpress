<?php
/**
 * Declaration and implementation of the exception class use by the locked file APIs.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * The locked file API exception class.
 *
 * Since 1.0.0
 */
class Locked_File_Exception extends \Exception {
	/**
	 * @var int When the exception has this code it indicates that it was raised
	 *          because the "physical" operation failed.
	 */
	const OPERATION_FAILED = 1;

	/**
	 * @var int When the exception has this code it indicates that it was raised
	 *          because the path supplied is not absolute.
	 */
	const PATH_NOT_ABSOLUTE = 2;

	/**
	 * @var int When the exception has this code it indicates that it was raised
	 *          because the path supplied is not accessible.
	 */
	const PATH_NOT_ACESSABLE = 3;
}
