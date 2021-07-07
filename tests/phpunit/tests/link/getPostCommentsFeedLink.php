<?php
/**
 * @group link
 * @covers ::get_post_comments_feed_link
 */
class Tests_Link_GetPostCommentsFeedLink extends WP_UnitTestCase {

	public function test_post_link() {
		$post_id = self::factory()->post->create();

		$link     = get_post_comments_feed_link( $post_id );
		$expected = get_permalink( $post_id ) . 'feed/' ;

		$this->assertSame( $expected, $link );
	}

	public function test_post_pretty_link() {
		$this->set_permalink_structure( '/%year%/%monthnum%/%day%/%postname%/' );

		$post_id = self::factory()->post->create();

		$link     = get_post_comments_feed_link( $post_id );
		$expected = get_permalink( $post_id ) . 'feed/';

		$this->assertSame( $expected, $link );
	}
}
