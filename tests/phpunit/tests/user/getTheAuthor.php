<?php

/**
 * @group author
 * @group user
 *
 * @covers ::get_the_author
 */
class Tests_User_GetTheAuthor extends WP_UnitTestCase {
	protected static $author_id = 0;
	protected static $post_id   = 0;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'         => 'author',
				'user_login'   => 'test_author',
				'display_name' => 'Test Author',
				'description'  => 'test_author',
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
		setup_postdata( $post );
	}

	public function test_get_the_author() {
		$author_name = get_the_author();

		// No author term associated.
		$this->assertSame( '', $author_name );

		// Associate an author
		$author_term_id = self::factory()->term->create( array( 'taxonomy' => \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, 'name' => 'zack' ) );
		wp_set_object_terms( self::$post_id, $author_term_id, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$author_name = get_the_author();
		$this->assertSame( 'zack', $author_name );
	}

	/**
	 * @ticket 58157
	 */
	public function test_get_the_author_should_return_empty_string_if_authordata_is_not_set() {
		unset( $GLOBALS['authordata'] );

		$this->assertSame( '', get_the_author() );
	}
}
