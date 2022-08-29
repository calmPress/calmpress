<?php
/**
 * Specification and implementation of an admin notices handler
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\admin;

class Admin_Notices_Handler {

	/**
	 * The data about the notices.
	 *
	 * Implementation uses it as an array of array with 3 arrays, one for success message,
	 * one for errors, and one for info.
	 * It is mixed as subclasses might want to use it differently.
	 *
	 * @var mixed
	 *
	 * @since 1.0.0
	 */
	protected $data;

	/**
	 * Construct an object based on previous data.
	 *
	 * Subclasses must implement with same signature.
	 *
	 * @param string $json The jsonified data to construct the object with.
	 *                     If empty string (default) creates an object with no data.
	 *
	 * @throws \Exception if $json is invalid or do not make sense.
	 */
	public function __construct( string $json = '' ) {
		if ( '' !== $json ) {
			$this->data = json_decode( $json, null, 512, JSON_THROW_ON_ERROR );

			// Validate data make composed of 3 arrays for success, info and error.
			foreach ( ['success', 'info', 'error'] as $type ) {
				if ( ! isset( $this->data->$type ) || ! is_array( $this->data->$type ) ) {
					throw new \Exception( 'json missing data for type ' . $type );
				}
			}
		} else {
			$this->data = new \stdClass();
			$this->data->success = [];
			$this->data->info    = [];
			$this->data->error   = [];
		}
	}

	/**
	 * Output the relevant notices.
	 *
	 * @since 1.0.0
	 */
	public function output_notices() {
		// Output notices with errors first, than infos, and success at last.
		foreach ( ['error', 'info', 'success' ] as $type ) {
			foreach ( $this->data->$type as $message ) {
				echo "<div class='notice notice-$type'><p>" . $message . "</p></div>";
			}
		}
	}

	/**
	 * The jsonified represantion of the object's data which can be used to recreate it.
	 *
	 * @return string The data in json format.
	 */
	public function json(): string {
		return json_encode( $this->data );
	}

	/**
	 * Add a success message.
	 *
	 * @param $message The message, in HTML escaped form.
	 *
	 * @since 1.0.0
	 */
	public function add_success_message( string $message ) {
		$this->data->success[] = $message;
	}

	/**
	 * Add an info message.
	 *
	 * @param $message The message, in HTML escaped form.
	 *
	 * @since 1.0.0
	 */
	public function add_info_message( string $message ) {
		$this->data->info[] = $message;
	}

	/**
	 * Add an error message.
	 *
	 * @param $message The message, in HTML escaped form.
	 *
	 * @since 1.0.0
	 */
	public function add_error_message( string $message ) {
		$this->data->error[] = $message;
	}
}
