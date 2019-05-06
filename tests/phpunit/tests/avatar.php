<?php

/**
 * Test avatar related functions
 *
 * @group avatar
 */
class Tests_Avatar extends WP_UnitTestCase {

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_user() {
		$url = get_avatar_url( 1 );

		$user = get_user_by( 'id', 1 );
		$url2 = get_avatar_url( $user );
		$this->assertEquals( $url, $url2 );

		$post_id = self::factory()->post->create( array( 'post_author' => 1 ) );
		$post    = get_post( $post_id );
		$url2    = get_avatar_url( $post );
		$this->assertEquals( $url, $url2 );

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $post_id,
				'user_id'         => 1,
			)
		);
		$comment    = get_comment( $comment_id );
		$url2       = get_avatar_url( $comment );
		$this->assertEquals( $url, $url2 );
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

		$this->assertEquals( $url, $this->fake_url );
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

		$this->assertEquals( $url, $this->fake_url );
	}
	public function get_avatar_url_filter( $url ) {
		return $this->fake_url;
	}

	public function test_get_avatar_size() {
		$size = '100';
		$img  = get_avatar( 1, $size );
		// User do not have an image avatar and should generate a text one.
		$this->assertContains( 'height:100px', $img );
		$this->assertContains( 'width:100px', $img );
	}

	public function test_get_avatar_class() {
		$class = 'first';
		$img   = get_avatar( 1, 96, '', '', array( 'class' => $class ) );
		$this->assertContains( $class, $img );
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

		$this->assertEquals( $img, $this->fake_img );
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

		$this->assertEquals( $img, $this->fake_url );
	}
	public function get_avatar_filter( $img ) {
		return $this->fake_url;
	}
}
