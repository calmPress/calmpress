<?php
/**
 * Tests Canonical redirections.
 *
 * In the process of doing so, it also tests WP, WP_Rewrite and WP_Query, A fail here may show a bug in any one of these areas.
 *
 * @group canonical
 * @group rewrite
 * @group query
 */
class Tests_Canonical extends WP_Canonical_UnitTestCase {

	public function set_up() {

		// Tests the results of adding feeds using the filter as well.
		add_filter( 'calm_feed_types', function ( array $feeds ) {
			$feeds['atom'] = 'atom';
			return $feeds;
		}, 10, 1 );

		parent::set_up();
		wp_set_current_user( self::$author_id );
	}

	/**
	 * @dataProvider data_canonical
	 */
	public function test_canonical( $test_url, $expected, $ticket = 0, $expected_doing_it_wrong = array() ) {

		// By default the option is 0 and rewrite rules for feeds to not work.
		// Setting it to non zero for the test to make sense.
		update_option( 'posts_per_rss', 5 );

		if ( false !== strpos( $test_url, '%d' ) ) {
			if ( false !== strpos( $test_url, '/?author=%d' ) ) {
				$test_url = sprintf( $test_url, self::$author_id );
			}
			if ( false !== strpos( $test_url, '?cat=%d' ) ) {
				$test_url = sprintf( $test_url, self::$terms[ $expected['url'] ] );
			}
		}

		$this->assertCanonical( $test_url, $expected, $ticket, $expected_doing_it_wrong );
	}

