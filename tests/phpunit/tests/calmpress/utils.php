<?php
/**
 * Unit tests covering function in the utils.php file
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\utils;

use function calmpress\utils\base64URL_decode;
use function calmpress\utils\base64URL_encode;
use function calmpress\utils\insert_style_into_html_head;

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

class WP_Test_Utils extends WP_UnitTestCase {

	/**
	 * Verify empty_directory removes nested contents while preserving the root directory.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_directory_preserves_root() {
		$directory = get_temp_dir() . 'empty-directory-' . wp_generate_uuid4();
		mkdir( $directory . '/nested', 0777, true );
		file_put_contents( $directory . '/file', 'contents' );
		file_put_contents( $directory . '/nested/file', 'contents' );

		utils\empty_directory( $directory );

		$this->assertDirectoryExists( $directory );
		$this->assertSame( array(), glob( $directory . '/*' ) );
		rmdir( $directory );
	}

	/**
	 * Verify delete_directory removes a complete nested directory tree.
	 *
	 * @since 1.0.0
	 */
	public function test_delete_directory_removes_root() {
		$directory = get_temp_dir() . 'delete-directory-' . wp_generate_uuid4();
		mkdir( $directory . '/nested', 0777, true );
		file_put_contents( $directory . '/nested/file', 'contents' );

		utils\delete_directory( $directory );

		$this->assertDirectoryDoesNotExist( $directory );
	}

	/**
	 * Verify directory deletion utilities reject paths which are not directories.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_directory_rejects_non_directory() {
		$file = get_temp_dir() . 'empty-directory-file-' . wp_generate_uuid4();
		file_put_contents( $file, 'contents' );

		try {
			utils\empty_directory( $file );
			$this->fail( 'Emptying a file path must fail.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'The path is not a directory: ' . $file, $exception->getMessage() );
		} finally {
			unlink( $file );
		}
	}

	/**
	 * Verify empty_directory removes a symbolic link without touching its target.
	 *
	 * @since 1.0.0
	 */
	public function test_empty_directory_does_not_follow_symbolic_links() {
		$directory = get_temp_dir() . 'empty-directory-links-' . wp_generate_uuid4();
		$target    = get_temp_dir() . 'empty-directory-target-' . wp_generate_uuid4();
		mkdir( $directory );
		mkdir( $target );
		file_put_contents( $target . '/file', 'contents' );
		if ( ! @symlink( $target, $directory . '/link' ) ) {
			rmdir( $directory );
			unlink( $target . '/file' );
			rmdir( $target );
			$this->markTestIncomplete( 'Symbolic links are unavailable on this system.' );
		}

		utils\empty_directory( $directory );

		$this->assertFileExists( $target . '/file' );
		$this->assertFileDoesNotExist( $directory . '/link' );
		rmdir( $directory );
		unlink( $target . '/file' );
		rmdir( $target );
	}

	/**
	 * Test the enqueue_inline_style_once function.
	 *
	 * @since 1.0.0
	 */
	function test_enqueue_inline_style_once() {

		utils\enqueue_inline_style_once( 'handle', 'a {color:red}' );

		// Inspect that the inline style was enqueued
		$wp_styles = wp_styles();
		$this->assertTrue( wp_style_is( 'handle', 'enqueued' ) );

		// Check that the inline style is added
		$this->assertNotEmpty( $wp_styles->get_data( 'handle', 'after' ) );

		// Call the function again to ensure the style is not enqueued twice
		utils\enqueue_inline_style_once( 'handle', 'a {color:red}' );

		// Confirm that it's still only enqueued once
		$inline_styles = $wp_styles->get_data( 'handle', 'after' );
		$this->assertCount( 1, $inline_styles );

		wp_dequeue_style( 'handle' );
	}

	/**
	 * Test base64URL decode an encode
	 * 
	 * @since 1.0.0
	 */
	function test_base64url() {

		// simple string
		$en = base64URL_encode( 'dummy' );
		$this->assertSame( 'dummy', base64URL_decode( $en ) );

		// A string with some invalid URL characters when base64 encoded.
		$en = base64URL_encode( "\xFA\xFB\xF" );
		$this->assertSame( "\xFA\xFB\xF", base64URL_decode( $en ) );

		// String not matching base64URL format.
		$de = base64URL_decode( 'pQECAyYgASFYIF6t9Oa1Z3ZL3mYjiHU5j9z6Bk3j+8m5UwxyZZ9S_ZUIlgg==INVALID==' );
		$this->assertFalse( $de );
	}

	/**
	 * test insert_style_into_html_head actually inserts enqueued styles
	 */
	function test_insert_style_into_html_head() {
		wp_register_style( 'test', false ); // 'false' means no external file, just inline
		wp_add_inline_style( 'test', 'a {color:red}' );
		wp_enqueue_style( 'test' );

		$html = '<head></head><body></body>';
		$t = insert_style_into_html_head( $html );
		$this->assertStringContainsString( 'a {color:red}', $t );
		wp_dequeue_style( 'test' );
	}

	/**
	 * Directory validation checks write access and removes its temporary probe.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_checks_write_access(): void {
		$root = sys_get_temp_dir() . '/connection-directory-' . wp_generate_uuid4();
		mkdir( $root );

		try {
			$credentials = $this->createMock( \calmpress\credentials\Connection_Credentials::class );
			$credentials->method( 'stream_url_from_path' )->willReturn( $root . '/probe' );
			$credentials->method( 'stream_context' )->willReturn( stream_context_create() );
			$result = utils\validate_directory_write_access( $credentials, $root );

			$this->assertTrue( $result );
			$this->assertSame( [ '.', '..' ], scandir( $root ) );
		} finally {
			rmdir( $root );
		}
	}

	/**
	 * Directory access failures retain the directory-probe error code.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_reports_directory_access_failure(): void {
		$transport = $this->createMock( \calmpress\credentials\Connection_Credentials::class );
		$transport->method( 'stream_url_from_path' )->willReturn( sys_get_temp_dir() . '/missing-' . wp_generate_uuid4() . '/probe' );
		$transport->method( 'stream_context' )->willReturn( stream_context_create() );
		$result = utils\validate_directory_write_access( $transport, ABSPATH );

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
			$credentials = $this->createMock( \calmpress\credentials\Credentials::class );
			$credentials->method( 'stream_url_from_path' )->willReturn( $protocol . '://directory/probe' );
			$credentials->method( 'stream_context' )->willReturn( stream_context_create() );
			$directory = '/installation/target';
			$result = utils\validate_directory_write_access( $credentials, $directory );

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
		$failure = new \calmpress\credentials\FTP_Connection_Exception( 'Authentication failed.', \calmpress\credentials\FTP_Connection_Exception::AUTHENTICATION_FAILED );
		$transport = $this->createMock( \calmpress\credentials\Connection_Credentials::class );
		$transport->method( 'stream_url_from_path' )->willReturn( ABSPATH . '/probe' );
		$transport->method( 'stream_context' )->willThrowException( $failure );
		$result = utils\validate_directory_write_access( $transport, ABSPATH );

		$this->assertEquals( $failure->to_wp_error(), $result );
	}


	/**
	 * Runtime connection failures retain their message in a user-facing error.
	 *
	 * @since 1.0.0
	 */
	public function test_validate_directory_write_access_reports_runtime_failure(): void {
		$failure = new \RuntimeException( 'Unexpected transport failure.' );
		$transport = $this->createMock( \calmpress\credentials\Connection_Credentials::class );
		$transport->method( 'stream_url_from_path' )->willReturn( ABSPATH . '/probe' );
		$transport->method( 'stream_context' )->willThrowException( $failure );
		$result = utils\validate_directory_write_access( $transport, ABSPATH );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'connection_failed', $result->get_error_code() );
		$this->assertSame( $failure->getMessage(), $result->get_error_message() );
	}

	/**
	 * Equivalent installation URLs produce the same autocomplete section.
	 *
	 * @since 1.0.0
	 */
	public function test_site_specific_autocomplete_section_normalizes_installation_url(): void {
		$expected = $this->autocomplete_section_for_site( 'https://example.test/site/', 'sftp' );

		$this->assertSame( $expected, $this->autocomplete_section_for_site( 'https://example.test/site', 'sftp' ) );
		$this->assertSame( $expected, $this->autocomplete_section_for_site( 'https://example.test/site/?ignored=1#fragment', 'sftp' ) );
		$this->assertMatchesRegularExpression( '/^section-calm[a-f0-9]{16}sftp$/', $expected );
	}

	/**
	 * Separate installations, sections, and case-sensitive paths have distinct identifiers.
	 *
	 * @since 1.0.0
	 */
	public function test_site_specific_autocomplete_section_distinguishes_installations_and_protocols(): void {
		$section = $this->autocomplete_section_for_site( 'https://example.test/site/', 'sftp' );

		$this->assertNotSame( $section, $this->autocomplete_section_for_site( 'https://example.test/other/', 'sftp' ) );
		$this->assertNotSame( $section, $this->autocomplete_section_for_site( 'https://example.test/Site/', 'sftp' ) );
		$this->assertNotSame( $section, $this->autocomplete_section_for_site( 'https://example.test/site/', 'ftp' ) );
	}

	/**
	 * Normal execution uses the configured URL instead of the request hostname and path.
	 *
	 * @since 1.0.0
	 */
	public function test_site_specific_autocomplete_section_uses_configured_site_url_outside_installation(): void {
		$expected = $this->autocomplete_section_for_site( 'https://configured.example/site/', 'sftp' );
		$server = $_SERVER;
		$was_installing = wp_installing( false );
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['HTTP_HOST'] = 'different.example';
		$_SERVER['REQUEST_URI'] = '/other/wp-admin/options.php';
		$configured_url = static function () {
			return 'https://configured.example/site/';
		};
		add_filter( 'pre_option_home', $configured_url );

		try {
			$this->assertSame( $expected, utils\site_specific_autocomplete_section( 'sftp' ) );
		} finally {
			remove_filter( 'pre_option_home', $configured_url );
			wp_installing( $was_installing );
			$_SERVER = $server;
		}
	}

	/**
	 * Whitespace in a section key is rejected before generating an autocomplete token.
	 *
	 * @since 1.0.0
	 * @dataProvider data_site_specific_autocomplete_section_whitespace
	 *
	 * @param string $key Section key containing whitespace.
	 */
	public function test_site_specific_autocomplete_section_rejects_whitespace( string $key ): void {
		$this->expectException( InvalidArgumentException::class );
		utils\site_specific_autocomplete_section( $key );
	}

	/**
	 * Supply keys with whitespace that could separate HTML autocomplete tokens.
	 *
	 * @since 1.0.0
	 *
	 * @return array Section keys containing whitespace.
	 */
	public static function data_site_specific_autocomplete_section_whitespace(): array {
		return [
			[ 'transfer credentials' ],
			[ "transfer\tcredentials" ],
			[ "transfer\ncredentials" ],
			[ "transfer\rcredentials" ],
			[ "transfer\fcredentials" ],
			[ "transfer\vcredentials" ],
		];
	}

	/**
	 * Exercise autocomplete identifiers in the request context of an installation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $site_url Installation URL used by the request.
	 * @param string $section_key Key distinguishing the requested section.
	 *
	 * @return string Autocomplete section identifier.
	 */
	private function autocomplete_section_for_site( string $site_url, string $section_key ): string {
		$server = $_SERVER;
		$was_installing = wp_installing( true );
		$parts = wp_parse_url( $site_url );
		$_SERVER['HTTPS'] = 'on';
		$_SERVER['HTTP_HOST'] = $parts['host'];

		if ( isset( $parts['port'] ) ) {
			$_SERVER['HTTP_HOST'] .= ':' . $parts['port'];
		}

		$_SERVER['REQUEST_URI'] = rtrim( $parts['path'] ?? '', '/' ) . '/wp-admin/setup-config.php';
		$_SERVER['SCRIPT_FILENAME'] = ABSPATH . 'wp-admin/setup-config.php';

		try {
			return utils\site_specific_autocomplete_section( $section_key );
		} finally {
			$_SERVER = $server;
			wp_installing( $was_installing );
		}
	}

}
