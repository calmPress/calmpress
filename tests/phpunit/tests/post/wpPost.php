<?php

/**
 * @group post
 */
class Tests_Post_WpPost extends WP_UnitTestCase {
	protected static $post_id;

	public static function wpSetUpBeforeClass( $factory ) {
		global $wpdb;

		// Ensure that there is a post with ID 1.
		if ( ! get_post( 1 ) ) {
			$wpdb->insert(
				$wpdb->posts,
				array(
					'ID'         => 1,
					'post_title' => 'Post 1',
				)
			);
		}

		self::$post_id = self::factory()->post->create();
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_work_for_numeric_string() {
		$found = WP_Post::get_instance( (string) self::$post_id );

		$this->assertSame( self::$post_id, $found->ID );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_negative_number() {
		$found = WP_Post::get_instance( -self::$post_id );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_fail_for_non_numeric_string() {
		$found = WP_Post::get_instance( 'abc' );

		$this->assertFalse( $found );
	}

	/**
	 * @ticket 37738
	 */
	public function test_get_instance_should_succeed_for_float_that_is_equal_to_post_id() {
		$found = WP_Post::get_instance( 1.0 );

		$this->assertSame( 1, $found->ID );
	}

	/**
	 * Test avatar generation.
	 *
	 * @since calmPress 1.0.0
	 */
	function test_avatar() {
		$user = $this->factory->user->create( [ 'name' => 'test', 'display_name' => 'display name', 'description' => 'test description' ] );

		$pid = $this->factory->post->create( [
			'post_title' => 'test1',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$blank_avatar = new \calmpress\avatar\Blank_Avatar();

		// Test no author get blank avatar.
		$post = get_post( $pid );
		$this->assertEquals( $blank_avatar->html( 50, 50 ), $post->avatar()->html( 50, 50 ) );

		// One author, avatar is text based.
		// Test one author.
		$author1 = wp_insert_term( 'author1', 'calm_authors' );
		wp_set_object_terms( $pid, $author1['term_id'], 'calm_authors', true );
		$text_avatar = new \calmpress\avatar\Text_Based_Avatar( 'author1', '' );
		$this->assertEquals( $text_avatar->html( 50, 50 ), $post->avatar()->html( 50, 50 ) );

		// Add an image to the author.
		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		update_term_meta( $author1['term_id'], 'calm_featured_image', $attachment_id);
		$image_avatar = new \calmpress\avatar\Image_Based_Avatar( get_post( $attachment_id ) );
		$this->assertEquals( $image_avatar->html( 50, 50 ), $post->avatar()->html( 50, 50 ) );
	}
}
