<?php
/**
 * Implementation a specific exception for when backup restore fails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

/**
 * An implementation a specific exception for when backup restore fails.
 *
 * @since 1.0.0
 */
class Restore_Exception extends \Exception {

	/*
	 * Following constatnt are used as the exception codes to make it easier to detect
	 * the reason for the exception in the calling code.
	 */

	public const OTHER = 0;                   // Some unspecified reason.
	public const CURROPTED_DATA = 1;          // The data given make no sense.
	public const MISMATCHED_DATA_VERSION =2 ; // The engine can not handle the data as it is not compatible with
	                                          // the specific format.
	/**
	 * The engine ID rasing the exception.
	 *
	 * @var string
	 */
	protected string $engine_id;

	/**
	 * Construct the exception.
	 *
	 * @param string $engine_id The engine id at which the exception happened.
	 * @param int    $code      The reason value for the exception. One of the consts defined above.
	 * @param string $message   The message to associate with the exception.
	 */
	public function __construct( string $engine_id, int $code, string $message ) {
		parent::__construct( $message, $code );
		$this->engine_id = $engine_id;
	}

	public function engine_id(): string {
		return $this->engine_id;
	}
}
