<?php

/**
 * @group user
 */
class Tests_User_wpDeleteUser extends WP_UnitTestCase {

	/**
	 * Test that usermeta cache is cleared after user deletion.
	 *
	 * @ticket 19500
	 */
	public function test_get_blogs_of_user() {
		// Logged out users don't have blogs.
		$this->assertSame( array(), get_blogs_of_user( 0 ) );

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$blogs   = get_blogs_of_user( $user_id );
		$this->assertSame( array( 1 ), array_keys( $blogs ) );

		// Deleted users retain their record and site membership on single-site installations.
		self::delete_user( $user_id );

		$user = new WP_User( $user_id );
		if ( is_multisite() ) {
			$this->assertFalse( $user->exists(), 'WP_User->exists' );
			$this->assertSame( array(), get_blogs_of_user( $user_id ) );
		} else {
			$this->assertTrue( $user->exists(), 'WP_User->exists' );
			$this->assertSame( array( 1 ), array_keys( get_blogs_of_user( $user_id ) ) );
		}
	}

	/**
	 * Test that usermeta cache is cleared after user deletion.
	 *
	 * @ticket 19500
	 */
	public function test_is_user_member_of_blog() {
		$old_current = get_current_user_id();

		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$this->assertTrue( is_user_member_of_blog() );
		$this->assertTrue( is_user_member_of_blog( 0, 0 ) );
		$this->assertTrue( is_user_member_of_blog( 0, get_current_blog_id() ) );
		$this->assertTrue( is_user_member_of_blog( $user_id ) );
		$this->assertTrue( is_user_member_of_blog( $user_id, get_current_blog_id() ) );

		// Will only remove the user from the current site in multisite; this is desired
		// and will achieve the desired effect with is_user_member_of_blog().
		wp_delete_user( $user_id );

		if ( is_multisite() ) {
			$this->assertFalse( is_user_member_of_blog( $user_id ) );
			$this->assertFalse( is_user_member_of_blog( $user_id, get_current_blog_id() ) );
		} else {
			$this->assertTrue( is_user_member_of_blog( $user_id ) );
			$this->assertTrue( is_user_member_of_blog( $user_id, get_current_blog_id() ) );
		}

		wp_set_current_user( $old_current );
	}

	public function test_delete_user() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = new WP_User( $user_id );

