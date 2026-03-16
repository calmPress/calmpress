<?php

/**
 * @group meta
 * @group slashes
 * @ticket 21767
 */
class Tests_Meta_Slashes extends WP_UnitTestCase {

	/*
	 * It is important to test with both even and odd numbered slashes,
	 * as KSES does a strip-then-add slashes in some of its function calls.
	 */

	const SLASH_1 = 'String with 1 slash \\';
	const SLASH_2 = 'String with 2 slashes \\\\';
	const SLASH_3 = 'String with 3 slashes \\\\\\';
	const SLASH_4 = 'String with 4 slashes \\\\\\\\';
	const SLASH_5 = 'String with 5 slashes \\\\\\\\\\';
	const SLASH_6 = 'String with 6 slashes \\\\\\\\\\\\';
	const SLASH_7 = 'String with 7 slashes \\\\\\\\\\\\\\';

	protected static $editor_id;
	protected static $post_id;
	protected static $comment_id;
	protected static $user_id;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$editor_id  = $factory->user->create( array( 'role' => 'editor' ) );
		self::$post_id    = $factory->post->create();
		self::$comment_id = $factory->comment->create( array( 'comment_post_ID' => self::$post_id ) );
		self::$user_id    = $factory->user->create();
	}

	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$editor_id );
	}

	/**
	 * Tests the legacy model function that expects slashed data.
	 */
	public function test_add_post_meta() {
		$post_id = self::$post_id;

		add_post_meta( $post_id, 'slash_test_1', addslashes( self::SLASH_1 ) );
		add_post_meta( $post_id, 'slash_test_2', addslashes( self::SLASH_3 ) );
		add_post_meta( $post_id, 'slash_test_3', addslashes( self::SLASH_4 ) );

		$this->assertSame( self::SLASH_1, get_post_meta( $post_id, 'slash_test_1', true ) );
		$this->assertSame( self::SLASH_3, get_post_meta( $post_id, 'slash_test_2', true ) );
		$this->assertSame( self::SLASH_4, get_post_meta( $post_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the legacy model function that expects slashed data.
	 */
	public function test_update_post_meta() {
		$post_id = self::$post_id;

		update_post_meta( $post_id, 'slash_test_1', addslashes( self::SLASH_1 ) );
		update_post_meta( $post_id, 'slash_test_2', addslashes( self::SLASH_3 ) );
		update_post_meta( $post_id, 'slash_test_3', addslashes( self::SLASH_4 ) );

		$this->assertSame( self::SLASH_1, get_post_meta( $post_id, 'slash_test_1', true ) );
		$this->assertSame( self::SLASH_3, get_post_meta( $post_id, 'slash_test_2', true ) );
		$this->assertSame( self::SLASH_4, get_post_meta( $post_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_add_comment_meta() {
		$comment_id = self::$comment_id;

		add_comment_meta( $comment_id, 'slash_test_1', self::SLASH_1 );
		add_comment_meta( $comment_id, 'slash_test_2', self::SLASH_3 );
		add_comment_meta( $comment_id, 'slash_test_3', self::SLASH_5 );

		$this->assertSame( wp_unslash( self::SLASH_1 ), get_comment_meta( $comment_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( self::SLASH_3 ), get_comment_meta( $comment_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( self::SLASH_5 ), get_comment_meta( $comment_id, 'slash_test_3', true ) );

		add_comment_meta( $comment_id, 'slash_test_4', self::SLASH_2 );
		add_comment_meta( $comment_id, 'slash_test_5', self::SLASH_4 );
		add_comment_meta( $comment_id, 'slash_test_6', self::SLASH_6 );

		$this->assertSame( wp_unslash( self::SLASH_2 ), get_comment_meta( $comment_id, 'slash_test_4', true ) );
		$this->assertSame( wp_unslash( self::SLASH_4 ), get_comment_meta( $comment_id, 'slash_test_5', true ) );
		$this->assertSame( wp_unslash( self::SLASH_6 ), get_comment_meta( $comment_id, 'slash_test_6', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_update_comment_meta() {
		$comment_id = self::$comment_id;

		add_comment_meta( $comment_id, 'slash_test_1', 'foo' );
		add_comment_meta( $comment_id, 'slash_test_2', 'foo' );
		add_comment_meta( $comment_id, 'slash_test_3', 'foo' );

		update_comment_meta( $comment_id, 'slash_test_1', self::SLASH_1 );
		update_comment_meta( $comment_id, 'slash_test_2', self::SLASH_3 );
		update_comment_meta( $comment_id, 'slash_test_3', self::SLASH_5 );

		$this->assertSame( wp_unslash( self::SLASH_1 ), get_comment_meta( $comment_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( self::SLASH_3 ), get_comment_meta( $comment_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( self::SLASH_5 ), get_comment_meta( $comment_id, 'slash_test_3', true ) );

		update_comment_meta( $comment_id, 'slash_test_1', self::SLASH_2 );
		update_comment_meta( $comment_id, 'slash_test_2', self::SLASH_4 );
		update_comment_meta( $comment_id, 'slash_test_3', self::SLASH_6 );

		$this->assertSame( wp_unslash( self::SLASH_2 ), get_comment_meta( $comment_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( self::SLASH_4 ), get_comment_meta( $comment_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( self::SLASH_6 ), get_comment_meta( $comment_id, 'slash_test_3', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_add_user_meta() {
		$user_id = self::$user_id;

		add_user_meta( $user_id, 'slash_test_1', self::SLASH_1 );
		add_user_meta( $user_id, 'slash_test_2', self::SLASH_3 );
		add_user_meta( $user_id, 'slash_test_3', self::SLASH_5 );

		$this->assertSame( wp_unslash( self::SLASH_1 ), get_user_meta( $user_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( self::SLASH_3 ), get_user_meta( $user_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( self::SLASH_5 ), get_user_meta( $user_id, 'slash_test_3', true ) );

		add_user_meta( $user_id, 'slash_test_4', self::SLASH_2 );
		add_user_meta( $user_id, 'slash_test_5', self::SLASH_4 );
		add_user_meta( $user_id, 'slash_test_6', self::SLASH_6 );

		$this->assertSame( wp_unslash( self::SLASH_2 ), get_user_meta( $user_id, 'slash_test_4', true ) );
		$this->assertSame( wp_unslash( self::SLASH_4 ), get_user_meta( $user_id, 'slash_test_5', true ) );
		$this->assertSame( wp_unslash( self::SLASH_6 ), get_user_meta( $user_id, 'slash_test_6', true ) );
	}

	/**
	 * Tests the model function that expects slashed data.
	 */
	public function test_update_user_meta() {
		$user_id = self::$user_id;

		add_user_meta( $user_id, 'slash_test_1', 'foo' );
		add_user_meta( $user_id, 'slash_test_2', 'foo' );
		add_user_meta( $user_id, 'slash_test_3', 'foo' );

		update_user_meta( $user_id, 'slash_test_1', self::SLASH_1 );
		update_user_meta( $user_id, 'slash_test_2', self::SLASH_3 );
		update_user_meta( $user_id, 'slash_test_3', self::SLASH_5 );

		$this->assertSame( wp_unslash( self::SLASH_1 ), get_user_meta( $user_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( self::SLASH_3 ), get_user_meta( $user_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( self::SLASH_5 ), get_user_meta( $user_id, 'slash_test_3', true ) );

		update_user_meta( $user_id, 'slash_test_1', self::SLASH_2 );
		update_user_meta( $user_id, 'slash_test_2', self::SLASH_4 );
		update_user_meta( $user_id, 'slash_test_3', self::SLASH_6 );

		$this->assertSame( wp_unslash( self::SLASH_2 ), get_user_meta( $user_id, 'slash_test_1', true ) );
		$this->assertSame( wp_unslash( self::SLASH_4 ), get_user_meta( $user_id, 'slash_test_2', true ) );
		$this->assertSame( wp_unslash( self::SLASH_6 ), get_user_meta( $user_id, 'slash_test_3', true ) );
	}
}
