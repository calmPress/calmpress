<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_author_posts_link
 */
class Tests_User_GetTheAuthorPostsLink extends WP_UnitTestCase {
	protected static $author_id = 0;
	protected static $post_id   = 0;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'test_author',
				'display_name' => 'Test Author',
				'description'  => 'test_author',
				'user_url'     => 'http://example.com',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$author_id,
				'post_status'  => 'publish',
				'post_content' => 'content',
				'post_title'   => 'title',
				'post_type'    => 'post',
			)
		);
	}

	public function set_up() {
		global $post;

		parent::set_up();

		$post = get_post( self::$post_id );
		setup_postdata( get_post( self::$post_id ) );
	}

	/**
	 * @ticket 30355
	 */
	public function test_get_the_author_posts_link_with_permalinks() {

		// No authors
		$this->assertSame( '', get_the_author_posts_link() );

		// One author.
		$author1 = wp_insert_term( 'author1', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( self::$post_id, $author1, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$author = \calmpress\post_authors\Post_Authors_As_Taxonomy::post_authors( get_post( self::$post_id ) );
		$url = $author[0]->posts_url();

		$link = get_the_author_posts_link();
		$this->assertStringContainsString( $url, $link );
		$this->assertStringContainsString( 'Posts by author1', $link );
		$this->assertStringContainsString( '>author1</a>', $link );

		// Two authors.
		$author2_term = wp_insert_term( 'author2', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( self::$post_id, $author2_term, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$author2 = \calmpress\post_authors\Post_Authors_As_Taxonomy::post_authors( get_post( self::$post_id ) );
		$url = $author2[0]->posts_url();
		$url2 = $author2[1]->posts_url();

		$link = get_the_author_posts_link('the name %s');
		$this->assertStringContainsString( $url, $link );
		$this->assertStringContainsString( 'the name author1', $link );
		$this->assertStringContainsString( '>author1</a>', $link );

		$this->assertStringContainsString( $url2, $link );
		$this->assertStringContainsString( 'the name author2', $link );
		$this->assertStringContainsString( '>author2</a>', $link );
	}
}
