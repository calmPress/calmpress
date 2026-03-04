<?php
/**
 * test wp-includes/template.php
 *
 * @group themes
 */
class Tests_Template extends WP_UnitTestCase {

	protected $hierarchy = array();

	protected static $page_on_front;
	protected static $page_for_posts;
	protected static $page;
	protected static $post;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$page_on_front = $factory->post->create_and_get(
			array(
				'post_type' => 'page',
				'post_name' => 'page-on-front-😀',
			)
		);

		self::$page_for_posts = $factory->post->create_and_get(
			array(
				'post_type' => 'page',
				'post_name' => 'page-for-posts-😀',
			)
		);

		self::$page = $factory->post->create_and_get(
			array(
				'post_type' => 'page',
				'post_name' => 'page-name-😀',
			)
		);
		add_post_meta( self::$page->ID, '_wp_page_template', 'templates/page.php' );

		self::$post = $factory->post->create_and_get(
			array(
				'post_type' => 'post',
				'post_name' => 'post-name-😀',
				'post_date' => '1984-02-25 12:34:56',
			)
		);
		set_post_format( self::$post, 'quote' );
		add_post_meta( self::$post->ID, '_wp_page_template', 'templates/post.php' );
	}

	/**
	 * @var WP_Scripts|null
	 */
	protected $original_wp_scripts;

	/**
	 * @var WP_Styles|null
	 */
	protected $original_wp_styles;

	/**
	 * @var array|null
	 */
	protected $original_theme_features;

	/**
	 * @var array
	 */
	const RESTORED_CONFIG_OPTIONS = array(
		'display_errors',
		'error_reporting',
		'log_errors',
		'error_log',
		'default_mimetype',
		'html_errors',
		'error_prepend_string',
		'error_append_string',
	);

	/**
	 * @var array
	 */
	protected $original_ini_config;

	public function set_up() {
		parent::set_up();

		register_post_type(
			'cpt',
			array(
				'public' => true,
			)
		);
		register_taxonomy(
			'taxo',
			'post',
			array(
				'public'       => true,
				'hierarchical' => true,
			)
		);
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		global $wp_scripts, $wp_styles;
		$this->original_wp_scripts = $wp_scripts;
		$this->original_wp_styles  = $wp_styles;
		$wp_scripts                = null;
		$wp_styles                 = null;

		foreach ( self::RESTORED_CONFIG_OPTIONS as $option ) {
			$this->original_ini_config[ $option ] = ini_get( $option );
		}
	}

	public function tear_down() {
		global $wp_scripts, $wp_styles;
		$wp_scripts = $this->original_wp_scripts;
		$wp_styles  = $this->original_wp_styles;

		foreach ( $this->original_ini_config as $option => $value ) {
			ini_set( $option, $value );
		}

		unregister_post_type( 'cpt' );
		unregister_taxonomy( 'taxo' );
		$this->set_permalink_structure( '' );

		$registry = WP_Block_Type_Registry::get_instance();
		if ( $registry->is_registered( 'third-party/test' ) ) {
			$registry->unregister( 'third-party/test' );
		}

		parent::tear_down();
	}


	public function test_404_template_hierarchy() {
		$url = add_query_arg(
			array(
				'p' => '-1',
			),
			home_url()
		);

		$this->assertTemplateHierarchy(
			$url,
			array(
				'404.php',
			)
		);
	}

	public function test_category_template_hierarchy() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_term_link( $term ),
			array(
				'category-foo-😀.php',
				'category-foo-%f0%9f%98%80.php',
				"category-{$term->term_id}.php",
				'category.php',
				'archive.php',
			)
		);
	}

	public function test_tag_template_hierarchy() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_term_link( $term ),
			array(
				'tag-foo-😀.php',
				'tag-foo-%f0%9f%98%80.php',
				"tag-{$term->term_id}.php",
				'tag.php',
				'archive.php',
			)
		);
	}

	public function test_taxonomy_template_hierarchy() {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'taxo',
				'slug'     => 'foo-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_term_link( $term ),
			array(
				'taxonomy-taxo-foo-😀.php',
				'taxonomy-taxo-foo-%f0%9f%98%80.php',
				"taxonomy-taxo-{$term->term_id}.php",
				'taxonomy-taxo.php',
				'taxonomy.php',
				'archive.php',
			)
		);
	}

	public function test_date_template_hierarchy_for_year() {
		$this->assertTemplateHierarchy(
			get_year_link( 1984 ),
			array(
				'date.php',
				'archive.php',
			)
		);
	}

	public function test_date_template_hierarchy_for_month() {
		$this->assertTemplateHierarchy(
			get_month_link( 1984, 2 ),
			array(
				'date.php',
				'archive.php',
			)
		);
	}

	public function test_date_template_hierarchy_for_day() {
		$this->assertTemplateHierarchy(
			get_day_link( 1984, 2, 25 ),
			array(
				'date.php',
				'archive.php',
			)
		);
	}

	public function test_search_template_hierarchy() {
		$url = add_query_arg(
			array(
				's' => 'foo',
			),
			home_url()
		);

		$this->assertTemplateHierarchy(
			$url,
			array(
				'search.php',
			)
		);
	}

	public function test_front_page_template_hierarchy_with_posts_on_front() {
		$this->assertSame( 'posts', get_option( 'show_on_front' ) );
		$this->assertTemplateHierarchy(
			home_url(),
			array(
				'front-page.php',
				'home.php',
				'index.php',
			)
		);
	}

	public function test_front_page_template_hierarchy_with_page_on_front() {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$page_on_front->ID );
		update_option( 'page_for_posts', self::$page_for_posts->ID );

		$this->assertTemplateHierarchy(
			home_url(),
			array(
				'front-page.php',
				'page-page-on-front-😀.php',
				'page-page-on-front-%f0%9f%98%80.php',
				'page-' . self::$page_on_front->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	public function test_home_template_hierarchy_with_page_on_front() {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', self::$page_on_front->ID );
		update_option( 'page_for_posts', self::$page_for_posts->ID );

		$this->assertTemplateHierarchy(
			get_permalink( self::$page_for_posts ),
			array(
				'home.php',
				'index.php',
			)
		);
	}

	public function test_page_template_hierarchy() {
		$this->assertTemplateHierarchy(
			get_permalink( self::$page ),
			array(
				'templates/page.php',
				'page-page-name-😀.php',
				'page-page-name-%f0%9f%98%80.php',
				'page-' . self::$page->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 18375
	 */
	public function test_single_template_hierarchy_for_post() {
		$this->assertTemplateHierarchy(
			get_permalink( self::$post ),
			array(
				'templates/post.php',
				'single-post-post-name-😀.php',
				'single-post-post-name-%f0%9f%98%80.php',
				'single-post.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_single_template_hierarchy_for_custom_post_type() {
		$cpt = self::factory()->post->create_and_get(
			array(
				'post_type' => 'cpt',
				'post_name' => 'cpt-name-😀',
			)
		);

		$this->assertTemplateHierarchy(
			get_permalink( $cpt ),
			array(
				'single-cpt-cpt-name-😀.php',
				'single-cpt-cpt-name-%f0%9f%98%80.php',
				'single-cpt.php',
				'single.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 18375
	 */
	public function test_single_template_hierarchy_for_custom_post_type_with_template() {
		$cpt = self::factory()->post->create_and_get(
			array(
				'post_type' => 'cpt',
				'post_name' => 'cpt-name-😀',
			)
		);
		add_post_meta( $cpt->ID, '_wp_page_template', 'templates/cpt.php' );

		$this->assertTemplateHierarchy(
			get_permalink( $cpt ),
			array(
				'templates/cpt.php',
				'single-cpt-cpt-name-😀.php',
				'single-cpt-cpt-name-%f0%9f%98%80.php',
				'single-cpt.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_embed_template_hierarchy_for_post() {
		update_option( 'calm_embedding_on', 1 );
		$this->assertTemplateHierarchy(
			get_post_embed_url( self::$post ),
			array(
				'embed-post.php',
				'embed.php',
				'templates/post.php',
				'single-post-post-name-😀.php',
				'single-post-post-name-%f0%9f%98%80.php',
				'single-post.php',
				'single.php',
				'singular.php',
			)
		);
	}

	public function test_embed_template_hierarchy_for_page() {
		update_option( 'calm_embedding_on', 1 );
		$this->assertTemplateHierarchy(
			get_post_embed_url( self::$page ),
			array(
				'embed-page.php',
				'embed.php',
				'templates/page.php',
				'page-page-name-😀.php',
				'page-page-name-%f0%9f%98%80.php',
				'page-' . self::$page->ID . '.php',
				'page.php',
				'singular.php',
			)
		);
	}

	/**
	 * @ticket 17851
	 * @covers ::add_settings_section
	 */
	public function test_add_settings_section() {
		add_settings_section( 'test-section', 'Section title', '__return_false', 'test-page' );

		global $wp_settings_sections;
		$this->assertIsArray( $wp_settings_sections, 'List of sections is not initialized.' );
		$this->assertArrayHasKey( 'test-page', $wp_settings_sections, 'List of sections for the test page has not been added to sections list.' );
		$this->assertIsArray( $wp_settings_sections['test-page'], 'List of sections for the test page is not initialized.' );
		$this->assertArrayHasKey( 'test-section', $wp_settings_sections['test-page'], 'Test section has not been added to the list of sections for the test page.' );

		$this->assertEqualSetsWithIndex(
			array(
				'id'             => 'test-section',
				'title'          => 'Section title',
				'callback'       => '__return_false',
				'before_section' => '',
				'after_section'  => '',
				'section_class'  => '',
			),
			$wp_settings_sections['test-page']['test-section'],
			'Test section data does not match the expected dataset.'
		);
	}

	/**
	 * @ticket 17851
	 *
	 * @param array  $extra_args                   Extra arguments to pass to function `add_settings_section()`.
	 * @param array  $expected_section_data        Expected set of section data.
	 * @param string $expected_before_section_html Expected HTML markup to be rendered before the settings section.
	 * @param string $expected_after_section_html  Expected HTML markup to be rendered after the settings section.
	 *
	 * @covers ::add_settings_section
	 * @covers ::do_settings_sections
	 *
	 * @dataProvider data_extra_args_for_add_settings_section
	 */
	public function test_add_settings_section_with_extra_args( $extra_args, $expected_section_data, $expected_before_section_html, $expected_after_section_html ) {
		add_settings_section( 'test-section', 'Section title', '__return_false', 'test-page', $extra_args );
		add_settings_field( 'test-field', 'Field title', '__return_false', 'test-page', 'test-section' );

		global $wp_settings_sections;
		$this->assertIsArray( $wp_settings_sections, 'List of sections is not initialized.' );
		$this->assertArrayHasKey( 'test-page', $wp_settings_sections, 'List of sections for the test page has not been added to sections list.' );
		$this->assertIsArray( $wp_settings_sections['test-page'], 'List of sections for the test page is not initialized.' );
		$this->assertArrayHasKey( 'test-section', $wp_settings_sections['test-page'], 'Test section has not been added to the list of sections for the test page.' );

		$this->assertEqualSetsWithIndex(
			$expected_section_data,
			$wp_settings_sections['test-page']['test-section'],
			'Test section data does not match the expected dataset.'
		);

		ob_start();
		do_settings_sections( 'test-page' );
		$output = ob_get_clean();

		$this->assertStringContainsString( $expected_before_section_html, $output, 'Test page output does not contain the custom markup to be placed before the section.' );
		$this->assertStringContainsString( $expected_after_section_html, $output, 'Test page output does not contain the custom markup to be placed after the section.' );
	}

	/**
	 * Data provider for `test_add_settings_section_with_extra_args()`.
	 *
	 * @return array
	 */
	public function data_extra_args_for_add_settings_section() {
		return array(
			'class placeholder section_class present' => array(
				array(
					'before_section' => '<div class="%s">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="%s">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				'<div class="test-section-wrap">',
				'</div><!-- end of the test section -->',
			),
			'missing class placeholder section_class' => array(
				array(
					'before_section' => '<div class="testing-section-wrapper">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="testing-section-wrapper">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => 'test-section-wrap',
				),
				'<div class="testing-section-wrapper">',
				'</div><!-- end of the test section -->',
			),
			'empty section_class'                     => array(
				array(
					'before_section' => '<div class="test-section-container">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="test-section-container">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				'<div class="test-section-container">',
				'</div><!-- end of the test section -->',
			),
			'section_class missing'                   => array(
				array(
					'before_section' => '<div class="wp-whitelabel-section">',
					'after_section'  => '</div><!-- end of the test section -->',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="wp-whitelabel-section">',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				'<div class="wp-whitelabel-section">',
				'</div><!-- end of the test section -->',
			),
			'disallowed tag in before_section'        => array(
				array(
					'before_section' => '<div class="video-settings-section"><iframe src="https://www.wordpress.org/" />',
					'after_section'  => '</div><!-- end of the test section -->',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="video-settings-section"><iframe src="https://www.wordpress.org/" />',
					'after_section'  => '</div><!-- end of the test section -->',
					'section_class'  => '',
				),
				'<div class="video-settings-section">',
				'</div><!-- end of the test section -->',
			),
			'disallowed tag in after_section'         => array(
				array(
					'before_section' => '<div class="video-settings-section">',
					'after_section'  => '</div><iframe src="https://www.wordpress.org/" />',
				),
				array(
					'id'             => 'test-section',
					'title'          => 'Section title',
					'callback'       => '__return_false',
					'before_section' => '<div class="video-settings-section">',
					'after_section'  => '</div><iframe src="https://www.wordpress.org/" />',
					'section_class'  => '',
				),
				'<div class="video-settings-section">',
				'</div>',
			),
		);
	}
	
	public function assertTemplateHierarchy( $url, array $expected, $message = '' ) {
		$this->go_to( $url );
		$hierarchy = $this->get_template_hierarchy();

		$this->assertSame( $expected, $hierarchy, $message );
	}

	/**
	 * Exports PHP array as string formatted as a snapshot for pasting into a data provider.
	 *
	 * Unfortunately, `var_export()` always includes array indices even for lists. For example:
	 *
	 *     var_export( array( 'a', 'b', 'c' ) );
	 *
	 * Results in:
	 *
	 *     array (
	 *       0 => 'a',
	 *       1 => 'b',
	 *       2 => 'c',
	 *     )
	 *
	 * This makes it unhelpful when outputting a snapshot to update a unit test. So this function strips out the indices
	 * to facilitate copy/pasting the snapshot from an assertion error message into the data provider. For example:
	 *
	 *      array(
	 *          'a',
	 *          'b',
	 *          'c',
	 *      )
	 *
	 *
	 * @param array $snapshot Snapshot.
	 * @return string Snapshot export.
	 */
	private static function get_array_snapshot_export( array $snapshot ): string {
		$export = var_export( $snapshot, true );
		$export = preg_replace( '/\barray \($/m', 'array(', $export );
		$export = preg_replace( '/^(\s+)\d+\s+=>\s+/m', '$1', $export );
		$export = preg_replace( '/=> *\n +/', '=> ', $export );
		$export = preg_replace( '/array\(\n\s+\)/', 'array()', $export );
		return preg_replace_callback(
			'/(^ +)/m',
			static function ( $matches ) {
				return str_repeat( "\t", strlen( $matches[0] ) / 2 );
			},
			$export
		);
	}

	protected static function get_query_template_conditions() {
		return array(
			'embed'             => 'is_embed',
			'404'               => 'is_404',
			'search'            => 'is_search',
			'front_page'        => 'is_front_page',
			'home'              => 'is_home',
			'privacy_policy'    => 'is_privacy_policy',
			'post_type_archive' => 'is_post_type_archive',
			'taxonomy'          => 'is_tax',
			'single'            => 'is_single',
			'page'              => 'is_page',
			'singular'          => 'is_singular',
			'category'          => 'is_category',
			'tag'               => 'is_tag',
			'author'            => 'is_author',
			'date'              => 'is_date',
			'archive'           => 'is_archive',
			'paged'             => 'is_paged',
		);
	}

	protected function get_template_hierarchy() {
		foreach ( self::get_query_template_conditions() as $type => $condition ) {

			if ( call_user_func( $condition ) ) {
				$filter = str_replace( '_', '', $type );
				add_filter( "{$filter}_template_hierarchy", array( $this, 'log_template_hierarchy' ) );
				call_user_func( "get_{$type}_template" );
				remove_filter( "{$filter}_template_hierarchy", array( $this, 'log_template_hierarchy' ) );
			}
		}
		$hierarchy       = $this->hierarchy;
		$this->hierarchy = array();
		return $hierarchy;
	}

	public function log_template_hierarchy( array $hierarchy ) {
		$this->hierarchy = array_merge( $this->hierarchy, $hierarchy );
		return $hierarchy;
	}
}
