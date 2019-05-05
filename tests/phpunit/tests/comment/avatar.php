<?php

/**
 * @group comment
 */
class Tests_Comment_Avatar extends WP_UnitTestCase {
	public function test_avatar() {

		/*
		 * Test avatar for comment left by non user is a text based avatar
		 * based on the commentor name and email
		 */
		$pid = $this->factory()->post->create();
		$cid = $this->factory()->comment->create(
			[
				'comment_author'       => 'test the best',
				'comment_author_email' => 'test@best.com',
				'comment_post_ID'      => $pid,
				'comment_content'      => '1',
				'comment_date_gmt'     => date( 'Y-m-d H:i:s', time() - 100 ),
			]
		);

		$com    = get_comment( $cid );
		$avatar = $com->avatar();

		// make sure that the result is the same as if we initiated the text based
		// avatar directly with the name and email.
		$text_avatar = new \calmpress\avatar\Text_Based_Avatar( 'test the best', 'test@best.com' );
		$this->assertEquals( $text_avatar->html(50,50), $avatar->html( 50, 50 ) );

		/*
		 * Test avatar for comment left by a user is the user's avatar.
		 */
		$user_id = $this->factory->user->create(
			[
				'role'         => 'author',
				'user_login'   => 'test_author',
				'user_email'   => 'test@test.com',
				'display_name' => 'test',
			]
		);

		$user   = get_user_by( 'id', $user_id );

		$pid = $this->factory()->post->create();
		$cid = $this->factory()->comment->create(
			[
				'comment_author'       => 'test the best',
				'comment_author_email' => 'test@best.com',
				'comment_post_ID'      => $pid,
				'comment_content'      => '1',
				'comment_date_gmt'     => date( 'Y-m-d H:i:s', time() - 100 ),
				'user_id'              => $user_id,
			]
		);

		$com    = get_comment( $cid );
		$avatar = $com->avatar();

		// make sure that the result is the same as if we initiated the text based
		// avatar directly with the name and email.
		$user_avatar = $user->avatar();
		$this->assertEquals( $user_avatar->html(50,50), $avatar->html( 50, 50 ) );
	}
}
