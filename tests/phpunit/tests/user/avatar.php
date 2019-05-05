<?php

/**
 * @group user
 */
class Tests_User_Avatar extends WP_UnitTestCase {
	public function test_avatar() {

		$user_id = $this->factory->user->create(
			[
				'role'         => 'author',
				'user_login'   => 'test_author',
				'user_email'   => 'test@test.com',
				'display_name' => 'test',
			]
		);

		$user   = get_user_by( 'id', $user_id );

		/*
		 * With no avatar image the avatar should be based on display name and
		 * email address.
		 */
		$avatar = $user->avatar();

		// make sure that the result is the same as if we initiated the text based
		// avatar directly with the name and email.
		$text_avatar = new \calmpress\avatar\Text_Based_Avatar( 'test', 'test@test.com' );
		$this->assertEquals( $text_avatar->html(50,50), $avatar->html( 50, 50 ) );

		/*
		 * With avatar image the avatar should be based on it.
		 */
	 	$file = DIR_TESTDATA . '/images/canola.jpg';
 		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$attachment = get_post( $attachment_id );
		$user->set_avatar( $attachment );
		$avatar = $user->avatar();

		// make sure that the result is the same as if we initiated the text based
		// avatar directly with the name and email.
		$image_avatar = new \calmpress\avatar\Image_Based_Avatar( $attachment );
		$this->assertEquals( $image_avatar->html(50,50), $avatar->html( 50, 50 ) );
	}
}
