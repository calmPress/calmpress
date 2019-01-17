<?php

/**
 * Test functions that fetch stuff from the theme directory
 *
 * @group themes
 */
class Tests_Theme_ThemeDir extends WP_UnitTestCase {

	function setUp() {
		parent::setUp();
		$this->theme_root = DIR_TESTDATA . '/themedir1';

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		add_filter('theme_root', array($this, '_theme_root'));
		add_filter( 'stylesheet_root', array($this, '_theme_root') );
		add_filter( 'template_root', array($this, '_theme_root') );
		// clear caches
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	function tearDown() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		remove_filter('theme_root', array($this, '_theme_root'));
		remove_filter( 'stylesheet_root', array($this, '_theme_root') );
		remove_filter( 'template_root', array($this, '_theme_root') );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tearDown();
	}

	// replace the normal theme root dir with our premade test dir
	function _theme_root($dir) {
		return $this->theme_root;
	}

	function test_wp_get_theme_with_non_default_theme_root() {
		$this->assertFalse( wp_get_theme( 'sandbox', $this->theme_root )->errors() );
		$this->assertFalse( wp_get_theme( 'sandbox' )->errors() );
	}

	/**
	 * @ticket 28662
	 */
	function test_theme_dir_slashes() {
		$size = count( $GLOBALS['wp_theme_directories'] );

		@mkdir( WP_CONTENT_DIR . '/themes/foo' );
		@mkdir( WP_CONTENT_DIR . '/themes/foo-themes' );

		$this->assertFileExists( WP_CONTENT_DIR . '/themes/foo' );
		$this->assertFileExists( WP_CONTENT_DIR . '/themes/foo-themes' );

		register_theme_directory( '/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( 'themes/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( '/foo/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( 'foo/' );

		$this->assertCount( $size, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( 'themes/foo/' );

		$this->assertCount( $size + 1, $GLOBALS['wp_theme_directories'] );

		register_theme_directory( WP_CONTENT_DIR . '/foo-themes/' );

		$this->assertCount( $size + 1, $GLOBALS['wp_theme_directories'] );

		foreach ( $GLOBALS['wp_theme_directories'] as $dir ) {
			$this->assertNotEquals( '/', substr( $dir, -1 ) );
		}

		rmdir( WP_CONTENT_DIR . '/themes/foo' );
		rmdir( WP_CONTENT_DIR . '/themes/foo-themes' );
	}
}
