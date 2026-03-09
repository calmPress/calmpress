<?php

/**
 * test wp-includes/theme.php
 *
 * @group themes
 */
class Tests_Theme extends WP_UnitTestCase {
	protected $theme_slug = 'calmseventeen';
	protected $theme_name = 'calm Seventeen';
	protected $default_themes = array(
		'calmseventeen'
	);

	/**
	 * Original theme directory.
	 *
	 * @var string[]
	 */
	private $orig_theme_dir;

	public function set_up() {
		global $wp_theme_directories;

		parent::set_up();

		// Sets up the `wp-content/themes/` directory to ensure consistency when running tests.
		$this->orig_theme_dir = $wp_theme_directories;
		$wp_theme_directories = array( WP_CONTENT_DIR . '/themes', realpath( DIR_TESTDATA . '/themedir1' ) );

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	public function tear_down() {
		global $wp_theme_directories;

		$wp_theme_directories = $this->orig_theme_dir;

		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		parent::tear_down();
	}

	public function test_wp_get_themes_default() {
		$themes = wp_get_themes();
		$this->assertInstanceOf( 'WP_Theme', $themes[ $this->theme_slug ] );
		$this->assertSame( $this->theme_name, $themes[ $this->theme_slug ]->get( 'Name' ) );

		$single_theme = wp_get_theme( $this->theme_slug );
		$this->assertSame( $single_theme->get( 'Name' ), $themes[ $this->theme_slug ]->get( 'Name' ) );
		$this->assertEquals( $themes[ $this->theme_slug ], $single_theme );
	}

	public function test_wp_get_theme() {
		$themes = wp_get_themes();

		$this->assertNotEmpty( $themes );

		foreach ( $themes as $theme ) {
			$this->assertInstanceOf( 'WP_Theme', $theme );
			$this->assertFalse( $theme->errors() );
			$_theme = wp_get_theme( $theme->get_stylesheet() );
			// This primes internal WP_Theme caches for the next assertion (headers_sanitized, textdomain_loaded).
			$this->assertSame( $theme->get( 'Name' ), $_theme->get( 'Name' ) );
			$this->assertEquals( $theme, $_theme );
		}
	}

	public function test_wp_get_theme_contents() {
		$theme = wp_get_theme( $this->theme_slug );

		$this->assertSame( $this->theme_name, $theme->get( 'Name' ) );
		$this->assertNotEmpty( $theme->get( 'Description' ) );
		$this->assertNotEmpty( $theme->get( 'Author' ) );
		$this->assertNotEmpty( $theme->get( 'Version' ) );
		$this->assertNotEmpty( $theme->get( 'AuthorURI' ) );
		$this->assertNotEmpty( $theme->get( 'ThemeURI' ) );
		$this->assertSame( $this->theme_slug, $theme->get_stylesheet() );
		$this->assertSame( $this->theme_slug, $theme->get_template() );

		$this->assertSame( 'publish', $theme->get( 'Status' ) );

		$this->assertSame( WP_CONTENT_DIR . '/themes/' . $this->theme_slug, $theme->get_stylesheet_directory(), 'get_stylesheet_directory' );
		$this->assertSame( WP_CONTENT_DIR . '/themes/' . $this->theme_slug, $theme->get_template_directory(), 'get_template_directory' );
		$this->assertSame( content_url( 'themes/' . $this->theme_slug ), $theme->get_stylesheet_directory_uri(), 'get_stylesheet_directory_uri' );
		$this->assertSame( content_url( 'themes/' . $this->theme_slug ), $theme->get_template_directory_uri(), 'get_template_directory_uri' );
	}

	/**
	 * Make sure we update the default theme list to include the latest default theme.
	 *
	 * @ticket 29925
	 */
	public function test_default_theme_in_default_theme_list() {
		$latest_default_theme = WP_Theme::get_core_default_theme();
		if ( ! $latest_default_theme->exists() || 'calm' !== substr( $latest_default_theme->get_stylesheet(), 0, 4 ) ) {
			$this->fail( 'No calm* series default themes are installed.' );
		}
		$this->assertContains( $latest_default_theme->get_stylesheet(), $this->default_themes );
	}

	/**
	 * Tests the default themes list in the test suite matches the runtime default themes.
	 *
	 * @ticket 62103
	 *
	 * @coversNothing
	 */
	public function test_default_default_theme_list_match_in_test_suite_and_at_runtime() {
		// Use a reflection to make WP_THEME::$default_themes accessible.
		$reflection = new ReflectionClass( 'WP_Theme' );
		$property   = $reflection->getProperty( 'default_themes' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		/*
		 * `default` and `classic` are included in `WP_Theme::$default_themes` but not included
		 * in the test suite default themes list. These are excluded from the comparison.
		 */
		$default_themes = array_keys( $property->getValue() );
		$default_themes = array_diff( $default_themes, array( 'default', 'classic' ) );

		$this->assertSameSets( $default_themes, $this->default_themes, 'Test suite default themes should match the runtime default themes.' );
	}

	/**
	 * Test the default theme in WP_Theme matches the WP_DEFAULT_THEME constant.
	 *
	 * @ticket 62103
	 *
	 * @covers WP_Theme::get_core_default_theme
	 */
	public function test_default_theme_matches_constant() {
		$latest_default_theme = WP_Theme::get_core_default_theme();

		/*
		 * The test suite sets the constant to `default` while this is intended to
		 * test the value defined in default-constants.php.
		 *
		 * Therefore this reads the file in via file_get_contents to extract the value.
		 */
		$default_constants = file_get_contents( ABSPATH . WPINC . '/default-constants.php' );
		preg_match( '/define\( \'WP_DEFAULT_THEME\', \'(.*)\' \);/', $default_constants, $matches );
		$wp_default_theme_constant = $matches[1];

		$this->assertSame( $wp_default_theme_constant, $latest_default_theme->get_stylesheet(), 'WP_DEFAULT_THEME should match the latest default theme.' );
	}

	public function test_switch_theme_bogus() {
		// Try switching to a theme that doesn't exist.
		$template = 'some_template';
		$style    = 'some_style';
		update_option( 'template', $template );
		update_option( 'stylesheet', $style );

		$theme = wp_get_theme();
		$this->assertSame( $style, (string) $theme );
		$this->assertNotFalse( $theme->errors() );
		$this->assertFalse( $theme->exists() );

		// These return the bogus name - perhaps not ideal behavior?
		$this->assertSame( $template, get_template() );
		$this->assertSame( $style, get_stylesheet() );
	}

	/**
	 * Test _wp_keep_alive_customize_changeset_dependent_auto_drafts.
	 *
	 * @covers ::_wp_keep_alive_customize_changeset_dependent_auto_drafts
	 */
	public function test_wp_keep_alive_customize_changeset_dependent_auto_drafts() {
		$nav_created_post_ids = self::factory()->post->create_many(
			2,
			array(
				'post_status' => 'auto-draft',
				'post_date'   => gmdate( 'Y-m-d H:i:s', strtotime( '-2 days' ) ),
			)
		);
		$data                 = array(
			'nav_menus_created_posts' => array(
				'value' => $nav_created_post_ids,
			),
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		require_once ABSPATH . WPINC . '/class-wp-customize-manager.php';
		$wp_customize = new WP_Customize_Manager();
		do_action( 'customize_register', $wp_customize );

		// The post_date for auto-drafts is bumped to match the changeset post_date whenever it is modified
		// to keep them from from being garbage collected by wp_delete_auto_drafts().
		$wp_customize->save_changeset_post(
			array(
				'data' => $data,
			)
		);
		$this->assertSame( get_post( $wp_customize->changeset_post_id() )->post_date, get_post( $nav_created_post_ids[0] )->post_date );
		$this->assertSame( get_post( $wp_customize->changeset_post_id() )->post_date, get_post( $nav_created_post_ids[1] )->post_date );
		$this->assertSame( 'auto-draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'auto-draft', get_post_status( $nav_created_post_ids[1] ) );

		// Stubs transition to drafts when changeset is saved as a draft.
		$wp_customize->save_changeset_post(
			array(
				'status' => 'draft',
				'data'   => $data,
			)
		);
		$this->assertSame( 'draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'draft', get_post_status( $nav_created_post_ids[1] ) );

		// Status remains unchanged for stub that the user broke out of the changeset.
		wp_update_post(
			array(
				'ID'          => $nav_created_post_ids[1],
				'post_status' => 'private',
			)
		);
		$wp_customize->save_changeset_post(
			array(
				'status' => 'draft',
				'data'   => $data,
			)
		);
		$this->assertSame( 'draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'private', get_post_status( $nav_created_post_ids[1] ) );

		// Draft stub is trashed when the changeset is trashed.
		$wp_customize->trash_changeset_post( $wp_customize->changeset_post_id() );
		$this->assertSame( 'trash', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertSame( 'private', get_post_status( $nav_created_post_ids[1] ) );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_defaults() {
		$registered = register_theme_feature( 'test-feature' );
		$this->assertTrue( $registered );

		$expected = array(
			'type'         => 'boolean',
			'variadic'     => false,
			'description'  => '',
			'show_in_rest' => false,
		);
		$this->assertSameSets( $expected, get_registered_theme_feature( 'test-feature' ) );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_explicit() {
		$args = array(
			'type'         => 'array',
			'variadic'     => true,
			'description'  => 'My Feature',
			'show_in_rest' => array(
				'schema' => array(
					'items' => array(
						'type' => 'string',
					),
				),
			),
		);

		register_theme_feature( 'test-feature', $args );
		$actual = get_registered_theme_feature( 'test-feature' );

		$this->assertSame( 'array', $actual['type'] );
		$this->assertTrue( $actual['variadic'] );
		$this->assertSame( 'My Feature', $actual['description'] );
		$this->assertSame( array( 'type' => 'string' ), $actual['show_in_rest']['schema']['items'] );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_upgrades_show_in_rest() {
		register_theme_feature( 'test-feature', array( 'show_in_rest' => true ) );

		$expected = array(
			'schema'           => array(
				'description' => '',
				'type'        => 'boolean',
				'default'     => false,
			),
			'name'             => 'test-feature',
			'prepare_callback' => null,
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest'];

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_fills_schema() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'items'    => array(
							'type' => 'string',
						),
						'minItems' => 1,
					),
				),
			)
		);

		$expected = array(
			'description' => 'Cool Feature',
			'type'        => array( 'boolean', 'array' ),
			'items'       => array(
				'type' => 'string',
			),
			'minItems'    => 1,
			'default'     => false,
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema'];

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_does_not_add_boolean_type_if_non_bool_default() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'show_in_rest' => array(
					'schema' => array(
						'items'   => array(
							'type' => 'string',
						),
						'default' => array( 'standard' ),
					),
				),
			)
		);

		$actual = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema']['type'];
		$this->assertSame( 'array', $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_defaults_additional_properties_to_false() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'object',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'properties' => array(
							'a' => array(
								'type' => 'string',
							),
						),
					),
				),
			)
		);

		$actual = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema'];

		$this->assertArrayHasKey( 'additionalProperties', $actual );
		$this->assertFalse( $actual['additionalProperties'] );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_with_additional_properties() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'object',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'properties'           => array(),
						'additionalProperties' => array(
							'type' => 'string',
						),
					),
				),
			)
		);

