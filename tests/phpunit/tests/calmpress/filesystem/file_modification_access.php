<?php
/**
 * Tests file modification access checks.
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\filesystem\File_Modification_Access;

/**
 * Covers modification access for the current PHP process.
 *
 * @since 1.0.0
 */
class WP_Test_File_Modification_Access extends WP_UnitTestCase {

	/**
	 * Writable files can be modified by the current process.
	 *
	 * @since 1.0.0
	 */
	public function test_can_modify_path_detects_writable_file(): void {
		$file = sys_get_temp_dir() . '/modifiable-path-' . wp_generate_uuid4();
		file_put_contents( $file, 'contents' );

		try {
			$this->assertTrue( File_Modification_Access::can_modify_path( $file ) );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Verify a writable installation root allows the current process to modify installed code.
	 *
	 * @since 1.0.0
	 */
	public function test_can_modify_installed_code_detects_writable_root(): void {
		$root = sys_get_temp_dir() . '/core-writable-' . wp_generate_uuid4();
		mkdir( $root );

		try {
			$this->assertTrue( File_Modification_Access::can_modify_installed_code( $root ) );
		} finally {
			rmdir( $root );
		}
	}

	/**
	 * Verify an owner can make a read-only installation root writable on Unix.
	 *
	 * @since 1.0.0
	 */
	public function test_can_modify_installed_code_detects_owned_read_only_root(): void {
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$this->markTestSkipped( 'Unix ownership is not available on Windows.' );
		}

		$root = sys_get_temp_dir() . '/core-owned-' . wp_generate_uuid4();
		mkdir( $root, 0700 );
		chmod( $root, 0500 );

		try {
			if ( wp_is_writable( $root ) ) {
				$this->markTestSkipped( 'The test process can write to a read-only directory.' );
			}

			$this->assertTrue( File_Modification_Access::can_modify_installed_code( $root ) );
		} finally {
			chmod( $root, 0700 );
			rmdir( $root );
		}
	}

}
