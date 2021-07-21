<?php

/**
 * Test WP_Theme_JSON_Resolver class.
 *
 * @package WordPress
 * @subpackage Theme
 *
 * @since 5.8.0
 *
 * @group themes
 */
class Tests_Theme_wpThemeJsonResolver extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->theme_root = realpath( DIR_TESTDATA . '/themedir1' );

		$this->orig_theme_dir = $GLOBALS['wp_theme_directories'];

		// /themes is necessary as theme.php functions assume /themes is the root if there is only one root.
		$GLOBALS['wp_theme_directories'] = array( WP_CONTENT_DIR . '/themes', $this->theme_root );

		add_filter( 'theme_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'stylesheet_root', array( $this, 'filter_set_theme_root' ) );
		add_filter( 'template_root', array( $this, 'filter_set_theme_root' ) );
		// Clear caches.
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tearDown() {
		$GLOBALS['wp_theme_directories'] = $this->orig_theme_dir;
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
		parent::tearDown();
	}

	public function filter_set_theme_root() {
		return $this->theme_root;
	}

	public function filter_set_locale_to_polish() {
		return 'pl_PL';
	}

	/**
	 * @ticket 52991
	 */
	public function test_fields_are_extracted() {
		$actual = WP_Theme_JSON_Resolver::get_fields_to_translate();

		$expected = array(
			array(
				'path'    => array( 'settings', 'typography', 'fontSizes' ),
				'key'     => 'name',
				'context' => 'Font size name',
			),
			array(
				'path'    => array( 'settings', 'color', 'palette' ),
				'key'     => 'name',
				'context' => 'Color name',
			),
			array(
				'path'    => array( 'settings', 'color', 'gradients' ),
				'key'     => 'name',
				'context' => 'Gradient name',
			),
			array(
				'path'    => array( 'settings', 'color', 'duotone' ),
				'key'     => 'name',
				'context' => 'Duotone name',
			),
			array(
				'path'    => array( 'settings', 'blocks', '*', 'typography', 'fontSizes' ),
				'key'     => 'name',
				'context' => 'Font size name',
			),
			array(
				'path'    => array( 'settings', 'blocks', '*', 'color', 'palette' ),
				'key'     => 'name',
				'context' => 'Color name',
			),
			array(
				'path'    => array( 'settings', 'blocks', '*', 'color', 'gradients' ),
				'key'     => 'name',
				'context' => 'Gradient name',
			),
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * @ticket 52991
	 */
	public function test_switching_themes_recalculates_data() {
		// By default, the theme for unit tests is "default",
		// which doesn't have theme.json support.
		$default = WP_Theme_JSON_Resolver::theme_has_support();

		// Switch to a theme that does have support.
		switch_theme( 'block-theme' );
		$has_theme_json_support = WP_Theme_JSON_Resolver::theme_has_support();

		$this->assertFalse( $default );
		$this->assertTrue( $has_theme_json_support );
	}

}