		$expected = array(
			'type' => 'string',
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema']['additionalProperties'];

		$this->assertSameSets( $expected, $actual );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_defaults_additional_properties_to_false_in_array() {
		register_theme_feature(
			'test-feature',
			array(
				'type'         => 'array',
				'description'  => 'Cool Feature',
				'show_in_rest' => array(
					'schema' => array(
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'a' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
			)
		);

		$actual = get_registered_theme_feature( 'test-feature' )['show_in_rest']['schema']['items'];

		$this->assertArrayHasKey( 'additionalProperties', $actual );
		$this->assertFalse( $actual['additionalProperties'] );
	}

	/**
	 * @ticket 49406
	 *
	 * @dataProvider data_register_theme_support_validation
	 *
	 * @param string $error_code The error code expected.
	 * @param array  $args       The args to register.
	 */
	public function test_register_theme_support_validation( $error_code, $args ) {
		$registered = register_theme_feature( 'test-feature', $args );

		$this->assertWPError( $registered );
		$this->assertSame( $error_code, $registered->get_error_code() );
	}

	public function data_register_theme_support_validation() {
		return array(
			array(
				'invalid_type',
				array(
					'type' => 'float',
				),
			),
			array(
				'invalid_type',
				array(
					'type' => array( 'string' ),
				),
			),
			array(
				'variadic_must_be_array',
				array(
					'variadic' => true,
				),
			),
			array(
				'missing_schema',
				array(
					'type'         => 'object',
					'show_in_rest' => true,
				),
			),
			array(
				'missing_schema',
				array(
					'type'         => 'array',
					'show_in_rest' => true,
				),
			),
			array(
				'missing_schema_items',
				array(
					'type'         => 'array',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'array',
						),
					),
				),
			),
			array(
				'missing_schema_properties',
				array(
					'type'         => 'object',
					'show_in_rest' => array(
						'schema' => array(
							'type' => 'object',
						),
					),
				),
			),
			array(
				'invalid_rest_prepare_callback',
				array(
					'show_in_rest' => array(
						'prepare_callback' => 'this is not a valid function',
					),
				),
			),
		);
	}

	/**
	 * Tests that a theme in the custom test data theme directory is recognized.
	 *
	 * @ticket 18298
	 */
	public function test_theme_in_custom_theme_dir_is_valid() {
		switch_theme( 'block-theme' );
		$this->assertTrue( wp_get_theme()->exists() );
	}

	/**
	 * Tests that `is_child_theme()` returns true for child theme.
	 *
	 * @ticket 18298
	 *
	 * @covers ::is_child_theme
	 */
	public function test_is_child_theme_true() {
		switch_theme( 'block-theme-child' );
		$this->assertTrue( is_child_theme() );
	}

	/**
	 * Tests that `is_child_theme()` returns false for parent theme.
	 *
	 * @ticket 18298
	 *
	 * @covers ::is_child_theme
	 */
	public function test_is_child_theme_false() {
		switch_theme( 'block-theme' );
		$this->assertFalse( is_child_theme() );
	}

	/**
	 * Tests that the child theme directory is correctly detected.
	 *
	 * @ticket 18298
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory() {
		switch_theme( 'block-theme-child' );
		$this->assertSamePathIgnoringDirectorySeparators( realpath( DIR_TESTDATA ) . '/themedir1/block-theme-child', get_stylesheet_directory() );
	}

	/**
	 * Tests that the parent theme directory is correctly detected.
	 *
	 * @ticket 18298
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory() {
		switch_theme( 'block-theme-child' );
		$this->assertSamePathIgnoringDirectorySeparators( realpath( DIR_TESTDATA ) . '/themedir1/block-theme', get_template_directory() );
	}

	/**
	 * Tests that get_stylesheet_directory() behaves correctly with filters.
	 *
	 * @ticket 18298
	 * @dataProvider data_get_stylesheet_directory_with_filter
	 *
	 * @covers ::get_stylesheet_directory
	 *
	 * @param string   $theme     Theme slug / directory name.
	 * @param string   $hook_name Filter hook name.
	 * @param callable $callback  Filter callback.
	 * @param string   $expected  Expected stylesheet directory with the filter active.
	 */
	public function test_get_stylesheet_directory_with_filter( $theme, $hook_name, $callback, $expected ) {
		switch_theme( $theme );

		// Add filter, then call get_stylesheet_directory() to compute value.
		add_filter( $hook_name, $callback );
		$this->assertSame( $expected, get_stylesheet_directory(), 'Stylesheet directory returned incorrect result not considering filters' );

		// Remove filter again, then ensure result is recalculated and not the same as before.
		remove_filter( $hook_name, $callback );
		$this->assertNotSame( $expected, get_stylesheet_directory(), 'Stylesheet directory returned previous value even though filters were removed' );
	}

	/**
	 * Data provider for `test_get_stylesheet_directory_with_filter()`.
	 *
	 * @return array[]
	 */
	public function data_get_stylesheet_directory_with_filter() {
		return array(
			'with stylesheet_directory filter' => array(
				'block-theme',
				'stylesheet_directory',
				static function ( $dir ) {
					return str_replace( realpath( DIR_TESTDATA ) . DIRECTORY_SEPARATOR . 'themedir1', '/fantasy-dir', $dir );
				},
				'/fantasy-dir/block-theme',
			),
			'with theme_root filter'           => array(
				'block-theme',
				'theme_root',
				static function () {
					return '/fantasy-dir';
				},
				'/fantasy-dir/block-theme',
			),
			'with stylesheet filter'           => array(
				'block-theme',
				'stylesheet',
				static function () {
					return 'another-theme';
				},
				// Because the theme does not exist, `get_theme_root()` returns the default themes directory.
				WP_CONTENT_DIR . '/themes/another-theme',
			),
		);
	}

	/**
	 * Tests that get_template_directory() behaves correctly with filters.
	 *
	 * @ticket 18298
	 * @dataProvider data_get_template_directory_with_filter
	 *
	 * @covers ::get_template_directory
	 *
	 * @param string   $theme     Theme slug / directory name.
	 * @param string   $hook_name Filter hook name.
	 * @param callable $callback  Filter callback.
	 * @param string   $expected  Expected template directory with the filter active.
	 */
	public function test_get_template_directory_with_filter( $theme, $hook_name, $callback, $expected ) {
		switch_theme( $theme );

		// Add filter, then call get_template_directory() to compute value.
		add_filter( $hook_name, $callback );
		$this->assertSame( $expected, get_template_directory(), 'Template directory returned incorrect result not considering filters' );

		// Remove filter again, then ensure result is recalculated and not the same as before.
		remove_filter( $hook_name, $callback );
		$this->assertNotSame( $expected, get_template_directory(), 'Template directory returned previous value even though filters were removed' );
	}

	/**
	 * Data provider for `test_get_template_directory_with_filter()`.
	 *
	 * @return array[]
	 */
	public function data_get_template_directory_with_filter() {
		return array(
			'with template_directory filter' => array(
				'block-theme',
				'template_directory',
				static function ( $dir ) {
					return str_replace( realpath( DIR_TESTDATA ) . DIRECTORY_SEPARATOR . 'themedir1', '/fantasy-dir', $dir );
				},
				'/fantasy-dir/block-theme',
			),
			'with theme_root filter'         => array(
				'block-theme',
				'theme_root',
				static function () {
					return '/fantasy-dir';
				},
				'/fantasy-dir/block-theme',
			),
			'with template filter'           => array(
				'block-theme',
				'template',
				static function () {
					return 'another-theme';
				},
				// Because the theme does not exist, `get_theme_root()` returns the default themes directory.
				WP_CONTENT_DIR . '/themes/another-theme',
			),
		);
	}

	/**
	 * Tests whether a switched site retrieves the correct stylesheet directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_with_switched_site() {
		$blog_id = self::factory()->blog->create();

		update_blog_option( $blog_id, 'stylesheet', 'switched_stylesheet' );

		// Prime global storage with the current site's data.
		get_stylesheet_directory();

		switch_to_blog( $blog_id );
		$switched_stylesheet = get_stylesheet_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/switched_stylesheet', $switched_stylesheet );
	}

	/**
	 * Tests whether a switched site retrieves the correct template directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_with_switched_site() {
		$blog_id = self::factory()->blog->create();

		update_blog_option( $blog_id, 'template', 'switched_template' );

		// Prime global storage with the current site's data.
		get_template_directory();

		switch_to_blog( $blog_id );
		$switched_template = get_template_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/switched_template', $switched_template );
	}

	/**
	 * Tests whether a restored site retrieves the correct stylesheet directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_with_restored_site() {
		$blog_id = self::factory()->blog->create();

		update_option( 'stylesheet', 'original_stylesheet' );
		update_blog_option( $blog_id, 'stylesheet', 'switched_stylesheet' );

		$stylesheet = get_stylesheet_directory();

		switch_to_blog( $blog_id );

		// Prime global storage with the restored site's data.
		get_stylesheet_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/original_stylesheet', $stylesheet );
	}

	/**
	 * Tests whether a restored site retrieves the correct template directory.
	 *
	 * @ticket 59677
	 * @group ms-required
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_with_restored_site() {
		$blog_id = self::factory()->blog->create();

		update_option( 'template', 'original_template' );
		update_blog_option( $blog_id, 'template', 'switched_template' );

		$template = get_template_directory();

		switch_to_blog( $blog_id );

		// Prime global storage with the switched site's data.
		get_template_directory();
		restore_current_blog();

		$this->assertSame( WP_CONTENT_DIR . '/themes/original_template', $template );
	}

	/**
	 * Make sure filters added after the initial call are fired.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_filters_apply() {
		// Call the function prior to the filter being added.
		get_stylesheet_directory();

		$expected = 'test_root/dir';

		// Add the filer.
		add_filter(
			'stylesheet_directory',
			function () use ( $expected ) {
				return $expected;
			}
		);

		$this->assertSame( $expected, get_stylesheet_directory() );
	}

	/**
	 * Make sure filters added after the initial call are fired.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_filters_apply() {
		// Call the function prior to the filter being added.
		get_template_directory();

		$expected = 'test_root/dir';

		// Add the filer.
		add_filter(
			'template_directory',
			function () use ( $expected ) {
				return $expected;
			}
		);

		$this->assertSame( $expected, get_template_directory() );
	}

	/**
	 * Make sure get_stylesheet_directory uses the correct path when the root theme dir changes.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_stylesheet_directory
	 */
	public function test_get_stylesheet_directory_uses_registered_theme_dir() {
		$old_theme = wp_get_theme();

		switch_theme( 'test' );

		$old_root = get_theme_root( 'test' );
		$path1    = get_stylesheet_directory();

		$new_root = DIR_TESTDATA . '/themedir2';
		register_theme_directory( $new_root );

		// Mock the stylesheet root option to mimic that the active root has changed.
		add_filter(
			'pre_option_stylesheet_root',
			function () use ( $new_root ) {
				return $new_root;
			}
		);

		$path2 = get_stylesheet_directory();

		// Cleanup.
		switch_theme( $old_theme->get_stylesheet() );

		$this->assertSame( $old_root . '/test', $path1, 'The original stylesheet path is not correct' );
		$this->assertSame( $new_root . '/test', $path2, 'The new stylesheet path is not correct' );
	}

	/**
	 * Make sure get_template_directory uses the correct path when the root theme dir changes.
	 *
	 * @ticket 59847
	 *
	 * @covers ::get_template_directory
	 */
	public function test_get_template_directory_uses_registered_theme_dir() {
		$old_theme = wp_get_theme();

		switch_theme( 'test' );

		// Mock parent theme to be returned as the template.
		add_filter(
			'pre_option_template',
			function () {
				return 'test-parent';
			}
		);

		$old_root = get_theme_root( 'test' );
		$path1    = get_template_directory();

		$new_root = DIR_TESTDATA . '/themedir2';
		register_theme_directory( $new_root );

		// Mock the template root option to mimic that the active root has changed.
		add_filter(
			'pre_option_template_root',
			function () use ( $new_root ) {
				return $new_root;
			}
		);

		$path2 = get_template_directory();

		// Cleanup.
		switch_theme( $old_theme->get_stylesheet() );

		$this->assertSame( $old_root . '/test-parent', $path1, 'The original template path is not correct' );
		$this->assertSame( $new_root . '/test-parent', $path2, 'The new template path is not correct' );
	}

	/**
	 * Tests that switch_to_blog() uses the original template path.
	 *
	 * @ticket 60290
	 *
	 * @group ms-required
	 *
	 * @covers ::locate_template
	 */
	public function test_switch_to_blog_uses_original_template_path() {
		$old_theme     = wp_get_theme();
		$template_path = locate_template( 'index.php' );

		$blog_id = self::factory()->blog->create();
		switch_to_blog( $blog_id );

		switch_theme( 'block-theme' );
		$new_template_path = locate_template( 'index.php' );

		// Cleanup.
		restore_current_blog();
		switch_theme( $old_theme->get_stylesheet() );

		$this->assertSame( $template_path, $new_template_path, 'Switching blogs switches the template path' );
	}

	/**
	 * Verify the validate_theme_requirements theme responds as expected for twentyten.
	 *
	 * @ticket 54381
	 */
	public function test_validate_theme_requirements_filter_default() {
		// Default expectation since twentyten has the least strict requirements.
		$this->assertTrue( validate_theme_requirements( 'twentyten' ) );
	}

	/**
	 * Verify that a filtered failure of validate_theme_requirements returns WP_Error
	 *
	 * @ticket 54381
	 */
	public function test_validate_theme_requirements_filter_error() {
		// Adds an extra requirement that always fails.
		add_filter(
			'validate_theme_requirements',
			function () {
				return new WP_Error( 'theme_test_failed_requirement' );
			}
		);

		$this->assertInstanceOf( 'WP_Error', validate_theme_requirements( 'twentyten' ) );
	}

	/**
	 * Verify that the theme is passed through to the validate_theme_requirements filter by selectively erroring.
	 *
	 * @ticket 54381
	 */
	public function test_validate_theme_requirements_filter_selective_failure() {
		// Adds an extra requirement only for a particular theme.
		add_filter(
			'validate_theme_requirements',
			function ( $met_requirements, $stylesheet ) {
				if ( 'twentytwenty' === $stylesheet ) {
					return new WP_Error( 'theme_test_failed_requirement' );
				}
				return $met_requirements;
			},
			10,
			2
		);

		$this->assertTrue( validate_theme_requirements( 'twentyten' ) );
		$this->assertInstanceOf( 'WP_Error', validate_theme_requirements( 'twentytwenty' ) );
	}
}
