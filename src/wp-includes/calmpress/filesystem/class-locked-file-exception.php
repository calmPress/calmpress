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
	 * When the exception has this code it indicates that it was raised
	 * because the "physical" operation failed.
	 *
	 * @var int
	 */
	const OPERATION_FAILED = 1;

	/**
	 * When the exception has this code it indicates that it was raised
	 * because the path supplied is not absolute.
	 *
	 * @var int
	 */
	const PATH_NOT_ABSOLUTE = 2;

	/**
	 * When the exception has this code it indicates that it was raised
	 *          because the path supplied is not accessible.
	 *
	 * @var int
	 */
	const PATH_NOT_ACESSABLE = 3;

	/**
	 * The path of the file for which the exception was raised.
	 *
	 * @var string
	 */
	public $path;

	/**
	 * Create the exception object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The textual message associated with the exception.
	 * @param int    $code The code identifying the type of the exception.
	 * @param string $path The file path for which the exception was raised.
	 */
	public function __construct( string $message, int $code, string $path ) {
		parent::__construct( $message, $code );
		$this->path = $path;
	}

	/**
	 * The exception's message.
	 *
	 * @since 1.0.0
	 *
	 * @retumr string The exception's message.
	 */
	public function message() {
		return $this->message;
	}

	/**
	 * The exception's code.
	 *
	 * @since 1.0.0
	 *
	 * @retumr int The exception's code.
	 */
	public function code() {
		return $this->code;
	}

	/**
	 * The file path for which the exception was raised.
	 *
	 * @since 1.0.0
	 *
	 * @retumr string The file path for which the exception was raised.
	 */
	public function path() {
		return $this->path;
	}
}
