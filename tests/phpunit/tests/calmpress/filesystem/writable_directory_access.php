<?php
/**
 * Tests for directory write-access validation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\filesystem\Writable_Directory_Access;

/**
 * Stream transport that permits file creation but rejects deletion.
 *
 * @since 1.0.0
 */
class Test_Create_Without_Delete_Stream {

	/**
	 * Context supplied by PHP when opening the stream.
	 *
	 * @since 1.0.0
	 * @var resource
	 */
	public $context;

	/**
	 * Whether the probe was opened.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public static bool $opened = false;

	/**
	 * Whether probe deletion was attempted.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public static bool $deletion_attempted = false;

	/**
	 * Allow PHP to create the probe stream.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Stream path.
	 * @param string $mode Requested mode.
	 * @param int $options Stream options.
	 * @param string|null $opened_path Resolved path.
	 *
	 * @return bool True to permit creation.
	 */
	public function stream_open( string $path, string $mode, int $options, ?string &$opened_path ): bool {
		self::$opened = true;

		return true;
	}

	/**
	 * Deny deletion independently of successful stream creation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Stream path.
	 *
	 * @return bool False to simulate missing delete permission.
	 */
	public function unlink( string $path ): bool {
		self::$deletion_attempted = true;

		return false;
	}
}

/**
 * Directory write-access validation tests.
 *
 * @since 1.0.0
 */
class WP_Test_Writable_Directory_Access extends WP_UnitTestCase {

	use Writable_Directory_Access;

	/**
	 * Directory validation checks write access and removes its temporary probe.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_checks_write_access(): void {
		$created_content_directory = ! is_dir( WP_CONTENT_DIR );

		if ( $created_content_directory ) {
			mkdir( WP_CONTENT_DIR, 0755, true );
		}

		$root = WP_CONTENT_DIR . '/connection-directory-' . wp_generate_uuid4();
		mkdir( $root );

		try {
			$credentials = new \calmpress\filesystem\Local_Filesystem_Access();
			$result = self::validate_directory_write_access( $credentials, $root );

			$this->assertTrue( $result );
			$this->assertSame( [ '.', '..' ], scandir( $root ) );
		} finally {
			rmdir( $root );

			if ( $created_content_directory ) {
				rmdir( WP_CONTENT_DIR );
			}

		}
	}

	/**
	 * Directory access failures retain the directory-probe error code.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_reports_directory_access_failure(): void {
		$transport = $this->createMock( \calmpress\filesystem\Remote_Filesystem_Access::class );
		$transport->method( 'stream_url_from_path' )->willReturn( sys_get_temp_dir() . '/missing-' . wp_generate_uuid4() . '/probe' );
		$transport->method( 'stream_context' )->willReturn( stream_context_create() );
		$result = self::validate_directory_write_access( $transport, ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'connection_directory_not_writable', $result->get_error_code() );
		$this->assertStringContainsString( ABSPATH, $result->get_error_message() );
		$this->assertSame( [ 'directory' => ABSPATH ], $result->get_error_data() );
	}

	/**
	 * A successfully created probe that cannot be deleted produces a directory-specific error.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_reports_probe_deletion_failure(): void {
		$protocol = 'calmpress-create-without-delete';
		Test_Create_Without_Delete_Stream::$opened = false;
		Test_Create_Without_Delete_Stream::$deletion_attempted = false;
		$this->assertTrue( stream_wrapper_register( $protocol, Test_Create_Without_Delete_Stream::class ) );

		try {
			$credentials = $this->createMock( \calmpress\filesystem\Filesystem_Access::class );
			$credentials->method( 'stream_url_from_path' )->willReturn( $protocol . '://directory/probe' );
			$credentials->method( 'stream_context' )->willReturn( stream_context_create() );
			$directory = '/installation/target';
			$result = self::validate_directory_write_access( $credentials, $directory );

			$this->assertTrue( Test_Create_Without_Delete_Stream::$opened );
			$this->assertTrue( Test_Create_Without_Delete_Stream::$deletion_attempted );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'connection_file_delete_failed', $result->get_error_code() );
			$this->assertStringContainsString( 'Failed to delete files from directory', $result->get_error_message() );
			$this->assertStringContainsString( $directory, $result->get_error_message() );
			$data = $result->get_error_data();
			$this->assertSame( $directory, $data['directory'] );
			$this->assertStringStartsWith( $directory . '/.calmpress-access-', $data['file'] );
			$this->assertStringContainsString( 'Temporary file ' . basename( $data['file'] ) . ' may have been left in it.', $result->get_error_message() );
			$this->assertStringNotContainsString( $data['file'], $result->get_error_message() );
		} finally {
			stream_wrapper_unregister( $protocol );
		}
	}

	/**
	 * Authentication failures retain their user-facing connection error.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_reports_authentication_failure(): void {
		$failure = new \calmpress\filesystem\FTP_Connection_Exception( 'Authentication failed.', \calmpress\filesystem\FTP_Connection_Exception::AUTHENTICATION_FAILED );
		$transport = $this->createMock( \calmpress\filesystem\Remote_Filesystem_Access::class );
		$transport->method( 'stream_url_from_path' )->willReturn( ABSPATH . '/probe' );
		$transport->method( 'stream_context' )->willThrowException( $failure );
		$result = self::validate_directory_write_access( $transport, ABSPATH );

		$this->assertEquals( $failure->to_wp_error(), $result );
	}


	/**
	 * Runtime connection failures retain their message in a user-facing error.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_reports_runtime_failure(): void {
		$failure = new \RuntimeException( 'Unexpected transport failure.' );
		$transport = $this->createMock( \calmpress\filesystem\Remote_Filesystem_Access::class );
		$transport->method( 'stream_url_from_path' )->willReturn( ABSPATH . '/probe' );
		$transport->method( 'stream_context' )->willThrowException( $failure );
		$result = self::validate_directory_write_access( $transport, ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'connection_failed', $result->get_error_code() );
		$this->assertSame( $failure->getMessage(), $result->get_error_message() );
	}
}
