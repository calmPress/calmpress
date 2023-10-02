<?php
/**
 * Unit tests covering file logger
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

class File_Logger_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor creates the directory.
	 *
	 * @since 1.0.0
	 */
	function test_constructor() {
		if ( is_dir( WP_CONTENT_DIR . '/.private/logs/test' ) ) {
			$files = glob( WP_CONTENT_DIR . '/.private/logs/test/*' ); 
			foreach( $files as $file ) {
				unlink( $file );
			}
			rmdir( WP_CONTENT_DIR . '/.private/logs/test' );
		}

		$logger = new \calmpress\logger\File_Logger( WP_CONTENT_DIR . '/.private/logs/test', 'tost' );
		$this->assertTrue( is_dir( WP_CONTENT_DIR . '/.private/logs/test' ) );
	}

	/**
	 * Test that log_message creates a file with a new line when non exists
	 * and appends content when file exists.
	 *
	 * @since 1.0.0
	 */
	function test_log_message() {
		if ( is_dir( WP_CONTENT_DIR . '/.private/logs/test' ) ) {
			$files = glob( WP_CONTENT_DIR . '/.private/logs/test/*' ); 
			foreach( $files as $file ) {
				unlink( $file );
			}
			rmdir( WP_CONTENT_DIR . '/.private/logs/test' );
		}
		
		$logger = new \calmpress\logger\File_Logger( WP_CONTENT_DIR . '/.private/logs/test', 'tost' );

		// going to depend on current date which is risky thing to do when running
		// the test at midnight but that seems the best that can be done.
		$date = $date = gmdate( 'Y-m-d' );
		$logger->log_message( 'test' );
		// file should be created.
		$this->assertTrue( is_file( WP_CONTENT_DIR . '/.private/logs/test/tost-' . $date . '.log' ) );
		// file should have content. we don't test for the content as content is hard
		// to test for and might change enough to break the tests without breaking the
		// intended information in the log.
		$filesize = filesize( WP_CONTENT_DIR . '/.private/logs/test/tost-' . $date . '.log' );
		$this->assertNotSame( 0, $filesize );

		// Second log should result with bigger file.
		$logger->log_message( 'test' );
		// file state chance needs to be cleared to avoid getting same value.
		clearstatcache();
		$filesize2 = filesize( WP_CONTENT_DIR . '/.private/logs/test/tost-' . $date . '.log' );
		$this->assertNotSame( $filesize, $filesize2 );
	}

	/**
	 * test purge_old_log_entries deletes old log files.
	 *
	 * @since 1.0.0
	 */
	public function test_purge_old_log_entries() {
		$logger = new \calmpress\logger\File_Logger( WP_CONTENT_DIR . '/.private/logs/test', 'tost' );
		
		// creat files to test against.

		// current file to keep
		file_put_contents( WP_CONTENT_DIR . '/.private/logs/test/tost-2023-10-3.log', '' );

		// old file to remove
		file_put_contents( WP_CONTENT_DIR . '/.private/logs/test/tost-2023-09-3.log', '' );
		touch( WP_CONTENT_DIR . '/.private/logs/test/tost-2023-09-3.log', time() - 2 * DAY_IN_SECONDS );

		// unrelated file to keep
		file_put_contents( WP_CONTENT_DIR . '/.private/logs/test/stake-2023-10-3.log', '' );
		touch( WP_CONTENT_DIR . '/.private/logs/test/stake-2023-09-3.log', time() - 2 * DAY_IN_SECONDS );

		$logger->purge_old_log_entries( 1 );
		$this->assertTrue( file_exists( WP_CONTENT_DIR . '/.private/logs/test/tost-2023-10-3.log' ) );
		$this->assertTrue( file_exists( WP_CONTENT_DIR . '/.private/logs/test/stake-2023-09-3.log' ) );
		$this->assertFalse( file_exists( WP_CONTENT_DIR . '/.private/logs/test/tost-2023-09-3.log' ) );
	}
}