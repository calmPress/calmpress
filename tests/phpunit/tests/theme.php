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

	function setUp() {
		global $wp_theme_directories;

		parent::setUp();

		$backup_wp_theme_directories = $wp_theme_directories;
		$wp_theme_directories        = array( WP_CONTENT_DIR . '/themes' );

		add_filter( 'extra_theme_headers', array( $this, '_theme_data_extra_headers' ) );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );
	}

	function tearDown() {
		global $wp_theme_directories;

		$wp_theme_directories = $this->wp_theme_directories;

		remove_filter( 'extra_theme_headers', array( $this, '_theme_data_extra_headers' ) );
		wp_clean_themes_cache();
		unset( $GLOBALS['wp_themes'] );

		parent::tearDown();
	}

	function test_wp_get_themes_default() {
		$themes = wp_get_themes();
		$this->assertInstanceOf( 'WP_Theme', $themes[ $this->theme_slug ] );
		$this->assertEquals( $this->theme_name, $themes[ $this->theme_slug ]->get( 'Name' ) );

		$single_theme = wp_get_theme( $this->theme_slug );
		$this->assertEquals( $single_theme->get( 'Name' ), $themes[ $this->theme_slug ]->get( 'Name' ) );
		$this->assertEquals( $themes[ $this->theme_slug ], $single_theme );
	}

	function test_wp_get_theme() {
		$themes = wp_get_themes();
		foreach ( $themes as $theme ) {
			$this->assertInstanceOf( 'WP_Theme', $theme );
			$this->assertFalse( $theme->errors() );
			$_theme = wp_get_theme( $theme->get_stylesheet() );
			// This primes internal WP_Theme caches for the next assertion (headers_sanitized, textdomain_loaded).
			$this->assertEquals( $theme->get( 'Name' ), $_theme->get( 'Name' ) );
			$this->assertEquals( $theme, $_theme );
		}
	}

	function test_wp_get_theme_contents() {
		$theme = wp_get_theme( $this->theme_slug );

		$this->assertEquals( $this->theme_name, $theme->get( 'Name' ) );
		$this->assertNotEmpty( $theme->get( 'Description' ) );
		$this->assertNotEmpty( $theme->get( 'Author' ) );
		$this->assertNotEmpty( $theme->get( 'Version' ) );
		$this->assertNotEmpty( $theme->get( 'AuthorURI' ) );
		$this->assertNotEmpty( $theme->get( 'ThemeURI' ) );
		$this->assertEquals( $this->theme_slug, $theme->get_stylesheet() );
		$this->assertEquals( $this->theme_slug, $theme->get_template() );

		$this->assertEquals( 'publish', $theme->get( 'Status' ) );

		$this->assertEquals( WP_CONTENT_DIR . '/themes/' . $this->theme_slug, $theme->get_stylesheet_directory(), 'get_stylesheet_directory' );
		$this->assertEquals( WP_CONTENT_DIR . '/themes/' . $this->theme_slug, $theme->get_template_directory(), 'get_template_directory' );
		$this->assertEquals( content_url( 'themes/' . $this->theme_slug ), $theme->get_stylesheet_directory_uri(), 'get_stylesheet_directory_uri' );
		$this->assertEquals( content_url( 'themes/' . $this->theme_slug ), $theme->get_template_directory_uri(), 'get_template_directory_uri' );
	}

	/**
	 * Make sure we update the default theme list to include the latest default theme.
	 *
	 * @ticket 29925
	 */
	function test_default_theme_in_default_theme_list() {
		$latest_default_theme = WP_Theme::get_core_default_theme();
		if ( ! $latest_default_theme->exists() || 'calm' !== substr( $latest_default_theme->get_stylesheet(), 0, 4 ) ) {
			$this->fail( 'No calm* series default themes are installed' );
		}
		$this->assertContains( $latest_default_theme->get_stylesheet(), $this->default_themes );
	}

	function test_default_themes_have_textdomain() {
		foreach ( $this->default_themes as $theme ) {
			if ( wp_get_theme( $theme )->exists() ) {
				$this->assertEquals( $theme, wp_get_theme( $theme )->get( 'TextDomain' ) );
			}
		}
	}

	function _theme_data_extra_headers() {
		return array( 'License' );
	}

	function test_switch_theme_bogus() {
		// Try switching to a theme that doesn't exist.
		$template = rand_str();
		$style    = rand_str();
		update_option( 'template', $template );
		update_option( 'stylesheet', $style );

		$theme = wp_get_theme();
		$this->assertEquals( $style, (string) $theme );
		$this->assertNotFalse( $theme->errors() );
		$this->assertFalse( $theme->exists() );

		// These return the bogus name - perhaps not ideal behaviour?
		$this->assertEquals( $template, get_template() );
		$this->assertEquals( $style, get_stylesheet() );
	}

	/**
	 * Test _wp_keep_alive_customize_changeset_dependent_auto_drafts.
	 *
	 * @covers ::_wp_keep_alive_customize_changeset_dependent_auto_drafts()
	 */
	function test_wp_keep_alive_customize_changeset_dependent_auto_drafts() {
		$nav_created_post_ids = $this->factory()->post->create_many(
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
		$this->assertEquals( get_post( $wp_customize->changeset_post_id() )->post_date, get_post( $nav_created_post_ids[0] )->post_date );
		$this->assertEquals( get_post( $wp_customize->changeset_post_id() )->post_date, get_post( $nav_created_post_ids[1] )->post_date );
		$this->assertEquals( 'auto-draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertEquals( 'auto-draft', get_post_status( $nav_created_post_ids[1] ) );

		// Stubs transition to drafts when changeset is saved as a draft.
		$wp_customize->save_changeset_post(
			array(
				'status' => 'draft',
				'data'   => $data,
			)
		);
		$this->assertEquals( 'draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertEquals( 'draft', get_post_status( $nav_created_post_ids[1] ) );

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
		$this->assertEquals( 'draft', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertEquals( 'private', get_post_status( $nav_created_post_ids[1] ) );

		// Draft stub is trashed when the changeset is trashed.
		$wp_customize->trash_changeset_post( $wp_customize->changeset_post_id() );
		$this->assertEquals( 'trash', get_post_status( $nav_created_post_ids[0] ) );
		$this->assertEquals( 'private', get_post_status( $nav_created_post_ids[1] ) );
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
		$this->assertEqualSets( $expected, get_registered_theme_feature( 'test-feature' ) );
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

		$this->assertEquals( 'array', $actual['type'] );
		$this->assertTrue( $actual['variadic'] );
		$this->assertEquals( 'My Feature', $actual['description'] );
		$this->assertEquals( array( 'type' => 'string' ), $actual['show_in_rest']['schema']['items'] );
	}

	/**
	 * @ticket 49406
	 */
	public function test_register_theme_support_upgrades_show_in_rest() {
		register_theme_feature( 'test-feature', array( 'show_in_rest' => true ) );

		$expected = array(
			'schema'           => array(
				'type'        => 'boolean',
				'description' => '',
				'default'     => false,
			),
			'name'             => 'test-feature',
			'prepare_callback' => null,
		);
		$actual   = get_registered_theme_feature( 'test-feature' )['show_in_rest'];

		$this->assertEqualSets( $expected, $actual );
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

		$this->assertEqualSets( $expected, $actual );
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
		$this->assertEquals( 'array', $actual );
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

		$this->assertEqualSets( $expected, $actual );
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
	 * @dataProvider _dp_register_theme_support_validation
	 *
	 * @param string $error_code The error code expected.
	 * @param array  $args       The args to register.
	 */
	public function test_register_theme_support_validation( $error_code, $args ) {
		$registered = register_theme_feature( 'test-feature', $args );

		$this->assertWPError( $registered );
		$this->assertEquals( $error_code, $registered->get_error_code() );
	}

	public function _dp_register_theme_support_validation() {
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
}
