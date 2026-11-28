<?php

/**
 * Test functions that fetch stuff from the theme directory
 *
 * @group themes
 */
class Tests_Theme_ThemeDir extends WP_UnitTestCase {

	/**
	 * Theme root directory.
	 *
	 * @var string
	 */
	const THEME_ROOT = DIR_TESTDATA . '/themedir1';

	/**
	 * Original theme directory.
	 *
	 * @var string
	 */
	private $orig_theme_dir;

	public function set_up() {
		parent::set_up();

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		$GLOBALS['wp_theme_directories'] = [];
		register_theme_directory( self::THEME_ROOT );

		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tear_down();
	}

	public function test_wp_get_theme_with_non_default_theme_root() {
		$this->assertFalse( wp_get_theme( 'sandbox', self::THEME_ROOT )->errors() );
		$this->assertFalse( wp_get_theme( 'sandbox' )->errors() );
	}

	/**
	 * Tests that registering the same theme directory again succeeds.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_register_same_theme_directory() {
		$this->assertTrue( register_theme_directory( get_theme_root() ) );
	}

	/**
	 * Tests that registering another theme directory is rejected.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_register_additional_theme_directory_is_rejected() {
		$this->expectException( LogicException::class );

		register_theme_directory( WP_CONTENT_DIR . '/other-themes' );
	}

	/**
	 * Tests that the first registration rejects a nonexistent directory.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_register_missing_theme_directory_throws(): void {
		$GLOBALS['wp_theme_directories'] = [];
		$missing_directory             = WP_CONTENT_DIR . '/missing-theme-directory';

		$this->assertDirectoryDoesNotExist( $missing_directory );
		$this->expectException( RuntimeException::class );

		register_theme_directory( $missing_directory );
	}
}
