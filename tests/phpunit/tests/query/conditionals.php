<?php

require_once ABSPATH . '/wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . '/wp-admin/includes/screen.php';

/**
 * Test the is_*() functions in query.php across the URL structure
 *
 * This exercises both query.php and rewrite.php: urls are fed through the rewrite code,
 * then we test the effects of each url on the wp_query object.
 *
 * @group query
 * @group rewrite
 */
class Tests_Query_Conditionals extends WP_UnitTestCase {

	protected $page_ids;
	protected $post_ids;

	public function set_up() {

		// Tests the results of adding feeds using the filter as well.
		// Needs to be set early to get the rewrite rules flushed with it.
		add_filter( 'calm_feed_types', function ( array $feeds ) {
			$feeds[] = 'atom';
			return $feeds;
		}, 10, 1 );

		parent::set_up();

		update_option( 'comments_per_page', 5 );
		update_option( 'posts_per_page', 5 );

		// By default the option is 0 and rewrite rules for feeds to not work.
		// Setting it to non zero for the tests to make sense.
		update_option( 'posts_per_rss', 5 );

		create_initial_taxonomies();

	}

	public function test_home() {
		$this->go_to( '/' );
		$this->assertQueryTrue( 'is_home', 'is_front_page' );
	}

	public function test_page_on_front() {
		$page_on_front  = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		$page_for_posts = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_on_front );
		update_option( 'page_for_posts', $page_for_posts );

		$this->go_to( '/' );
		$this->assertQueryTrue( 'is_front_page', 'is_page', 'is_singular' );

