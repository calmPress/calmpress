<?php

/**
 * @group comment
 *
 * Testing items that are only testable by grabbing the markup of `comments_template()` from the output buffer.
 */
class Tests_Comment_CommentsTemplate extends WP_UnitTestCase {

	/**
	 * @ticket 8071
	 */
	public function test_should_respect_comment_order_asc_when_default_comments_page_is_newest() {
		$now       = time();
		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 ),
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '2',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 200 ),
			)
		);

		update_option( 'comment_order', 'asc' );
		update_option( 'default_comments_page', 'newest' );

		$this->go_to( get_permalink( $p ) );
		$found = get_echo( 'comments_template' );

		// Life in the fast lane.
		$comments = preg_match_all( '/id="comment-([0-9]+)"/', $found, $matches );

		$found_cids = array_map( 'intval', $matches[1] );
		$this->assertSame( array( $comment_2, $comment_1 ), $found_cids );
	}

	/**
	 * @ticket 8071
	 */
	public function test_should_respect_comment_order_desc_when_default_comments_page_is_newest() {
		$now       = time();
		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 ),
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '2',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 200 ),
			)
		);

		update_option( 'comment_order', 'desc' );
		update_option( 'default_comments_page', 'newest' );

		$this->go_to( get_permalink( $p ) );
		$found = get_echo( 'comments_template' );

		// Life in the fast lane.
		$comments = preg_match_all( '/id="comment-([0-9]+)"/', $found, $matches );

		$found_cids = array_map( 'intval', $matches[1] );
		$this->assertSame( array( $comment_1, $comment_2 ), $found_cids );
	}

	/**
	 * @ticket 8071
	 */
	public function test_should_respect_comment_order_asc_when_default_comments_page_is_oldest() {
		$now       = time();
		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 ),
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '2',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 200 ),
			)
		);

		update_option( 'comment_order', 'asc' );
		update_option( 'default_comments_page', 'oldest' );

		$this->go_to( get_permalink( $p ) );
		$found = get_echo( 'comments_template' );

		// Life in the fast lane.
		$comments = preg_match_all( '/id="comment-([0-9]+)"/', $found, $matches );

		$found_cids = array_map( 'intval', $matches[1] );
		$this->assertSame( array( $comment_2, $comment_1 ), $found_cids );
	}

	/**
	 * @ticket 8071
	 */
	public function test_should_respect_comment_order_desc_when_default_comments_page_is_oldest() {
		$now       = time();
		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 ),
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '2',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 200 ),
			)
		);

		update_option( 'comment_order', 'desc' );
		update_option( 'default_comments_page', 'oldest' );

		$this->go_to( get_permalink( $p ) );
		$found = get_echo( 'comments_template' );

		// Life in the fast lane.
		$comments = preg_match_all( '/id="comment-([0-9]+)"/', $found, $matches );

		$found_cids = array_map( 'intval', $matches[1] );
		$this->assertSame( array( $comment_1, $comment_2 ), $found_cids );
	}

	/**
	 * @ticket 35378
	 */
	public function test_hierarchy_should_be_ignored_when_threading_is_disabled() {
		$now       = time();
		$p         = self::factory()->post->create();
		$comment_1 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '1',
				'comment_approved' => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 ),
			)
		);
		$comment_2 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '2',
				'comment_approved' => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 300 ),
			)
		);
		$comment_3 = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $p,
				'comment_content'  => '3',
				'comment_approved' => '1',
				'comment_parent'   => $comment_1,
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 200 ),
			)
		);

		update_option( 'comment_order', 'asc' );
		update_option( 'thread_comments', 0 );

		$this->go_to( get_permalink( $p ) );
		$found = get_echo( 'comments_template' );

		// Find the found comments in the markup.
		preg_match_all( '|id="comment-([0-9]+)|', $found, $matches );

		$found_cids = array_map( 'intval', $matches[1] );
		$this->assertSame( array( $comment_2, $comment_3, $comment_1 ), $found_cids );
	}
}
