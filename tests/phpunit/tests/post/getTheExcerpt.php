<?php

/**
 * @group post
 * @group formatting
 */
class Tests_Post_GetTheExcerpt extends WP_UnitTestCase {

	/**
	 * @ticket 27246
	 */
	public function test_the_excerpt_invalid_post() {
		$this->assertSame( '', get_echo( 'the_excerpt' ) );
		$this->assertSame( '', get_the_excerpt() );
	}

	/**
	 * @ticket 27246
	 */
	public function test_the_excerpt() {
		$GLOBALS['post'] = self::factory()->post->create_and_get( array( 'post_excerpt' => 'Post excerpt' ) );
		$this->assertSame( "<p>Post excerpt</p>\n", get_echo( 'the_excerpt' ) );
		$this->assertSame( 'Post excerpt', get_the_excerpt() );
	}

	/**
	 * @ticket 27246
	 */
	public function test_the_excerpt_specific_post() {
		$GLOBALS['post'] = self::factory()->post->create_and_get( array( 'post_excerpt' => 'Foo' ) );
		$post_id         = self::factory()->post->create( array( 'post_excerpt' => 'Bar' ) );
		$this->assertSame( 'Bar', get_the_excerpt( $post_id ) );
	}
}
