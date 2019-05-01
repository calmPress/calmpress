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
	public function test_get_avatar_url_gravatar_url() {
		$url = get_avatar_url( 1 );
		$this->assertEquals( preg_match( '|^http?://[0-9]+.gravatar.com/avatar/[0-9a-f]{32}\?|', $url ), 1 );
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_size() {
		$url = get_avatar_url( 1 );
		$this->assertEquals( preg_match( '|\?.*s=96|', $url ), 1 );

		$args = array( 'size' => 100 );
		$url  = get_avatar_url( 1, $args );
		$this->assertEquals( preg_match( '|\?.*s=100|', $url ), 1 );
	}

	/**
	 * @ticket 21195
	 */
	public function test_get_avatar_url_user() {
		$url = get_avatar_url( 1 );

		$url2 = get_avatar_url( WP_TESTS_EMAIL );
		$this->assertEquals( $url, $url2 );

		$url2 = get_avatar_url( md5( WP_TESTS_EMAIL ) . '@md5.gravatar.com' );
		$this->assertEquals( $url, $url2 );

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

	public function test_get_avatar() {
		$img = get_avatar( 1 );
		$this->assertEquals( preg_match( "|^<img alt='[^']*' src='[^']*' srcset='[^']*' class='[^']*' height='[^']*' width='[^']*' />$|", $img ), 1 );
	}

	public function test_get_avatar_size() {
		$size = '100';
		$img  = get_avatar( 1, $size );
		$this->assertEquals( preg_match( "|^<img .*height='$size'.*width='$size'|", $img ), 1 );
	}

	public function test_get_avatar_alt() {
		$alt = 'Mr Hyde';
		$img = get_avatar( 1, 96, '', $alt );
		$this->assertEquals( preg_match( "|^<img alt='$alt'|", $img ), 1 );
	}

	public function test_get_avatar_class() {
		$class = 'first';
		$img   = get_avatar( 1, 96, '', '', array( 'class' => $class ) );
		$this->assertEquals( preg_match( "|^<img .*class='[^']*{$class}[^']*'|", $img ), 1 );
	}

	public function test_get_avatar_force_display() {
		$old = get_option( 'show_avatars' );
		update_option( 'show_avatars', false );

		$this->assertFalse( get_avatar( 1 ) );

		$this->assertNotEmpty( get_avatar( 1, 96, '', '', array( 'force_display' => true ) ) );

		update_option( 'show_avatars', $old );
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
