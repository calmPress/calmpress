<?php

/**
 * @group comment
 */
class Tests_Comment_GetCommentLink extends WP_UnitTestCase {
	protected static $p;
	protected static $comments = array();

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		$now     = time();
		self::$p = $factory->post->create();

		self::$comments[] = $factory->comment->create(
			array(
				'comment_post_ID'  => self::$p,
				'comment_content'  => '1',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 100 ),
			)
		);
		self::$comments[] = $factory->comment->create(
			array(
				'comment_post_ID'  => self::$p,
				'comment_content'  => '2',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 200 ),
			)
		);
		self::$comments[] = $factory->comment->create(
			array(
				'comment_post_ID'  => self::$p,
				'comment_content'  => '3',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 300 ),
			)
		);
		self::$comments[] = $factory->comment->create(
			array(
				'comment_post_ID'  => self::$p,
				'comment_content'  => '4',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 400 ),
			)
		);
		self::$comments[] = $factory->comment->create(
			array(
				'comment_post_ID'  => self::$p,
				'comment_content'  => '4',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 500 ),
			)
		);
		self::$comments[] = $factory->comment->create(
			array(
				'comment_post_ID'  => self::$p,
				'comment_content'  => '4',
				'comment_date_gmt' => gmdate( 'Y-m-d H:i:s', $now - 600 ),
			)
		);

	}

	/**
	 * @ticket 34068
	 */
	public function test_default_comments_page_oldest_default_page_should_not_have_cpage() {
		update_option( 'default_comments_page', 'oldest' );
		update_option( 'comments_per_page', 2 );

		$found = get_comment_link( self::$comments[5] );

		$this->assertStringNotContainsString( 'page', $found );
	}

	/**
	 * @ticket 34946
	 */
	public function test_should_not_contain_comment_page_1_when_pagination_is_disabled() {
		$this->set_permalink_structure( '/%postname%/' );

		$found = get_comment_link( self::$comments[1] );

		$this->assertStringNotContainsString( 'comment-page-1', $found );
	}
}