	public function data_canonical() {
		/*
		 * Data format:
		 * [0]: Test URL.
		 * [1]: Expected results: Any of the following can be used.
		 *      array( 'url': expected redirection location, 'qv': expected query vars to be set via the rewrite AND $_GET );
		 *      array( expected query vars to be set, same as 'qv' above )
		 *      (string) expected redirect location
		 * [2]: (optional) The ticket the test refers to, Can be skipped if unknown.
		 * [3]: (optional) Array of class/function names expected to throw `_doing_it_wrong()` notices.
		 */

		// Please Note: A few test cases are commented out below, look at the test case following it.
		// In most cases it's simply showing 2 options for the "proper" redirect.
		return array(
			// Categories.
			array(
				'/category/uncategorized/',
				array(
					'url' => '/category/uncategorized/',
					'qv'  => array( 'category_name' => 'uncategorized' ),
				),
			),
			array(
				'/category/uncategorized/page/2/',
				array(
					'url' => '/category/uncategorized/page/2/',
					'qv'  => array(
						'category_name' => 'uncategorized',
						'paged'         => 2,
					),
				),
			),

			// Categories & intersections with other vars.
			array(
				'/category/uncategorized/?tag=post-formats',
				array(
					'url' => '/category/uncategorized/?tag=post-formats',
					'qv'  => array(
						'category_name' => 'uncategorized',
						'tag'           => 'post-formats',
					),
				),
			),

			// Taxonomies with extra query vars.
			array( '/category/cat-a/page/1/?test=one%20two', '/category/cat-a/?test=one%20two', 18086 ), // Extra query vars should stay encoded.

			// Categories with dates.
			array(
				'/2008/04/?cat=1',
				array(
					'url' => '/2008/04/?cat=1',
					'qv'  => array(
						'cat'      => '1',
						'year'     => '2008',
						'monthnum' => '04',
					),
				),
				17661,
			),
			/*
			array(
				'/2008/?category_name=cat-a',
					array(
						'url' => '/2008/?category_name=cat-a',
						'qv'  => array(
							'category_name' => 'cat-a',
							'year'          => '2008'
						)
					)
			),
			*/

			// Pages.
			array( '/child-page-1/', '/parent-page/child-page-1/' ),
			array( '/abo', '/about/' ),
			array( '/parent/child1/grandchild/', '/parent/child1/grandchild/' ),
			array( '/parent/child2/grandchild/', '/parent/child2/grandchild/' ),

			// Posts.
			array( '?p=587', '/2008/06/02/post-format-test-audio/' ),
			array( '/?name=images-test', '/2008/09/03/images-test/' ),
			// Incomplete slug should resolve and remove the ?name= parameter.
			array( '/?name=images-te', '/2008/09/03/images-test/', 20374 ),
			// Page slug should resolve to post slug and remove the ?pagename= parameter.
			array( '/?pagename=images-test', '/2008/09/03/images-test/', 20374 ),

			array( '/2008/06/02/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),
			array( '/2008/06/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),
			array( '/2008/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),
			array( '/2010/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ), // A year the post is not in.
			array( '/post-format-test-au/', '/2008/06/02/post-format-test-audio/' ),

			// Pagination.
			array(
				'/2008/09/03/multipage-post-test/3/',
				array(
					'url' => '/2008/09/03/multipage-post-test/3/',
					'qv'  => array(
						'name'     => 'multipage-post-test',
						'year'     => '2008',
						'monthnum' => '09',
						'day'      => '03',
						'page'     => '3',
					),
				),
			),
			array( '/2008/09/03/multipage-post-test/?page=3', '/2008/09/03/multipage-post-test/3/' ),
			array( '/2008/09/03/multipage-post-te?page=3', '/2008/09/03/multipage-post-test/3/' ),

			array( '/2008/09/03/non-paged-post-test/3/', '/2008/09/03/non-paged-post-test/' ),
			array( '/2008/09/03/non-paged-post-test/?page=3', '/2008/09/03/non-paged-post-test/' ),

			// Comments.
			array( '/2008/03/03/comment-test/?cpage=2', '/2008/03/03/comment-test/comment-page-2/' ),

			// Dates.
			array( '/?m=2008', '/2008/' ),
			array( '/?m=200809', '/2008/09/' ),
			array( '/?m=20080905', '/2008/09/05/' ),

			array( '/2008/?day=05', '/2008/?day=05' ), // No redirect.
			array( '/2008/09/?day=05', '/2008/09/05/' ),
			array( '/2008/?monthnum=9', '/2008/09/' ),

			array( '/?year=2008', '/2008/' ),

			array( '/2012/13/', '/2012/' ),
			array( '/2012/11/51/', '/2012/11/', 0, array( 'WP_Date_Query' ) ),

			// Feeds.
			array( '/feed/rss2', '/feed/rss2/' ),

			// Index.
			array( '/?paged=1', '/' ),
			array( '/page/1/', '/' ),
			array( '/page1/', '/' ),
			array( '/?paged=2', '/page/2/' ),
			array( '/page2/', '/page/2/' ),

			// Misc.
			array( '/2008%20', '/2008' ),
			array( '//2008////', '/2008/' ),
		);
	}

	/**
	 * @ticket 16557
	 */
	public function test_do_redirect_guess_404_permalink() {
		// Test disable do_redirect_guess_404_permalink().
		add_filter( 'do_redirect_guess_404_permalink', '__return_false' );
		$this->go_to( '/child-page-1' );
		$this->assertFalse( redirect_guess_404_permalink() );
	}

	/**
	 * @ticket 16557
	 */
	public function test_pre_redirect_guess_404_permalink() {
		// Test short-circuit filter.
		add_filter(
			'pre_redirect_guess_404_permalink',
			static function() {
				return 'wp';
			}
		);
		$this->go_to( '/child-page-1' );
		$this->assertSame( 'wp', redirect_guess_404_permalink() );
	}

	/**
	 * @ticket 16557
	 */
	public function test_strict_redirect_guess_404_permalink() {
		$post = self::factory()->post->create(
			array(
				'post_title' => 'strict-redirect-guess-404-permalink',
			)
		);

		$this->go_to( 'strict-redirect' );

		// Test default 'non-strict' redirect guess.
		$this->assertSame( get_permalink( $post ), redirect_guess_404_permalink() );

		// Test 'strict' redirect guess.
		add_filter( 'strict_redirect_guess_404_permalink', '__return_true' );
		$this->assertFalse( redirect_guess_404_permalink() );
	}

	/**
	 * Ensure multiple post types do not throw a notice.
	 *
	 * @ticket 43056
	 */
	public function test_redirect_guess_404_permalink_post_types() {
		/*
		 * Sample-page is intentionally missspelt as sample-pag to ensure
		 * the 404 post permalink guessing runs.
		 *
		 * Please do not correct the apparent typo.
		 */

		// String format post type.
		$this->assertCanonical( '/?name=sample-pag&post_type=page', '/sample-page/' );
		// Array formatted post type or types.
		$this->assertCanonical( '/?name=sample-pag&post_type[]=page', '/sample-page/' );
		$this->assertCanonical( '/?name=sample-pag&post_type[]=page&post_type[]=post', '/sample-page/' );
	}

	/**
	 * @ticket 43745
	 */
	public function test_utf8_query_keys_canonical() {
		$p = self::factory()->post->create(
			array(
				'post_type' => 'page',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $p );

		$this->go_to( get_permalink( $p ) );

		$url = redirect_canonical( add_query_arg( '%D0%BA%D0%BE%D0%BA%D0%BE%D0%BA%D0%BE', 1, site_url( '/' ) ), false );
		$this->assertNull( $url );

		delete_option( 'page_on_front' );
	}
}
