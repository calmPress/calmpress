<?php

/**
 * Test avatar related functions
 *
 * @group avatar
 */
class Tests_Avatar extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		require_once ABSPATH . '/wp-admin/includes/image.php';
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_user() {
		$url = get_avatar_url( 1 );

		$user = get_user_by( 'id', 1 );
		$url2 = get_avatar_url( $user );
		$this->assertSame( $url, $url2 );

		// no URL when user  do not have image avatar.
		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );
		$post    = get_post( $post_id );
		$url2    = get_avatar_url( $post );
		$this->assertSame( $url, $url2 );

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$user->set_avatar( get_post( $attachment_id ) );
		$url2 = get_avatar_url( $user, [ 'size' => 50 ] );
		$avatar_url = wp_get_attachment_image_url( $attachment_id, [50, 50] );
		$this->assertStringContainsString( $url2, $avatar_url );

		// No authors, no url
		$url2 = get_avatar_url( $post );
		$this->assertEquals( '', $url2 );

		// Author but no image, no url.
		$author1 = wp_insert_term( 'author1', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$author = new \calmpress\post_authors\Taxonomy_Based_Post_Author( get_term( $author1['term_id'] ) );
		wp_set_object_terms( $post_id, $author1, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$url2 = get_avatar_url( $post );
		$this->assertEquals( '', $url2 );

		// author with image need to give the image url.
		$author->set_image( get_post( $attachment_id ) );
		$url2 = get_avatar_url( $post );
		$avatar_url = wp_get_attachment_image_url( $attachment_id, [50, 50] );
		$this->assertStringContainsString( $url2, $avatar_url );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => 1,
			)
		);
		$comment    = get_comment( $comment_id );
		$url2       = get_avatar_url( $comment );
		$this->assertSame( $avatar_url, $url2 );

		// cleanup.
		wp_delete_post( $attachment_id, true );
	}

	protected $fake_url;
	/**
	 * @ticket 21195
	 */
	public function test_pre_get_avatar_url_filter() {
		$this->fake_url = 'haha wat';

		add_filter( 'pre_get_avatar_data', array( $this, 'pre_get_avatar_url_filter' ), 10, 1 );
		$url = get_avatar_url( 1 );
		remove_filter( 'pre_get_avatar_data', array( $this, 'pre_get_avatar_url_filter' ), 10 );

		$this->assertSame( $url, $this->fake_url );
	}
	public function pre_get_avatar_url_filter( $args ) {
		$args['url'] = $this->fake_url;
		return $args;
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_filter() {
		$this->fake_url = 'omg lol';

		add_filter( 'get_avatar_url', array( $this, 'get_avatar_url_filter' ), 10, 1 );
		$url = get_avatar_url( 1 );
		remove_filter( 'get_avatar_url', array( $this, 'get_avatar_url_filter' ), 10 );

		$this->assertSame( $url, $this->fake_url );
	}
	public function get_avatar_url_filter( $url ) {
		return $this->fake_url;
	}

	public function test_get_avatar_size() {
		$size = '100';
		$img  = get_avatar( 1, $size );
		// User do not have an image avatar and should generate a text one.
		$this->assertStringContainsString( 'height="100"', $img );
		$this->assertStringContainsString( 'width="100"', $img );
	}

	public function test_get_avatar_class() {
		$class = 'first';
		$img   = get_avatar( 1, 96, '', '', array( 'class' => $class ) );
		$this->assertStringContainsString( $class, $img );
	}

	protected $fake_img;
	/**
	 * @ticket 21195
	 */
	public function test_pre_get_avatar_filter() {
		$this->fake_img = 'YOU TOO?!';

		add_filter( 'pre_get_avatar', array( $this, 'pre_get_avatar_filter' ), 10, 1 );
		$img = get_avatar( 1 );
		remove_filter( 'pre_get_avatar', array( $this, 'pre_get_avatar_filter' ), 10 );

		$this->assertSame( $img, $this->fake_img );
	}
	public function pre_get_avatar_filter( $img ) {
		return $this->fake_img;
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_filter() {
		$this->fake_url = 'YA RLY';

		add_filter( 'get_avatar', array( $this, 'get_avatar_filter' ), 10, 1 );
		$img = get_avatar( 1 );
		remove_filter( 'get_avatar', array( $this, 'get_avatar_filter' ), 10 );

		$this->assertSame( $img, $this->fake_url );
	}
	public function get_avatar_filter( $img ) {
		return $this->fake_url;
	}
}