		$this->go_to( get_permalink( $page_for_posts ) );
		$this->assertQueryTrue( 'is_home', 'is_posts_page' );

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_on_front' );
		delete_option( 'page_for_posts' );
	}

	public function test_404() {
		$this->go_to( '/notapage' );
		$this->assertQueryTrue( 'is_404' );
	}

	public function test_permalink() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'hello-world' ) );
		// A category is required for the verbose test that runs the same test
		// for a verbose structure.
		$c1 = self::factory()->category->create( ['name' => 'c1'] );
		wp_set_object_terms( $post_id, $c1, 'category' );
		$this->go_to( get_permalink( $post_id ) );
		$this->assertQueryTrue( 'is_single', 'is_singular' );
	}

	public function test_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'about',
			)
		);
		$this->go_to( get_permalink( $page_id ) );
		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_parent_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_child_page_1() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	public function test_child_page_2() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'parent-page',
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-1',
				'post_parent' => $page_id,
			)
		);
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'child-page-2',
				'post_parent' => $page_id,
			)
		);
		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular' );
	}

	// '(about)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'
	public function test_page_page_2() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'about',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		$this->go_to( '/about/page/2/' );

		// Make sure the correct WP_Query flags are set.
		$this->assertQueryTrue( 'is_page', 'is_singular', 'is_paged' );

		// Make sure the correct page was fetched.
		global $wp_query;
		$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
	}

	// '(about)/page/?([0-9]{1,})/?$' => 'index.php?pagename=$matches[1]&paged=$matches[2]'
	public function test_page_page_2_no_slash() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'about',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		$this->go_to( '/about/page2/' );

		// Make sure the correct WP_Query flags are set.
		$this->assertQueryTrue( 'is_page', 'is_singular', 'is_paged' );

		// Make sure the correct page was fetched.
		global $wp_query;
		$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );
	}

	// '(about)(/[0-9]+)?/?$' => 'index.php?pagename=$matches[1]&page=$matches[2]'
	public function test_pagination_of_posts_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_title'   => 'about',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_for_posts', $page_id );

		$this->go_to( '/about/2/' );

		$this->assertQueryTrue( 'is_home', 'is_posts_page' );

		// Make sure the correct page was fetched.
		global $wp_query;
		$this->assertSame( $page_id, $wp_query->get_queried_object()->ID );

		update_option( 'show_on_front', 'posts' );
		delete_option( 'page_for_posts' );
	}

	public function test_main_feed() {
		self::factory()->post->create(); // @test_404
		$types = array( 'rss2', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( get_feed_link( $type ) );
			$this->assertQueryTrue( 'is_feed' );
		}

		// Test for 404 when feeds are disabled.
		update_option( 'posts_per_rss', 0 );
		foreach ( $types as $type ) {
			$this->go_to( get_feed_link( $type ) );
			$this->assertQueryTrue( 'is_404' );
		}
	}

	// 'page/?([0-9]{1,})/?$' => 'index.php?&paged=$matches[1]',
	public function test_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 5 );
		for ( $i = 2; $i <= 3; $i++ ) {
			$this->go_to( "/page/{$i}/" );
			$this->assertQueryTrue( 'is_home', 'is_front_page', 'is_paged' );
		}
	}

	// 'search/(.+)/(rss2)/?$' => 'index.php?s=$matches[1]&feed=$matches[2]',
	public function test_search_feed() {
		$types = array( 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/search/test/feed/{$type}" );
				$this->assertQueryTrue( 'is_feed', 'is_search' );
		}

		// Test for 404 when feeds are disabled.
		update_option( 'posts_per_rss', 0 );
		foreach ( $types as $type ) {
			$this->go_to( "/search/test/feed/{$type}" );
			$this->assertQueryTrue( 'is_404' );
		}
	}

	// 'search/(.+)/page/?([0-9]{1,})/?$' => 'index.php?s=$matches[1]&paged=$matches[2]',
	public function test_search_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_title' => 'test' ) );
		$this->go_to( '/search/test/page/2/' );
		$this->assertQueryTrue( 'is_search', 'is_paged' );
	}

	// 'search/(.+)/?$' => 'index.php?s=$matches[1]',
	public function test_search() {
		$this->go_to( '/search/test/' );
		$this->assertQueryTrue( 'is_search' );
	}

	/**
	 * @ticket 13961
	 */
	public function test_search_encoded_chars() {
		$this->go_to( '/search/F%C3%BCnf%2Bbar/' );
		$this->assertSame( get_query_var( 's' ), 'Fünf+bar' );
	}

	// 'category/(.+?)/(rss2)/?$' => 'index.php?category_name=$matches[1]&feed=$matches[2]',
	public function test_category_feed() {

		self::factory()->term->create(
			array(
				'name'     => 'cat-a',
				'taxonomy' => 'category',
			)
		);

		$types = array( 'rss2', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( "/category/cat-a/feed/{$type}" );
			$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_category' );
		}

		// Yeh ugly hack, but there is no sane way to reinitialize the object.
		global $wp_rewrite;
		$wp_rewrite->extra_rules_top = [];

		// Test for 404 when feeds are disabled.
		update_option( 'posts_per_rss', 0 );
		// Check the short form.
		$types = array( 'feed', 'rss2', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( "/category/cat-a/feed/{$type}" );
			$this->assertQueryTrue( 'is_404' );
		}
	}

	// 'category/(.+?)/page/?([0-9]{1,})/?$' => 'index.php?category_name=$matches[1]&paged=$matches[2]',
	public function test_category_paged() {
		$c1 = self::factory()->category->create( ['name' => 'Uncategorized'] );
		update_option( 'posts_per_page', 2 );
		$posts = self::factory()->post->create_many( 3 );
		foreach ( $posts as $post_id ) {
			wp_set_object_terms( $post_id, $c1, 'category' );
		}
		$this->go_to('/category/uncategorized/page/2/');
		$this->assertQueryTrue('is_archive', 'is_category', 'is_paged');
	}

	// 'category/(.+?)/?$' => 'index.php?category_name=$matches[1]',
	public function test_category() {
		self::factory()->term->create(
			array(
				'name'     => 'cat-a',
				'taxonomy' => 'category',
			)
		);
		$this->go_to( '/category/cat-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_category' );
	}

	// 'tag/(.+?)/(rss2)/?$' => 'index.php?tag=$matches[1]&feed=$matches[2]',
	public function test_tag_feed() {
		self::factory()->term->create(
			array(
				'name'     => 'tag-a',
				'taxonomy' => 'post_tag',
			)
		);

		$types = array( 'rss2', 'atom' );
		foreach ( $types as $type ) {
				$this->go_to( "/tag/tag-a/feed/{$type}" );
				$this->assertQueryTrue( 'is_archive', 'is_feed', 'is_tag' );
		}

		// Yeh ugly hack, but there is no sane way to reinitialize the object.
		global $wp_rewrite;
		$wp_rewrite->extra_rules_top = [];

		// Test a 404 when feeds are disabled.
		update_option( 'posts_per_rss', 0 );
		// Check the short form.
		$types = array( 'rss2', 'atom' );
		foreach ( $types as $type ) {
			$this->go_to( "/tag/tag-a/feed/{$type}" );
			$this->assertQueryTrue( 'is_404' );
		}
	}

	// 'tag/(.+?)/page/?([0-9]{1,})/?$' => 'index.php?tag=$matches[1]&paged=$matches[2]',
	public function test_tag_paged() {
		update_option( 'posts_per_page', 2 );
		$post_ids = self::factory()->post->create_many( 3 );
		foreach ( $post_ids as $post_id ) {
			self::factory()->term->add_post_terms( $post_id, 'tag-a', 'post_tag' );
		}
		$this->go_to( '/tag/tag-a/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_tag', 'is_paged' );
	}

	// 'tag/(.+?)/?$' => 'index.php?tag=$matches[1]',
	public function test_tag() {
		$term_id = self::factory()->term->create(
			array(
				'name'     => 'Tag Named A',
				'slug'     => 'tag-a',
				'taxonomy' => 'post_tag',
			)
		);
		$this->go_to( '/tag/tag-a/' );
		$this->assertQueryTrue( 'is_archive', 'is_tag' );

		$tag = get_term( $term_id, 'post_tag' );

		$this->assertTrue( is_tag() );
		$this->assertTrue( is_tag( $tag->name ) );
		$this->assertTrue( is_tag( $tag->slug ) );
		$this->assertTrue( is_tag( $tag->term_id ) );
		$this->assertTrue( is_tag( array() ) );
		$this->assertTrue( is_tag( array( $tag->name ) ) );
		$this->assertTrue( is_tag( array( $tag->slug ) ) );
		$this->assertTrue( is_tag( array( $tag->term_id ) ) );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&paged=$matches[4]',
	public function test_ymd_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/04/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_day', 'is_date', 'is_paged' );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]',
	public function test_ymd() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/04/' );
		$this->assertQueryTrue( 'is_archive', 'is_day', 'is_date' );
	}

	// '([0-9]{4})/([0-9]{1,2})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&paged=$matches[3]',
	public function test_ym_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_month', 'is_paged' );
	}

	// '([0-9]{4})/([0-9]{1,2})/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]',
	public function test_ym() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/09/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_month' );
	}

	// '([0-9]{4})/page/?([0-9]{1,})/?$' => 'index.php?year=$matches[1]&paged=$matches[2]',
	public function test_y_paged() {
		update_option( 'posts_per_page', 2 );
		self::factory()->post->create_many( 3, array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/page/2/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_year', 'is_paged' );
	}

	// '([0-9]{4})/?$' => 'index.php?year=$matches[1]',
	public function test_y() {
		self::factory()->post->create( array( 'post_date' => '2007-09-04 00:00:00' ) );
		$this->go_to( '/2007/' );
		$this->assertQueryTrue( 'is_archive', 'is_date', 'is_year' );
	}

	// '([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)(/[0-9]+)?/?$' => 'index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&page=$matches[5]',
	public function test_post_paged_short() {
		$post_id = self::factory()->post->create(
			array(
				'post_date'    => '2007-09-04 00:00:00',
				'post_title'   => 'a-post-with-multiple-pages',
				'post_content' => 'Page 1 <!--nextpage--> Page 2',
			)
		);
		// A category is required for the verbose test that runs the same test
		// for a verbose structure.
		$c1 = self::factory()->category->create( ['name' => 'c1'] );
		wp_set_object_terms( $post_id, $c1, 'category' );
		$this->go_to( get_permalink( $post_id ) . '2/' );
		// Should is_paged be true also?
		$this->assertQueryTrue( 'is_single', 'is_singular' );

	}

	/**
	 * @expectedIncorrectUsage WP_Date_Query
	 */
	public function test_bad_dates() {
		$this->go_to( '/2013/13/13/' );
		$this->assertQueryTrue( 'is_404' );

		$this->go_to( '/2013/11/41/' );
		$this->assertQueryTrue( 'is_404' );
	}

	public function test_post_type_archive_with_tax_query() {
		$this->markTestSkipped();
		delete_option( 'rewrite_rules' );

		$cpt_name = 'ptawtq';
		register_post_type(
			$cpt_name,
			array(
				'taxonomies'  => array( 'post_tag', 'category' ),
				'rewrite'     => true,
				'has_archive' => true,
				'public'      => true,
			)
		);

		$tag_id  = self::factory()->tag->create( array( 'slug' => 'tag-slug' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => $cpt_name ) );
		wp_set_object_terms( $post_id, $tag_id, 'post_tag' );

		$this->go_to( '/ptawtq/' );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( $cpt_name ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_tax_query' ) );

		$this->go_to( '/ptawtq/' );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( $cpt_name ) );

		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_tax_query' ) );
	}

	public function pre_get_posts_with_tax_query( &$query ) {
		$term = get_term_by( 'slug', 'tag-slug', 'post_tag' );
		$query->set(
			'tax_query',
			array(
				array(
					'taxonomy' => 'post_tag',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			)
		);
	}

	public function test_post_type_array() {
		$this->markTestSkipped();
		delete_option( 'rewrite_rules' );

		$cpt_name = 'thearray';
		register_post_type(
			$cpt_name,
			array(
				'taxonomies'  => array( 'post_tag', 'category' ),
				'rewrite'     => true,
				'has_archive' => true,
				'public'      => true,
			)
		);
		self::factory()->post->create( array( 'post_type' => $cpt_name ) );

		$this->go_to( "/$cpt_name/" );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( $cpt_name ) );

		add_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_type_array' ) );

		$this->go_to( "/$cpt_name/" );
		$this->assertQueryTrue( 'is_post_type_archive', 'is_archive' );
		$this->assertSame( get_queried_object(), get_post_type_object( 'post' ) );

		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts_with_type_array' ) );
	}

	public function pre_get_posts_with_type_array( &$query ) {
		$query->set( 'post_type', array( 'post', 'thearray' ) );
	}

	public function test_is_single() {
		$post_id = self::factory()->post->create();
		$this->go_to( "/?p=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_single() );
		$this->assertTrue( $q->is_single );
		$this->assertFalse( $q->is_page );
		$this->assertTrue( is_single( $post ) );
		$this->assertTrue( is_single( $post->ID ) );
		$this->assertTrue( is_single( $post->post_title ) );
		$this->assertTrue( is_single( $post->post_name ) );
	}

	/**
	 * @ticket 16802
	 */
	public function test_is_single_with_parent() {
		// Use custom hierarchical post type.
		$post_type = 'test_hierarchical';

		register_post_type(
			$post_type,
			array(
				'hierarchical' => true,
				'rewrite'      => true,
				'has_archive'  => true,
				'public'       => true,
			)
		);

		// Create parent and child posts.
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => $post_type,
				'post_name' => 'foo',
			)
		);

		$post_id = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_name'   => 'bar',
				'post_parent' => $parent_id,
			)
		);

		// Tests.
		$this->go_to( "/?p=$post_id&post_type=$post_type" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_single() );
		$this->assertFalse( $q->is_page );
		$this->assertTrue( $q->is_single );
		$this->assertTrue( is_single( $post ) );
		$this->assertTrue( is_single( $post->ID ) );
		$this->assertTrue( is_single( $post->post_title ) );
		$this->assertTrue( is_single( $post->post_name ) );
		$this->assertTrue( is_single( 'foo/bar' ) );
		$this->assertFalse( is_single( $parent_id ) );
		$this->assertFalse( is_single( 'foo/bar/baz' ) );
		$this->assertFalse( is_single( 'bar/bar' ) );
		$this->assertFalse( is_single( 'foo' ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_single_with_slug_that_begins_with_a_number_that_clashes_with_another_post_id() {
		$p1 = self::factory()->post->create();

		$p2_name = $p1 . '-post';
		$p2      = self::factory()->post->create(
			array(
				'slug' => $p2_name,
			)
		);

		$this->go_to( "/?p=$p1" );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_single() );
		$this->assertTrue( $q->is_single( $p1 ) );
		$this->assertFalse( $q->is_single( $p2_name ) );
		$this->assertFalse( $q->is_single( $p2 ) );
	}

	/**
	 * @ticket 24612
	 */
	public function test_is_single_with_slug_that_clashes_with_attachment() {
		$this->set_permalink_structure( '/%postname%/' );

		$attachment_id = $this->factory->post->create(
			array(
				'post_type' => 'attachment',
			)
		);

		$post_id = $this->factory->post->create(
			array(
				'post_title' => get_post( $attachment_id )->post_title,
			)
		);

		$this->go_to( get_permalink( $post_id ) );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_single() );
		$this->assertTrue( $q->is_single( $post_id ) );
		$this->assertFalse( $q->is_404() );

		$this->set_permalink_structure();
	}

	public function test_is_page() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$this->go_to( "/?page_id=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_page() );
		$this->assertFalse( $q->is_single );
		$this->assertTrue( $q->is_page );
		$this->assertTrue( is_page( $post ) );
		$this->assertTrue( is_page( $post->ID ) );
		$this->assertTrue( is_page( $post->post_title ) );
		$this->assertTrue( is_page( $post->post_name ) );
	}

	/**
	 * @ticket 16802
	 */
	public function test_is_page_with_parent() {
		$parent_id = self::factory()->post->create(
			array(
				'post_type' => 'page',
				'post_name' => 'foo',
			)
		);
		$post_id   = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_name'   => 'bar',
				'post_parent' => $parent_id,
			)
		);
		$this->go_to( "/?page_id=$post_id" );

		$post = get_queried_object();
		$q    = $GLOBALS['wp_query'];

		$this->assertTrue( is_page() );
		$this->assertFalse( $q->is_single );
		$this->assertTrue( $q->is_page );
		$this->assertTrue( is_page( $post ) );
		$this->assertTrue( is_page( $post->ID ) );
		$this->assertTrue( is_page( $post->post_title ) );
		$this->assertTrue( is_page( $post->post_name ) );
		$this->assertTrue( is_page( 'foo/bar' ) );
		$this->assertFalse( is_page( $parent_id ) );
		$this->assertFalse( is_page( 'foo/bar/baz' ) );
		$this->assertFalse( is_page( 'bar/bar' ) );
		$this->assertFalse( is_page( 'foo' ) );
	}

	/**
	 * @ticket 24674
	 */
	public function test_is_category_with_slug_that_begins_with_a_number_that_clashes_with_another_category_id() {
		$c1 = self::factory()->category->create( ['name' => 'c1'] );

		$c2_name = $c1 . '-category';
		$c2      = self::factory()->category->create(
			array(
				'slug' => $c2_name,
			)
		);

		$this->go_to( '/category/c1' );

		$q = $GLOBALS['wp_query'];

		$this->assertTrue( $q->is_category() );
		$this->assertTrue( $q->is_category( $c1 ) );
		$this->assertFalse( $q->is_category( $c2_name ) );
		$this->assertFalse( $q->is_category( $c2 ) );
	}

	/*
	 * @ticket 18375
	 */
	public function test_is_page_template_other_post_type() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );
		update_post_meta( $post_id, '_wp_page_template', 'example.php' );
		$this->go_to( get_post_permalink( $post_id ) );
		$this->assertFalse( is_page_template( array( 'test.php' ) ) );
		$this->assertTrue( is_page_template( array( 'test.php', 'example.php' ) ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_category_should_not_match_numeric_id_to_name_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => "$t1-foo",
				'name'     => 'foo 2',
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_category( $t2 ) );
		$this->assertFalse( is_category( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_category_should_not_match_numeric_id_to_slug_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'category',
				'slug'     => 'foo-2',
				'name'     => "$t1 foo",
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_category( $t2 ) );
		$this->assertFalse( is_category( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_tag_should_not_match_numeric_id_to_name_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => "$t1-foo",
				'name'     => 'foo 2',
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_tag( $t2 ) );
		$this->assertFalse( is_tag( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_tag_should_not_match_numeric_id_to_slug_beginning_with_id() {
		$t1 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo',
				'name'     => 'foo',
			)
		);
		$t2 = self::factory()->term->create(
			array(
				'taxonomy' => 'post_tag',
				'slug'     => 'foo-2',
				'name'     => "$t1 foo",
			)
		);

		$this->go_to( get_term_link( $t2 ) );

		$this->assertTrue( is_tag( $t2 ) );
		$this->assertFalse( is_tag( $t1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_page_should_not_match_numeric_id_to_post_title_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => "$p1 Foo",
				'post_name'  => 'foo-2',
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_page( $p2 ) );
		$this->assertFalse( is_page( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_page_should_not_match_numeric_id_to_post_name_beginning_with_id() {
		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Foo',
				'post_name'  => "$p1-foo",
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_page( $p2 ) );
		$this->assertFalse( is_page( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_single_should_not_match_numeric_id_to_post_title_beginning_with_id() {
		$this->markTestSkipped();

		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => "$p1 Foo",
				'post_name'  => 'foo-2',
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_single( $p2 ) );
		$this->assertFalse( is_single( $p1 ) );
	}

	/**
	 * @ticket 35902
	 */
	public function test_is_single_should_not_match_numeric_id_to_post_name_beginning_with_id() {
		$this->markTestSkipped();

		$p1 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Foo',
				'post_name'  => 'foo',
			)
		);
		$p2 = self::factory()->post->create(
			array(
				'post_type'  => 'post',
				'post_title' => 'Foo',
				'post_name'  => "$p1-foo",
			)
		);

		$this->go_to( get_permalink( $p2 ) );

		$this->assertTrue( is_single( $p2 ) );
		$this->assertFalse( is_single( $p1 ) );
	}

	/**
	 * @ticket 44005
	 * @group privacy
	 */
	public function test_is_privacy_policy() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'  => 'page',
				'post_title' => 'Privacy Policy',
			)
		);

		update_option( 'wp_page_for_privacy_policy', $page_id );

		$this->go_to( get_permalink( $page_id ) );

		$this->assertQueryTrue( 'is_page', 'is_singular', 'is_privacy_policy' );
	}

}
