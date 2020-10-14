<?php

/**
 * Test functions in wp-includes/author-template.php
 *
 * @group author
 * @group user
 */
class Tests_User_Author_Template extends WP_UnitTestCase {
	protected static $author_id = 0;
	protected static $post_id   = 0;

	private $permalink_structure;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$author_id = $factory->user->create(
			array(
				'role'        => 'author',
				'user_login'  => 'test_author',
				'description' => 'test_author',
			)
		);

		self::$post_id = $factory->post->create(
			array(
				'post_author'  => self::$author_id,
				'post_status'  => 'publish',
				'post_content' => rand_str(),
				'post_title'   => rand_str(),
				'post_type'    => 'post',
			)
		);
	}

	function setUp() {
		parent::setUp();

		setup_postdata( get_post( self::$post_id ) );
	}

	function tearDown() {
		wp_reset_postdata();
		parent::tearDown();
	}

	function test_get_the_author() {
		$author_name = get_the_author();
		$user        = new WP_User( self::$author_id );

		$this->assertEquals( $user->display_name, 'Anonymous' );
		$this->assertEquals( '', $author_name );
	}

	function test_get_the_author_meta() {
		$this->assertEquals( 'test_author', get_the_author_meta( 'login' ) );
		$this->assertEquals( 'test_author', get_the_author_meta( 'user_login' ) );
		$this->assertEquals( 'Anonymous', get_the_author_meta( 'display_name' ) );

		$this->assertEquals( 'test_author', trim( get_the_author_meta( 'description' ) ) );
		$this->assertEquals( 'test_author', get_the_author_meta( 'user_description' ) );
		add_user_meta( self::$author_id, 'user_description', 'user description' );
		$this->assertEquals( 'user description', get_user_meta( self::$author_id, 'user_description', true ) );
		// user_description in meta is ignored. The content of description is returned instead.
		// See #20285.
		$this->assertEquals( 'test_author', get_the_author_meta( 'user_description' ) );
		$this->assertEquals( 'test_author', trim( get_the_author_meta( 'description' ) ) );
		update_user_meta( self::$author_id, 'user_description', '' );
		$this->assertEquals( '', get_user_meta( self::$author_id, 'user_description', true ) );
		$this->assertEquals( 'test_author', get_the_author_meta( 'user_description' ) );
		$this->assertEquals( 'test_author', trim( get_the_author_meta( 'description' ) ) );

		$this->assertEquals( '', get_the_author_meta( 'does_not_exist' ) );
	}

	function test_get_the_author_meta_no_authordata() {
		unset( $GLOBALS['authordata'] );
		$this->assertEquals( '', get_the_author_meta( 'id' ) );
		$this->assertEquals( '', get_the_author_meta( 'user_login' ) );
		$this->assertEquals( '', get_the_author_meta( 'does_not_exist' ) );
	}

}
