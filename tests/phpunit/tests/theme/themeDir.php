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

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', self::THEME_ROOT );

		add_filter( 'theme_root', array( $this, 'filter_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_theme_root' ) );
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

	// Replace the normal theme root directory with our premade test directory.
	public function filter_theme_root( $dir ) {
		return self::THEME_ROOT;
	}

	public function test_wp_get_theme_with_non_default_theme_root() {
		$this->assertFalse( wp_get_theme( 'sandbox', self::THEME_ROOT )->errors() );
		$this->assertFalse( wp_get_theme( 'sandbox' )->errors() );
	}

	/**
	 * Tests that registering the default theme directory again succeeds without duplicating it.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_register_default_theme_directory() {
		$registered_directories = $GLOBALS['wp_theme_directories'];

		$this->assertTrue( register_theme_directory( get_theme_root() ) );
		$this->assertSame( $registered_directories, $GLOBALS['wp_theme_directories'] );
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
	 * Supplies a nonexistent theme root for the missing-directory test.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @return string A nonexistent theme root path.
	 */
	public function filter_missing_theme_root() {
		return WP_CONTENT_DIR . '/missing-theme-directory';
	}

	/**
	 * Tests that registering a missing default theme directory throws.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_register_missing_default_theme_directory_throws() {
		$this->assertDirectoryDoesNotExist( WP_CONTENT_DIR . '/missing-theme-directory' );

		add_filter( 'theme_root', [ $this, 'filter_missing_theme_root' ], 20 );

		$this->expectException( RuntimeException::class );

		register_theme_directory( get_theme_root() );
	}
}
