<?php

/**
 * Tests to make sure query vars are as expected.
 *
 * @group query
 */
class Tests_Query_Vars extends WP_UnitTestCase {

	/**
	 * @ticket 35115
	 */
	public function testPublicQueryVarsAreAsExpected() {
		global $wp;

		// Re-initialise any dynamically-added public query vars:
		do_action( 'init' );

		$expected = array(

				// Static public query vars:
				'm',
				'p',
				'w',
				's',
				'search',
				'exact',
				'sentence',
				'page',
				'paged',
				'more',
				'order',
				'orderby',
				'year',
				'monthnum',
				'day',
				'hour',
				'minute',
				'second',
				'name',
				'tag',
				'static',
				'pagename',
				'page_id',
				'error',
				'preview',
				'robots',
				'cpage',
				'category_name',
				'attachment',

			// Dynamically added public query vars:
			'rest_route',
			\calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME,

		);
		sort( $expected );
		$actual = $wp->public_query_vars;
		sort( $actual );
		$this->assertEquals( $expected, $actual, 'Care should be taken when introducing new public query vars. See https://core.trac.wordpress.org/ticket/35115' );

		// Test that when feeds are enabled, "feed" is a valid public variable.
		update_option( 'calm_embedding_on', 0 );
		update_option( 'posts_per_rss', 5 );
		$this->go_to( '/' );
		$expected[] = 'feed';
		sort( $expected );
		$actual = $wp->public_query_vars;
		sort( $actual );
		$this->assertEquals( $expected, $actual, 'Care should be taken when introducing new public query vars. See https://core.trac.wordpress.org/ticket/35115' );

		// Test that when embeding is is enabled, "embed" is a valid public variable.
		update_option( 'posts_per_rss', 0 );
		update_option( 'calm_embedding_on', 1 );
		$this->go_to( '/' );
		$expected[] = 'embed';
		sort( $expected );
		$actual = $wp->public_query_vars;
		sort( $actual );
		$this->assertEquals( $expected, $actual, 'Care should be taken when introducing new public query vars. See https://core.trac.wordpress.org/ticket/35115' );
	}

}
