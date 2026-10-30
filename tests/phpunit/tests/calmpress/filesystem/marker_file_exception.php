<?php
/**
 * Tests marker file failure reporting.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Marker_File_Exception;

/**
 * Tests operation-specific errors from Marker_File_Exception.
 *
 * @since 1.0.0
 */
class WP_Test_Marker_File_Exception extends WP_UnitTestCase {

	/**
	 * Tests that to_wp_error identifies a failed read.
	 *
	 * @since 1.0.0
	 */
	public function test_to_wp_error_reports_read_failure(): void {
		$error = ( new Marker_File_Exception( 'Failed reading marker file.', Marker_File_Exception::READ_FAILED ) )->to_wp_error();

		$this->assertSame( 'marker_file_read_failed', $error->get_error_code() );
	}

	/**
	 * Tests that to_wp_error identifies a failed write.
	 *
	 * @since 1.0.0
	 */
	public function test_to_wp_error_reports_write_failure(): void {
		$error = ( new Marker_File_Exception( 'Failed writing marker file.', Marker_File_Exception::WRITE_FAILED ) )->to_wp_error();

		$this->assertSame( 'marker_file_write_failed', $error->get_error_code() );
	}
}