		$post = array(
			'post_author'  => $user_id,
			'post_status'  => 'publish',
			'post_content' => 'Post content',
			'post_title'   => 'Post Title',
			'post_type'    => 'post',
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $post );
		$this->assertIsNumeric( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$post = get_post( $post_id );
		$this->assertSame( $post_id, $post->ID );

		$post = array(
			'post_author'  => $user_id,
			'post_status'  => 'publish',
			'post_content' => 'Post content',
			'post_title'   => 'Post Title',
			'post_type'    => 'nav_menu_item',
		);

		// Insert a post and make sure the ID is OK.
		$nav_id = wp_insert_post( $post );
		$this->assertIsNumeric( $nav_id );
		$this->assertGreaterThan( 0, $nav_id );

		$post = get_post( $nav_id );
		$this->assertSame( $nav_id, $post->ID );

		wp_delete_user( $user_id );
		$user = new WP_User( $user_id );
		if ( is_multisite() ) {
			$this->assertTrue( $user->exists() );
		} else {
			$this->assertTrue( $user->exists() );
			$this->assertSame( array( 'deleted' ), $user->roles );
		}

		$this->assertNotNull( get_post( $post_id ) );
		$this->assertSame( 'publish', get_post( $post_id )->post_status );
		$this->assertSame( $user_id, (int) get_post( $post_id )->post_author );
		// Content of every post type remains associated with the anonymized user.
		$this->assertNotNull( get_post( $nav_id ) );
		$this->assertSame( 'publish', get_post( $nav_id )->post_status );
		$this->assertSame( $user_id, (int) get_post( $nav_id )->post_author );
		wp_delete_post( $nav_id, true );
		$this->assertNull( get_post( $nav_id ) );
		wp_delete_post( $post_id, true );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_delete_user_anonymizes_account_and_removes_non_core_meta() {
		$user_id = self::factory()->user->create(
			array(
				'user_login'   => 'person-to-delete',
				'user_email'   => 'person@example.org',
				'user_url'     => 'https://example.org/person',
				'display_name' => 'Identifying Name',
				'role'         => 'author',
			)
		);
		update_user_meta( $user_id, 'first_name', 'Identifying' );
		update_user_meta( $user_id, 'plugin_personal_data', 'private' );

		$this->assertTrue( wp_delete_user( $user_id ) );

		$user = get_userdata( $user_id );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( 'person-to-delete', $user->user_login );
		$this->assertMatchesRegularExpression( '/^deleted-[0-9]{8}@example\.invalid$/', $user->user_email );
		$this->assertSame( 'deleted ' . substr( $user->user_email, 8, 8 ), $user->display_name );
		$this->assertSame( '', $user->user_url );
		$this->assertSame( '', $user->user_activation_key );
		$this->assertSame( array( 'deleted' ), $user->roles );
		$this->assertSame( '', get_user_meta( $user_id, 'nickname', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'first_name', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'plugin_personal_data', true ) );
		$meta_keys = array_keys( get_user_meta( $user_id ) );
		sort( $meta_keys );
		$expected_meta_keys = array( $GLOBALS['wpdb']->prefix . 'capabilities', $GLOBALS['wpdb']->prefix . 'user_level' );
		sort( $expected_meta_keys );
		$this->assertSame( $expected_meta_keys, $meta_keys );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_deleted_user_cannot_authenticate_with_their_old_credentials() {
		$password = 'known-password';
		$email    = 'authentication@example.org';
		$user_id  = self::factory()->user->create(
			array(
				'user_email' => $email,
				'user_pass'  => $password,
			)
		);

		$this->assertSame( $user_id, wp_authenticate( $email, $password )->ID );

		wp_delete_user( $user_id );
		$deleted_user = get_userdata( $user_id );

		$this->assertWPError( wp_authenticate( $email, $password ) );
		$this->assertWPError( wp_authenticate( $deleted_user->user_email, $password ) );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_delete_user_invalidates_sessions_and_password_reset_keys() {
		$user_id   = self::factory()->user->create();
		$user      = get_userdata( $user_id );
		$sessions  = WP_Session_Tokens::get_instance( $user_id );
		$token     = $sessions->create( time() + HOUR_IN_SECONDS );
		$reset_key = get_password_reset_key( $user );

		$this->assertTrue( $sessions->verify( $token ) );
		$this->assertNotWPError( check_password_reset_key( $reset_key, $user->user_email ) );

		wp_delete_user( $user_id );

		$this->assertFalse( $sessions->verify( $token ) );
		$this->assertWPError( check_password_reset_key( $reset_key, $user->user_email ) );
	}

	/**
	 * @group ms-required
	 */
	public function test_multisite_delete_removes_only_current_site_membership() {
		$user_id = self::factory()->user->create(
			array(
				'user_email'   => 'network-user@example.org',
				'display_name' => 'Network User',
				'role'         => 'author',
			)
		);
		$other_site_id = self::factory()->blog->create();
		add_user_to_blog( $other_site_id, $user_id, 'subscriber' );
		update_user_meta( $user_id, 'network_profile_data', 'preserved' );

		$this->assertTrue( wp_delete_user( $user_id ) );

		$user = get_userdata( $user_id );
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( 'network-user@example.org', $user->user_email );
		$this->assertSame( 'Network User', $user->display_name );
		$this->assertSame( 'preserved', get_user_meta( $user_id, 'network_profile_data', true ) );
		$this->assertFalse( is_user_member_of_blog( $user_id, get_current_blog_id() ) );
		$this->assertTrue( is_user_member_of_blog( $user_id, $other_site_id ) );
		$this->assertNotContains( 'deleted', $user->roles );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_anonymization_occurs_between_delete_actions() {
		$user_id    = self::factory()->user->create(
			array(
				'user_login' => 'lifecycle-user',
				'user_email' => 'lifecycle@example.org',
			)
		);
		$action_data = array();

		$before = static function ( $deleted_user_id ) use ( &$action_data ) {
			$action_data['before'] = get_userdata( $deleted_user_id )->user_email;
		};
		$after  = static function ( $deleted_user_id ) use ( &$action_data ) {
			$action_data['after'] = get_userdata( $deleted_user_id )->user_email;
		};

		add_action( 'delete_user', $before );
		add_action( 'deleted_user', $after );
		wp_delete_user( $user_id );
		remove_action( 'delete_user', $before );
		remove_action( 'deleted_user', $after );

		$this->assertSame( 'lifecycle@example.org', $action_data['before'] );
		$this->assertMatchesRegularExpression( '/^deleted-[0-9]{8}@example\.invalid$/', $action_data['after'] );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_deleting_an_anonymized_user_returns_false_without_running_the_lifecycle_again() {
		$user_id = self::factory()->user->create();
		wp_delete_user( $user_id );

		$anonymized_email = get_userdata( $user_id )->user_email;
		$delete_count     = 0;
		$deleted_count    = 0;
		$before           = static function () use ( &$delete_count ) {
			++$delete_count;
		};
		$after            = static function () use ( &$deleted_count ) {
			++$deleted_count;
		};

		add_action( 'delete_user', $before );
		add_action( 'deleted_user', $after );
		$result = wp_delete_user( $user_id );
		remove_action( 'delete_user', $before );
		remove_action( 'deleted_user', $after );

		$this->assertFalse( $result );
		$this->assertSame( 0, $delete_count );
		$this->assertSame( 0, $deleted_count );
		$this->assertSame( $anonymized_email, get_userdata( $user_id )->user_email );
	}

	public function test_deleted_role_is_not_editable() {
		$this->assertArrayNotHasKey( 'deleted', get_editable_roles() );
	}

	/**
	 * @ticket 20447
	 */
	public function test_wp_delete_user_reassignment_clears_post_caches() {
		$user_id  = self::factory()->user->create();
		$reassign = self::factory()->user->create();
		$post_id  = self::factory()->post->create( array( 'post_author' => $user_id ) );

		get_post( $post_id ); // Ensure this post is in the cache.

		wp_delete_user( $user_id, $reassign );

		$post = get_post( $post_id );
		$this->assertEquals( $reassign, $post->post_author );
	}

	/**
	 * @group ms-excluded
	 */
	public function test_numeric_string_user_id() {
		$u = self::factory()->user->create();

		$u_string = (string) $u;
		$this->assertTrue( wp_delete_user( $u_string ) );
		$this->assertInstanceOf( WP_User::class, get_user_by( 'id', $u ) );
	}

	/**
	 * @ticket 33800
	 */
	public function test_should_return_false_for_non_numeric_string_user_id() {
		$this->assertFalse( wp_delete_user( 'abcde' ) );
	}

	/**
	 * @ticket 33800
	 * @group ms-excluded
	 */
	public function test_should_return_false_for_object_user_id() {
		$u_obj = self::factory()->user->create_and_get();
		$this->assertFalse( wp_delete_user( $u_obj ) );
		$this->assertSame( $u_obj->ID, username_exists( $u_obj->user_login ) );
	}
}
