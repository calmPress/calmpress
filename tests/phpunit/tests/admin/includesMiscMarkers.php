<?php
/**
 * Tests for marked file sections.
 *
 * @package calmPress
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

require_once ABSPATH . 'wp-admin/includes/misc.php';

/**
 * Marked file section reading and comparison tests.
 *
 * @since calmPress 1.0.0
 */
class Tests_Admin_Misc_Markers extends WP_UnitTestCase {

	/**
	 * Isolated file used by each test.
	 *
	 * @since calmPress 1.0.0
	 * @var string
	 */
	private string $filename;

	/**
	 * Create an isolated marker file.
	 *
	 * @since calmPress 1.0.0
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->filename = tempnam( sys_get_temp_dir(), 'calm-section-' );
	}

	/**
	 * Remove the isolated marker file.
	 *
	 * @since calmPress 1.0.0
	 */
	protected function tearDown(): void {

		if ( file_exists( $this->filename ) ) {
			unlink( $this->filename );
		}

		parent::tearDown();
	}

	/**
	 * Reading a section preserves its comments, inner markers, blank lines, and spaces.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @dataProvider file_line_endings
	 *
	 * @param string $line_ending File line ending.
	 */
	public function test_extract_marked_section_preserves_nested_user_section( string $line_ending ): void {
		$content = "# BEGIN User\n# User comment\n  Header set Example value\n# END User\n\nRewriteEngine On\n";
		$file    = "# Outside section\n# BEGIN WordPress\n" . $content . "\n# END WordPress\n# Outside section\n";
		file_put_contents( $this->filename, str_replace( "\n", $line_ending, $file ) );

		$this->assertSame( explode( "\n", $content ), extract_marked_section( $this->filename, 'WordPress' ) );
		$this->assertTrue( marker_section_matches( $this->filename, 'WordPress', str_replace( "\n", "\r\n", $content ) ) );
	}

	/**
	 * Supply the supported file line endings.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array[] File line endings.
	 */
	public static function file_line_endings(): array {
		return [
			'LF'   => [ "\n" ],
			'CRLF' => [ "\r\n" ],
			'CR'   => [ "\r" ],
		];
	}

	/**
	 * Comparisons detect changed comments, directives, and whitespace.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @dataProvider section_content_differences
	 *
	 * @param string $expected Expected section content.
	 */
	public function test_marker_section_matches_detects_content_differences( string $expected ): void {
		file_put_contents( $this->filename, "# BEGIN WordPress\n# Comment\nRewriteEngine On\n# END WordPress\n" );

		$this->assertFalse( marker_section_matches( $this->filename, 'WordPress', $expected ) );
	}

	/**
	 * Supply content changes that remain significant after normalizing line endings.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return array[] Changed expected content.
	 */
	public static function section_content_differences(): array {
		return [
			'changed comment'    => [ "# Changed comment\nRewriteEngine On" ],
			'missing comment'    => [ 'RewriteEngine On' ],
			'changed directive'  => [ "# Comment\nRewriteEngine Off" ],
			'leading space'      => [ " # Comment\nRewriteEngine On" ],
			'trailing space'     => [ "# Comment \nRewriteEngine On" ],
			'blank line'         => [ "# Comment\n\nRewriteEngine On" ],
			'final blank line'   => [ "# Comment\nRewriteEngine On\n" ],
		];
	}

	/**
	 * Missing sections have empty content even when other marked sections exist.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_extract_marked_section_returns_empty_for_missing_section(): void {
		file_put_contents( $this->filename, "# BEGIN Other\n# Comment\n# END Other\n" );

		$this->assertSame( [], extract_marked_section( $this->filename, 'WordPress' ) );
		$this->assertTrue( marker_section_matches( $this->filename, 'WordPress', '' ) );
	}

	/**
	 * Missing files have empty section content.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_extract_marked_section_returns_empty_for_missing_file(): void {
		unlink( $this->filename );

		$this->assertSame( [], extract_marked_section( $this->filename, 'WordPress' ) );
	}

	/**
	 * Rules written through the marker API compare equal with nested User comments.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_marker_section_matches_accepts_rules_saved_through_marker_api(): void {
		$rules = "\n# BEGIN User\n# User comment\n# END User\n\nRewriteEngine On\n";

		$this->assertTrue( insert_with_markers( $this->filename, 'WordPress', explode( "\n", $rules ) ) );
		$this->assertTrue( marker_section_matches( $this->filename, 'WordPress', $rules ) );
	}

	/**
	 * The inherited extraction API retains its filtering of comment-prefixed lines.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_extract_from_markers_preserves_comment_filtering(): void {
		file_put_contents( $this->filename, "# BEGIN WordPress\r\n# Comment\r\nRewriteEngine On\r\n\r\n# END WordPress\r\n" );

		$this->assertSame( [ 'RewriteEngine On', '' ], extract_from_markers( $this->filename, 'WordPress' ) );
	}

	/**
	 * Unreadable files return an empty array through both marker-reading APIs.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_marker_readers_return_empty_for_unreadable_file(): void {
		file_put_contents( $this->filename, "# BEGIN WordPress\nRewriteEngine On\n# END WordPress\n" );
		chmod( $this->filename, 0000 );
		clearstatcache( true, $this->filename );

		try {

			if ( is_readable( $this->filename ) ) {
				$this->markTestSkipped( 'The process can read files despite read permissions.' );
			}

			$this->assertSame( [], extract_marked_section( $this->filename, 'WordPress' ) );
			$this->assertSame( [], extract_from_markers( $this->filename, 'WordPress' ) );
		} finally {
			chmod( $this->filename, 0600 );
		}
	}

}
