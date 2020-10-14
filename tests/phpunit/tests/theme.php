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
}
