<?php

require_once ABSPATH . 'wp-admin/includes/misc.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Test functions in wp-includes/user.php
 *
 * @group user
 */
class Tests_User extends WP_UnitTestCase {
	protected static $admin_id;
	protected static $editor_id;
	protected static $author_id;
	protected static $contrib_id;
	protected static $sub_id;

	protected static $user_ids = array();

	protected static $_author;
	protected $author;
	protected $user_data;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$contrib_id = $factory->user->create(
			array(
				'user_login'    => 'user1',
				'user_nicename' => 'userone',
				'user_pass'     => 'password',
				'first_name'    => 'John',
				'last_name'     => 'Doe',
				'display_name'  => 'John Doe',
				'user_email'    => 'blackburn@battlefield3.com',
				'user_url'      => 'http://tacos.com',
				'role'          => 'contributor',
				'nickname'      => 'Johnny',
				'description'   => 'I am a WordPress user that cares about privacy.',
			)
		);
		self::$user_ids[] = self::$contrib_id;

		self::$author_id  = $factory->user->create(
			array(
				'user_login' => 'author_login',
				'user_email' => 'author@email.com',
				'role'       => 'author',
			)
		);
		self::$user_ids[] = self::$author_id;

		self::$admin_id   = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$user_ids[] = self::$admin_id;
		self::$editor_id  = $factory->user->create(
			array(
				'user_email' => 'test@test.com',
				'role'       => 'editor',
			)
		);
		self::$user_ids[] = self::$editor_id;
		self::$sub_id     = $factory->user->create( array( 'role' => 'subscriber' ) );
		self::$user_ids[] = self::$sub_id;

		self::$_author = get_user_by( 'ID', self::$author_id );
	}

	public function set_up() {
		parent::set_up();

		$this->author = clone self::$_author;
	}

	public function test_get_users_of_blog() {
		// Add one of each user role.
		$nusers = array(
			self::$contrib_id,
			self::$author_id,
			self::$admin_id,
			self::$editor_id,
			self::$sub_id,
		);

		$user_list = get_users();

		// Find the role of each user as returned by get_users_of_blog().
		$found = array();
		foreach ( $user_list as $user ) {
			// Only include the users we just created - there might be some others that existed previously.
			if ( in_array( $user->ID, $nusers, true ) ) {
				$found[] = $user->ID;
			}
		}

		// Make sure every user we created was returned.
		$this->assertSameSets( $nusers, $found );
	}

	// Simple get/set tests for user_option functions.
	public function test_user_option() {
		$key = rand_str();
		$val = rand_str();

		// Get an option that doesn't exist.
		$this->assertFalse( get_user_option( $key, self::$author_id ) );

		// Set and get.
		update_user_option( self::$author_id, $key, $val );
		$this->assertSame( $val, get_user_option( $key, self::$author_id ) );

		// Change and get again.
		$val2 = 'baz';
		update_user_option( self::$author_id, $key, $val2 );
		$this->assertSame( $val2, get_user_option( $key, self::$author_id ) );
	}

	/**
	 * Simple tests for usermeta functions.
	 */
	public function test_usermeta() {
		$key = 'key';
		$val = 'value1';

		// Get a meta key that doesn't exist.
		$this->assertSame( '', get_user_meta( self::$author_id, $key, true ) );

		// Set and get.
		update_user_meta( self::$author_id, $key, $val );
		$this->assertSame( $val, get_user_meta( self::$author_id, $key, true ) );

		// Change and get again.
		$val2 = 'value2';
		update_user_meta( self::$author_id, $key, $val2 );
		$this->assertSame( $val2, get_user_meta( self::$author_id, $key, true ) );

		// Delete and get.
		delete_user_meta( self::$author_id, $key );
		$this->assertSame( '', get_user_meta( self::$author_id, $key, true ) );

		// Delete by key AND value.
		update_user_meta( self::$author_id, $key, $val );
		// Incorrect key: key still exists.
		delete_user_meta( self::$author_id, $key, 'foo' );
		$this->assertSame( $val, get_user_meta( self::$author_id, $key, true ) );
		// Correct key: deleted.
		delete_user_meta( self::$author_id, $key, $val );
		$this->assertSame( '', get_user_meta( self::$author_id, $key, true ) );

	}

	/**
	 * Test usermeta functions in array mode.
	 */
	public function test_usermeta_array() {
		// Some values to set.
		$vals = array(
			'key0' => 'val0',
			'key1' => 'val1',
			'key2' => 'val2',
		);

		// There is already some stuff in the array.
		$this->assertIsArray( get_user_meta( self::$author_id ) );

		foreach ( $vals as $k => $v ) {
			update_user_meta( self::$author_id, $k, $v );
		}
		// Get the complete usermeta array.
		$out = get_user_meta( self::$author_id );

		// For reasons unclear, the resulting array is indexed numerically; meta keys are not included anywhere.
		// So we'll just check to make sure our values are included somewhere.
		foreach ( $vals as $k => $v ) {
			$this->assertArrayHasKey( $k, $out );
			$this->assertSame( $v, $out[ $k ][0] );
		}
		// Delete one key and check again.
		$keys          = array_keys( $vals );
		$key_to_delete = array_pop( $keys );
		delete_user_meta( self::$author_id, $key_to_delete );
		$out = get_user_meta( self::$author_id );
		// Make sure that key is excluded from the results.
		foreach ( $vals as $k => $v ) {
			if ( $k === $key_to_delete ) {
				$this->assertArrayNotHasKey( $k, $out );
			} else {
				$this->assertArrayHasKey( $k, $out );
				$this->assertSame( $v, $out[ $k ][0] );
			}
		}
	}

	/**
	 * Test property magic functions for property get/set/isset.
	 */
	public function test_user_properties() {
		$user = new WP_User( self::$author_id );

		foreach ( $user->data as $key => $data ) {
			$this->assertEquals( $data, $user->$key );
		}

		$this->assertTrue( isset( $user->$key ) );
		$this->assertFalse( isset( $user->fooooooooo ) );

		$user->$key = 'foo';
		$this->assertSame( 'foo', $user->$key );
		$this->assertSame( 'foo', $user->data->$key );  // This will fail with WP < 3.3.

		foreach ( get_object_vars( $user ) as $key => $value ) {
			$this->assertSame( $value, $user->$key );
		}
	}

	/**
	 * @ticket 53235
	 */
	public function test_numeric_properties_should_be_cast_to_ints() {
		$user     = new WP_User( self::$author_id );
		$contexts = array( 'raw', 'edit', 'db', 'display', 'attribute', 'js' );

		foreach ( $contexts as $context ) {
			$user->filter = $context;
			$user->init( $user->data );

			$this->assertIsInt( $user->ID );
		}
	}

	/**
	 * Test the magic __unset() method.
	 *
	 * @ticket 20043
	 */
	public function test_user_unset() {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$user = new WP_User( self::$author_id );

		// Test custom fields.
		$user->customField = 123;
		$this->assertSame( $user->customField, 123 );
		unset( $user->customField );
		$this->assertFalse( isset( $user->customField ) );
		return $user;
		// phpcs:enable
	}

	/**
	 * Test meta property magic functions for property get/set/isset.
	 */
	public function test_user_meta_properties() {
		$user = new WP_User( self::$author_id );

		update_user_option( self::$author_id, 'foo', 'foo', true );

		$this->assertTrue( isset( $user->foo ) );

		$this->assertSame( 'foo', $user->foo );
	}

	/**
	 * ticket 19265
	 */
	public function test_user_level_property_back_compat() {
		$roles = array(
			self::$admin_id   => 10,
			self::$editor_id  => 7,
			self::$author_id  => 2,
			self::$contrib_id => 1,
			self::$sub_id     => 0,
		);

		foreach ( $roles as $user_id => $level ) {
			$user = new WP_User( $user_id );

			$this->assertTrue( isset( $user->user_level ) );
			$this->assertEquals( $level, $user->user_level );
		}
	}

	public function test_construction() {
		$user = new WP_User( self::$author_id );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( self::$author_id, $user->ID );

		$user2 = new WP_User( 0, $user->user_login );
		$this->assertInstanceOf( 'WP_User', $user2 );
		$this->assertSame( self::$author_id, $user2->ID );
		$this->assertSame( $user->user_login, $user2->user_login );

		$user3 = new WP_User();
		$this->assertInstanceOf( 'WP_User', $user3 );
		$this->assertSame( 0, $user3->ID );
		$this->assertFalse( isset( $user3->user_login ) );

		$user3->init( $user->data );
		$this->assertSame( self::$author_id, $user3->ID );

		$user4 = new WP_User( $user->user_login );
		$this->assertInstanceOf( 'WP_User', $user4 );
		$this->assertSame( self::$author_id, $user4->ID );
		$this->assertSame( $user->user_login, $user4->user_login );

		$user5 = new WP_User( null, $user->user_login );
		$this->assertInstanceOf( 'WP_User', $user5 );
		$this->assertSame( self::$author_id, $user5->ID );
		$this->assertSame( $user->user_login, $user5->user_login );

		$user6 = new WP_User( $user );
		$this->assertInstanceOf( 'WP_User', $user6 );
		$this->assertSame( self::$author_id, $user6->ID );
		$this->assertSame( $user->user_login, $user6->user_login );

		$user7 = new WP_User( $user->data );
		$this->assertInstanceOf( 'WP_User', $user7 );
		$this->assertSame( self::$author_id, $user7->ID );
		$this->assertSame( $user->user_login, $user7->user_login );
	}

	public function test_get() {
		$user = new WP_User( self::$author_id );
		$this->assertSame( 'author_login', $user->get( 'user_login' ) );
		$this->assertSame( 'author@email.com', $user->get( 'user_email' ) );
		$this->assertSame( '', $user->get( 'field_that_does_not_exist' ) );

		update_user_meta( self::$author_id, 'dashed-key', 'abcdefg' );
		$this->assertSame( 'abcdefg', $user->get( 'dashed-key' ) );
	}

	public function test_has_prop() {
		$user = new WP_User( self::$author_id );
		$this->assertTrue( $user->has_prop( 'user_email' ) );
		$this->assertFalse( $user->has_prop( 'field_that_does_not_exist' ) );

		update_user_meta( self::$author_id, 'dashed-key', 'abcdefg' );
		$this->assertTrue( $user->has_prop( 'dashed-key' ) );
	}

	public function test_update_user() {
		$user = new WP_User( self::$author_id );

		update_user_meta( self::$author_id, 'description', 'about me' );
		$this->assertSame( 'about me', $user->get( 'description' ) );

		$user_data = array(
			'ID'           => self::$author_id,
			'display_name' => 'test user',
		);
		wp_update_user( $user_data );

		$user = new WP_User( self::$author_id );
		$this->assertSame( 'test user', $user->get( 'display_name' ) );

		// Make sure there is no collateral damage to fields not in $user_data.
		$this->assertSame( 'about me', $user->get( 'description' ) );

		// Pass as stdClass.
		$user_data = array(
			'ID'           => self::$author_id,
			'display_name' => 'a test user',
		);
		wp_update_user( (object) $user_data );

		$user = new WP_User( self::$author_id );
		$this->assertSame( 'a test user', $user->get( 'display_name' ) );

		$user->display_name = 'some test user';
		wp_update_user( $user );

		$this->assertSame( 'some test user', $user->get( 'display_name' ) );

		// Test update of fields in _get_additional_user_keys().
		$user_data = array(
			'ID' => self::$author_id, 'show_admin_bar_front' => 1,
			'first_name' => 'first', 'last_name' => 'last',
			'admin_color' => 'classic',
			'description' => 'describe'
		);
		wp_update_user( $user_data );

		$user = new WP_User( self::$author_id );
		foreach ( $user_data as $key => $value ) {
			$this->assertEquals( $value, $user->get( $key ), $key );
		}
	}

	/**
	 * @ticket 19595
	 */
	public function test_global_userdata() {
		global $userdata, $wpdb;

		wp_set_current_user( self::$sub_id );

		$this->assertNotEmpty( $userdata );
		$this->assertInstanceOf( 'WP_User', $userdata );
		$this->assertSame( $userdata->ID, self::$sub_id );
		$prefix  = $wpdb->get_blog_prefix();
		$cap_key = $prefix . 'capabilities';
		$this->assertTrue( isset( $userdata->$cap_key ) );
	}

	/**
	 * @ticket 19769
	 */
	public function test_global_userdata_is_null_when_logged_out() {
		global $userdata;
		wp_set_current_user( 0 );
		$this->assertNull( $userdata );
	}

	public function test_exists() {
		$user = new WP_User( self::$author_id );

		$this->assertTrue( $user->exists() );

		$user = new WP_User( 123456789 );

		$this->assertFalse( $user->exists() );

		$user = new WP_User( 0 );

		$this->assertFalse( $user->exists() );
	}

	public function test_global_authordata() {
		global $authordata, $id;

		$old_post_id = $id;

		$user = new WP_User( self::$author_id );

		$post = array(
			'post_author'  => self::$author_id,
			'post_status'  => 'publish',
			'post_content' => 'content',
			'post_title'   => 'title',
			'post_type'    => 'post',
		);

		// Insert a post and make sure the ID is OK.
		$post_id = wp_insert_post( $post );
		$this->assertIsNumeric( $post_id );

		setup_postdata( get_post( $post_id ) );

		$this->assertNotEmpty( $authordata );
		$this->assertInstanceOf( 'WP_User', $authordata );
		$this->assertSame( $authordata->ID, self::$author_id );

		if ( $old_post_id ) {
			setup_postdata( get_post( $old_post_id ) );
		}
	}

	/**
	 * @ticket 13317
	 */
	public function test_get_userdata() {
		$this->assertFalse( get_userdata( 0 ) );
		$this->assertFalse( get_userdata( '0' ) );
		$this->assertFalse( get_userdata( 'string' ) );
		$this->assertFalse( get_userdata( array( 'array' ) ) );
	}

	/**
	 * @ticket 23480
	 */
	public function test_user_get_data_by_id() {
		$user = WP_User::get_data_by( 'id', self::$author_id );
		$this->assertInstanceOf( 'stdClass', $user );
		$this->assertEquals( self::$author_id, $user->ID );

		// @ticket 23480
		$user1 = WP_User::get_data_by( 'id', -1 );
		$this->assertFalse( $user1 );

		$user2 = WP_User::get_data_by( 'id', 0 );
		$this->assertFalse( $user2 );

		$user3 = WP_User::get_data_by( 'id', null );
		$this->assertFalse( $user3 );

		$user4 = WP_User::get_data_by( 'id', '' );
		$this->assertFalse( $user4 );

		$user5 = WP_User::get_data_by( 'id', false );
		$this->assertFalse( $user5 );

		$user6 = WP_User::get_data_by( 'id', $user->user_nicename );
		$this->assertFalse( $user6 );

		$user7 = WP_User::get_data_by( 'id', 99999 );
		$this->assertFalse( $user7 );
	}

	/**
	 * @ticket 33869
	 */
	public function test_user_get_data_by_ID_should_alias_to_id() {
		$user = WP_User::get_data_by( 'ID', self::$author_id );
		$this->assertEquals( self::$author_id, $user->ID );
	}

	/**
	 * @ticket 21431
	 */
	public function test_count_many_users_posts() {
		$user_id_b = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id_a = self::factory()->post->create( array( 'post_author' => self::$author_id ) );
		$post_id_b = self::factory()->post->create( array( 'post_author' => $user_id_b ) );
		$post_id_c = self::factory()->post->create(
			array(
				'post_author' => $user_id_b,
				'post_status' => 'private',
			)
		);

		wp_set_current_user( self::$author_id );
		$counts = count_many_users_posts( array( self::$author_id, $user_id_b ), 'post', false );
		$this->assertEquals( 1, $counts[ self::$author_id ] );
		$this->assertEquals( 1, $counts[ $user_id_b ] );

		$counts = count_many_users_posts( array( self::$author_id, $user_id_b ), 'post', true );
		$this->assertEquals( 1, $counts[ self::$author_id ] );
		$this->assertEquals( 1, $counts[ $user_id_b ] );

		wp_set_current_user( $user_id_b );
		$counts = count_many_users_posts( array( self::$author_id, $user_id_b ), 'post', false );
		$this->assertEquals( 1, $counts[ self::$author_id ] );
		$this->assertEquals( 2, $counts[ $user_id_b ] );

		$counts = count_many_users_posts( array( self::$author_id, $user_id_b ), 'post', true );
		$this->assertEquals( 1, $counts[ self::$author_id ] );
		$this->assertEquals( 1, $counts[ $user_id_b ] );
	}

	/**
	 * @ticket 22858
	 */
	public function test_wp_update_user_on_nonexistent_users() {
		$user_id = 1;
		// Find me a non-existent user ID.
		while ( get_userdata( $user_id ) ) {
			++$user_id;
		}

		// If this test fails, it will error out for calling the to_array() method on a non-object.
		$this->assertInstanceOf( 'WP_Error', wp_update_user( array( 'ID' => $user_id ) ) );
	}

	/**
	 * @ticket 28435
	 */
	public function test_wp_update_user_should_not_change_password_when_passed_WP_User_instance() {
		$testuserid = 1;
		$user       = get_userdata( $testuserid );
		$pwd_before = $user->user_pass;
		wp_update_user( $user );

		// Reload the data.
		$pwd_after = get_userdata( $testuserid )->user_pass;
		$this->assertSame( $pwd_before, $pwd_after );
	}

	/**
	 * @ticket 28315
	 */
	public function test_user_meta_error() {
		$id1 = wp_insert_user(
			array(
				'user_login' => 'taco_burrito',
				'user_pass'  => 'password',
				'user_email' => 'taco@burrito.com',
			)
		);
		$this->assertSame( $id1, email_exists( 'taco@burrito.com' ) );

		$id2 = wp_insert_user(
			array(
				'user_login' => 'taco_burrito2',
				'user_pass'  => 'password',
				'user_email' => 'taco@burrito.com',
			)
		);

		$this->assertWPError( $id2 );

		update_user_meta( $id2, 'key', 'value' );

		$metas = array_keys( get_user_meta( 1 ) );
		$this->assertNotContains( 'key', $metas );
	}

	/**
	 * @ticket 30647
	 */
	public function test_user_update_email_error() {
		$id1 = wp_insert_user(
			array(
				'user_login' => 'blackburn',
				'user_pass'  => 'password',
				'user_email' => 'blackburn@battlefield4.com',
			)
		);
		$this->assertSame( $id1, email_exists( 'blackburn@battlefield4.com' ) );

		$id2 = wp_insert_user(
			array(
				'user_login' => 'miller',
				'user_pass'  => 'password',
				'user_email' => 'miller@battlefield4.com',
			)
		);
		$this->assertSame( $id2, email_exists( 'miller@battlefield4.com' ) );

		if ( ! is_wp_error( $id2 ) ) {
			wp_update_user(
				array(
					'ID'         => $id2,
					'user_email' => 'david@battlefield4.com',
				)
			);
			$this->assertSame( $id2, email_exists( 'david@battlefield4.com' ) );

			$return = wp_update_user(
				array(
					'ID'         => $id2,
					'user_email' => 'blackburn@battlefield4.com',
				)
			);

			$this->assertWPError( $return );
		}
	}

	/**
	 * @ticket 27317
	 * @dataProvider data_illegal_user_logins
	 */
	public function test_illegal_user_logins_single( $user_login ) {
		$user_data = array(
			'user_login' => $user_login,
			'user_email' => 'testuser@example.com',
			'user_pass'  => wp_generate_password(),
		);

		add_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );

		$response = wp_insert_user( $user_data );
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'invalid_username', $response->get_error_code() );

		remove_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );

		$user_id = wp_insert_user( $user_data );
		$user    = get_user_by( 'id', $user_id );
		$this->assertInstanceOf( 'WP_User', $user );
	}

	/**
	 * @ticket 27317
	 * @dataProvider data_illegal_user_logins
	 */
	public function test_illegal_user_logins_single_wp_create_user( $user_login ) {
		$user_email = 'testuser-' . $user_login . '@example.com';

		add_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );

		$response = register_new_user( $user_login, $user_email );
		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'invalid_username', $response->get_error_code() );

		remove_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );

		$response = register_new_user( $user_login, $user_email );
		$user     = get_user_by( 'id', $response );
		$this->assertInstanceOf( 'WP_User', $user );
	}

	/**
	 * @ticket 27317
	 * @group ms-required
	 */
	public function test_illegal_user_logins_multisite() {
		$user_data = array(
			'user_login' => 'testuser',
			'user_email' => 'testuser@example.com',
		);

		add_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );

		$response = wpmu_validate_user_signup( $user_data['user_login'], $user_data['user_email'] );
		$this->assertInstanceOf( 'WP_Error', $response['errors'] );
		$this->assertSame( 'user_name', $response['errors']->get_error_code() );

		remove_filter( 'illegal_user_logins', array( $this, 'illegal_user_logins' ) );

		$response = wpmu_validate_user_signup( $user_data['user_login'], $user_data['user_email'] );
		$this->assertInstanceOf( 'WP_Error', $response['errors'] );
		$this->assertCount( 0, $response['errors']->get_error_codes() );
	}

	public function data_illegal_user_logins() {
		$data = array(
			array( 'testuser' ),
		);

		// Multisite doesn't allow mixed case logins ever.
		if ( ! is_multisite() ) {
			$data[] = array( 'TestUser' );
		}
		return $data;
	}

	public function illegal_user_logins() {
		return array( 'testuser' );
	}

	/**
	 * @ticket 24618
	 */
	public function test_validate_username_string() {
		$this->assertTrue( validate_username( 'johndoe' ) );
		$this->assertTrue( validate_username( 'test@test.com' ) );
	}

	/**
	 * @ticket 24618
	 */
	public function test_validate_username_contains_uppercase_letters() {
		if ( is_multisite() ) {
			$this->assertFalse( validate_username( 'JohnDoe' ) );
		} else {
			$this->assertTrue( validate_username( 'JohnDoe' ) );
		}
	}

	/**
	 * @ticket 24618
	 */
	public function test_validate_username_empty() {
		$this->assertFalse( validate_username( '' ) );
	}

	/**
	 * @ticket 24618
	 */
	public function test_validate_username_invalid() {
		$this->assertFalse( validate_username( '@#&99sd' ) );
	}

	/**
	 * @ticket 29880
	 */
	public function test_wp_insert_user_should_not_wipe_existing_password() {
		$user_details = array(
			'user_login' => 'jonsnow',
			'user_pass'  => 'password',
			'user_email' => 'jonsnow@example.com',
		);

		$user_id = wp_insert_user( $user_details );
		$this->assertSame( $user_id, email_exists( $user_details['user_email'] ) );

		// Check that providing an empty password doesn't remove a user's password.
		$user_details['ID']        = $user_id;
		$user_details['user_pass'] = '';

		$user_id = wp_insert_user( $user_details );
		$user    = WP_User::get_data_by( 'id', $user_id );
		$this->assertNotEmpty( $user->user_pass );
	}

	/**
	 * @ticket 29696
	 */
	public function test_wp_insert_user_should_sanitize_user_nicename_parameter() {
		$user = $this->author;

		$userdata                  = $user->to_array();
		$userdata['user_nicename'] = str_replace( '-', '.', $user->user_nicename );
		wp_insert_user( $userdata );

		$updated_user = new WP_User( $user->ID );

		$this->assertSame( $user->user_nicename, $updated_user->user_nicename );
	}

	/**
	 * @ticket 33793
	 */
	public function test_wp_insert_user_should_accept_user_login_with_60_characters() {
		$user_login = str_repeat( 'a', 60 );
		$u          = wp_insert_user(
			array(
				'user_login'    => $user_login,
				'user_email'    => $user_login . '@example.com',
				'user_pass'     => 'password',
				'user_nicename' => 'something-short',
			)
		);

		$this->assertIsInt( $u );
		$this->assertGreaterThan( 0, $u );

		$user = new WP_User( $u );
		$this->assertSame( $user_login, $user->user_login );
	}

	/**
	 * @ticket 33793
	 */
	public function test_wp_insert_user_should_reject_user_login_over_60_characters() {
		$user_login = str_repeat( 'a', 61 );
		$u          = wp_insert_user(
			array(
				'user_login'    => $user_login,
				'user_email'    => $user_login . '@example.com',
				'user_pass'     => 'password',
				'user_nicename' => 'something-short',
			)
		);

		$this->assertWPError( $u );
		$this->assertSame( 'user_login_too_long', $u->get_error_code() );
	}

	/**
	 * @ticket 33793
	 */
	public function test_wp_insert_user_should_reject_user_nicename_over_50_characters() {
		$user_nicename = str_repeat( 'a', 51 );
		$u             = wp_insert_user(
			array(
				'user_login'    => 'mynicenamehas50chars',
				'user_email'    => $user_nicename . '@example.com',
				'user_pass'     => 'password',
				'user_nicename' => $user_nicename,
			)
		);

		$this->assertWPError( $u );
		$this->assertSame( 'user_nicename_too_long', $u->get_error_code() );
	}

	/**
	 * @ticket 33793
	 */
	public function test_wp_insert_user_should_not_generate_user_nicename_longer_than_50_chars() {
		$user_login = str_repeat( 'a', 55 );
		$u          = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_email' => $user_login . '@example.com',
				'user_pass'  => 'password',
			)
		);

		$this->assertNotEmpty( $u );
		$user     = new WP_User( $u );
		$expected = str_repeat( 'a', 50 );
		$this->assertSame( $expected, $user->user_nicename );
	}

	/**
	 * @ticket 33793
	 */
	public function test_wp_insert_user_should_not_truncate_to_a_duplicate_user_nicename() {
		$u1 = self::factory()->user->create(
			array(
				'user_nicename' => str_repeat( 'a', 50 ),
			)
		);

		$user1 = new WP_User( $u1 );

		$expected = str_repeat( 'a', 50 );
		$this->assertSame( $expected, $user1->user_nicename );

		$user_login = str_repeat( 'a', 55 );
		$u          = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_email' => $user_login . '@example.com',
				'user_pass'  => 'password',
			)
		);

		$this->assertNotEmpty( $u );
		$user2    = new WP_User( $u );
		$expected = str_repeat( 'a', 48 ) . '-2';
		$this->assertSame( $expected, $user2->user_nicename );
	}

	/**
	 * @ticket 33793
	 */
	public function test_wp_insert_user_should_not_truncate_to_a_duplicate_user_nicename_when_suffix_has_more_than_one_character() {
		$user_ids = self::factory()->user->create_many(
			4,
			array(
				'user_nicename' => str_repeat( 'a', 50 ),
			)
		);

		foreach ( $user_ids as $i => $user_id ) {
			$user = new WP_User( $user_id );
			if ( 0 === $i ) {
				$expected = str_repeat( 'a', 50 );
			} else {
				$expected = str_repeat( 'a', 48 ) . '-' . ( $i + 1 );
			}
			$this->assertSame( $expected, $user->user_nicename );
		}

		$user_login = str_repeat( 'a', 55 );
		$u          = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_email' => $user_login . '@example.com',
				'user_pass'  => 'password',
			)
		);

		$this->assertNotEmpty( $u );
		$user     = new WP_User( $u );
		$expected = str_repeat( 'a', 48 ) . '-5';
		$this->assertSame( $expected, $user->user_nicename );
	}

	/**
	 * @ticket 28004
	 */
	public function test_wp_insert_user_with_invalid_user_id() {
		global $wpdb;
		$max_user = $wpdb->get_var( "SELECT MAX(ID) FROM $wpdb->users" );

		$u = wp_insert_user(
			array(
				'ID'         => $max_user + 1,
				'user_login' => 'whatever',
				'user_email' => 'whatever@example.com',
				'user_pass'  => 'password',
			)
		);

		$this->assertWPError( $u );
		$this->assertSame( 'invalid_user_id', $u->get_error_code() );
	}

	/**
	 * @ticket 47902
	 */
	public function test_wp_insert_user_with_empty_data() {
		add_filter( 'wp_pre_insert_user_data', '__return_empty_array' );

		$u = self::factory()->user->create();

		remove_filter( 'wp_pre_insert_user_data', '__return_empty_array' );

		$this->assertWPError( $u );
		$this->assertSame( 'empty_data', $u->get_error_code() );
	}

	/**
	 * @ticket 35750
	 */
	public function test_wp_update_user_should_delete_userslugs_cache() {
		$u    = self::factory()->user->create();
		$user = get_userdata( $u );

		wp_update_user(
			array(
				'ID'            => $u,
				'user_nicename' => 'newusernicename',
			)
		);
		$updated_user = get_userdata( $u );

		$this->assertFalse( wp_cache_get( $user->user_nicename, 'userslugs' ) );
		$this->assertEquals( $u, wp_cache_get( $updated_user->user_nicename, 'userslugs' ) );
	}

	public function test_changing_email_invalidates_password_reset_key() {
		global $wpdb;

		$user = $this->author;
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => 'key' ), array( 'ID' => $user->ID ) );
		clean_user_cache( $user );

		$user = get_userdata( $user->ID );
		$this->assertSame( 'key', $user->user_activation_key );

		// Check that changing something other than the email doesn't remove the key.
		$userdata = array(
			'ID'            => $user->ID,
			'user_nicename' => 'wat',
		);
		wp_update_user( $userdata );

		$user = get_userdata( $user->ID );
		$this->assertSame( 'key', $user->user_activation_key );

		// Now check that changing the email does remove it.
		$userdata = array(
			'ID'            => $user->ID,
			'user_nicename' => 'cat',
			'user_email'    => 'foo@bar.dev',
		);
		wp_update_user( $userdata );

		$user = get_userdata( $user->ID );
		$this->assertEmpty( $user->user_activation_key );
	}

	public function test_changing_password_invalidates_password_reset_key() {
		global $wpdb;

		$user = $this->author;
		$wpdb->update( $wpdb->users, array( 'user_activation_key' => 'key' ), array( 'ID' => $user->ID ) );
		clean_user_cache( $user );

		$user = get_userdata( $user->ID );
		$this->assertSame( 'key', $user->user_activation_key );

		$userdata = array(
			'ID'        => $user->ID,
			'user_pass' => 'password',
		);
		wp_update_user( $userdata );

		$user = get_userdata( $user->ID );
		$this->assertEmpty( $user->user_activation_key );
	}

	public function test_search_users_login() {
		$users = get_users(
			array(
				'search' => 'user1',
				'fields' => 'ID',
			)
		);

		$this->assertContains( (string) self::$contrib_id, $users );
	}

	public function test_search_users_email() {
		$users = get_users(
			array(
				'search' => '*battle*',
				'fields' => 'ID',
			)
		);

		$this->assertContains( (string) self::$contrib_id, $users );
	}

	public function test_search_users_nicename() {
		$users = get_users(
			array(
				'search' => '*one*',
				'fields' => 'ID',
			)
		);

		$this->assertContains( (string) self::$contrib_id, $users );
	}

	public function test_search_users_display_name() {
		$users = get_users(
			array(
				'search' => '*Doe*',
				'fields' => 'ID',
			)
		);

		$this->assertContains( (string) self::$contrib_id, $users );
	}

	/**
	 * @ticket 32158
	 */
	public function test_email_case() {
		// Alter the case of the email address (which stays the same).
		$userdata = array(
			'ID'         => self::$editor_id,
			'user_email' => 'test@TEST.com',
		);
		$update   = wp_update_user( $userdata );

		$this->assertSame( self::$editor_id, $update );
	}

	/**
	 * Checks that calling edit_user() with no password returns an error when adding, and doesn't when updating.
	 *
	 * @ticket 35715
	 * @ticket 42766
	 */
	public function test_edit_user_blank_password() {
		$_POST                 = array();
		$_GET                  = array();
		$_REQUEST              = array();
		$_POST['role']         = 'subscriber';
		$_POST['email']        = 'user1@example.com';
		$_POST['user_login']   = 'user_login1';
		$_POST['display_name'] = 'display_name1';

		// Check new user with missing password.
		$response = edit_user();

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'pass', $response->get_error_code() );

		// Check new user with password set.
		$_POST['pass1'] = 'password';
		$_POST['pass2'] = 'password';

		$user_id = edit_user();
		$user    = get_user_by( 'ID', $user_id );

		$this->assertIsInt( $user_id );
		$this->assertInstanceOf( 'WP_User', $user );

		// Check updating user with empty password.
		$_POST['pass1'] = $_POST['pass2'] = '';

		$user_id = edit_user( $user_id );

		$this->assertIsInt( $user_id );

		// Check not to change an old password if a new password contains only spaces. Ticket #42766.
		$user           = get_user_by( 'ID', $user_id );
		$old_pass       = $user->user_pass;
		$_POST['pass2'] = '  ';
		$_POST['pass1'] = '  ';

		$user_id = edit_user( $user_id );
		$user    = get_user_by( 'ID', $user_id );

		$this->assertIsInt( $user_id );
		$this->assertSame( $old_pass, $user->user_pass );

		// Check updating user with missing second password.
		$_POST['pass1'] = 'blank_pass2';
		$_POST['pass2'] = '';

		$response = edit_user( $user_id );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertSame( 'pass', $response->get_error_code() );

		// Check updating user with empty password via `check_passwords` action.
		add_action( 'check_passwords', array( $this, 'action_check_passwords_blank_password' ), 10, 2 );
		$user_id = edit_user( $user_id );
		remove_action( 'check_passwords', array( $this, 'action_check_passwords_blank_password' ) );

		$this->assertIsInt( $user_id );
	}

	/**
	 * Check passwords action for test_edit_user_blank_password().
	 */
	public function action_check_passwords_blank_password( $user_login, &$pass1 ) {
		$pass1 = '';
	}

	/**
	 * @ticket 42564
	 */
	public function test_edit_user_role_update() {
		$_POST    = array();
		$_GET     = array();
		$_REQUEST = array();

		$administrator = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);

		wp_set_current_user( $administrator );

		// Don't let anyone with 'promote_users' (administrator) edit their own role to something without it (subscriber).
		$_POST['role']     = 'subscriber';
		$_POST['email']    = 'subscriber@subscriber.test';
		$_POST['nickname'] = 'subscriber';
		$this->assertSame( $administrator, edit_user( $administrator ) );

		// Should still have the old role.
		$this->assertSame( array( 'administrator' ), get_userdata( $administrator )->roles );

		// Promote an editor to an administrator.
		$editor = self::factory()->user->create(
			array(
				'role' => 'editor',
			)
		);

		$_POST['role']     = 'administrator';
		$_POST['email']    = 'administrator@administrator.test';
		$_POST['nickname'] = 'administrator';
		$this->assertSame( $editor, edit_user( $editor ) );

		// Should have the new role.
		$this->assertSame( array( 'administrator' ), get_userdata( $editor )->roles );
	}

	/**
	 * Testing the `wp_user_personal_data_exporter()` function when no user exists.
	 *
	 * @ticket 43547
	 */
	public function test_wp_user_personal_data_exporter_no_user() {
		$actual = wp_user_personal_data_exporter( 'not-a-user-email@test.com' );

		$expected = array(
			'data' => array(),
			'done' => true,
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Testing the `wp_user_personal_data_exporter()` function when the requested
	 * user exists.
	 *
	 * @ticket 43547
	 */
	public function test_wp_user_personal_data_exporter() {
		$test_user = new WP_User( self::$contrib_id );

		$actual = wp_user_personal_data_exporter( $test_user->user_email );

		$this->assertTrue( $actual['done'] );

		// Number of exported users.
		$this->assertCount( 1, $actual['data'] );

		// Number of exported user properties.
		$this->assertCount( 11, $actual['data'][0]['data'] );
	}

	/**
	 * Test avatar image association.
	 *
	 * @since calmPress 1.0.0
	 */
	 function test_avatar() {
		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );

		// No avatar on user creation.
		$id1 = wp_insert_user(
			[
				'user_login' => rand_str(),
				'user_pass'  => 'password',
				'user_email' => 'taco@burrito.com',
			]
		);
		$user = get_user_by( 'id', $id1 );
		$this->assertNull( $user->avatar()->attachment() );

		// No avatar on user creation.
		$id2 = wp_insert_user(
			[
				'user_login' => rand_str(),
				'user_pass'  => 'password',
				'user_email' => 'taco2@burrito.com',
				'avatar_attachment_id' => $attachment_id,
		   ]
		);
		$user2 = get_user_by( 'id', $id2 );
		$this->assertEquals( $attachment_id, $user2->avatar()->attachment()->ID );

		// Updating user with no avatar specified still have no avatar associated.
		wp_update_user(
			[
				'ID'         => $id1,
				'user_email' => 'david@battlefield4.com',
			]
		);
		$this->assertNull( $user->avatar()->attachment() );

		// Updating user with an avatar specified will associate the attachment.
		wp_update_user(
			[
				'ID'         => $id1,
				'user_email' => 'david2@battlefield4.com',
				'avatar_attachment_id' => $attachment_id,
			]
		);
		$this->assertEquals( $attachment_id, $user->avatar()->attachment()->ID );

		// Updating user with no avatar specified will keep avatar associated if there is one.
		wp_update_user(
			[
				'ID'         => $id1,
				'user_email' => 'david3@battlefield4.com',
			]
		);
		$this->assertEquals( $attachment_id, $user->avatar()->attachment()->ID );

		// Updating user avatar with the value 0 will remove the avatar.
		wp_update_user(
			[
				'ID'         => $id1,
				'avatar_attachment_id' => 0,
			]
		);
		$this->assertNull( $user->avatar()->attachment() );

		// Cleanup.
		wp_delete_post( $attachment_id, true );
	 }

	/**
	 * Test the mocked_role method.
	 *
	 * Verify that the role return by it matches mock role and expiry settings.
	 *
	 * @since calmPress 1.0.0
	 */
	function test_mocked_role() {
		$user_id = wp_insert_user(
			[
				'user_login' => rand_str(),
				'user_pass'  => 'password',
				'user_email' => 'taco@burrito.com',
			]
		);
		$user = get_user_by( 'id', $user_id );

		// No meta either role, or eixpiry should return ''.
		$this->assertSame( '', $user->mocked_role() );

		update_user_meta( $user_id, 'mock_role', 'editor' );
		$this->assertSame( '', $user->mocked_role() );

		delete_user_meta( $user_id, 'mock_role' );
		update_user_meta( $user_id, 'mock_role_expiry', time() + 1000 );
		$this->assertSame( '', $user->mocked_role() );

		// Test editor and author roles returned when set and expiry in the future.
		update_user_meta( $user_id, 'mock_role', 'editor' );
		update_user_meta( $user_id, 'mock_role_expiry', time() + 1000 );
		$this->assertSame( 'editor', $user->mocked_role() );

		update_user_meta( $user_id, 'mock_role', 'author' );
		update_user_meta( $user_id, 'mock_role_expiry', time() + 1000 );
		$this->assertSame( 'author', $user->mocked_role() );

		// Test '' returned when role is '' or not editor nor author and expiry in the future.
		update_user_meta( $user_id, 'mock_role', '' );
		update_user_meta( $user_id, 'mock_role_expiry', time() + 1000 );
		$this->assertSame( '', $user->mocked_role() );

		update_user_meta( $user_id, 'mock_role', 'test' );
		update_user_meta( $user_id, 'mock_role_expiry', time() + 1000 );
		$this->assertSame( '', $user->mocked_role() );

		// Test '' returned when expiry in the past.

		update_user_meta( $user_id, 'mock_role', 'editor' );
		update_user_meta( $user_id, 'mock_role_expiry', time() - 1000 );
		$this->assertSame( '', $user->mocked_role() );
	}

	/**
	 * Testing the `wp_user_personal_data_exporter()` function
	 * with Session Tokens data.
	 *
	 * @ticket 45889
	 */
	public function test_wp_session_tokens_personal_data_exporter() {
		$test_user = new WP_User( self::$contrib_id );

		$session_tokens_data = array(
			'yft87y56457687sfd897867545fg76ds78iyuhgjyui7865' => array(
				'expiration' => 1580461981,
				'ip'         => '0.0.0.0',
				'ua'         => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36',
				'login'      => 1580289181,
			),
		);
		update_user_option( $test_user->ID, 'session_tokens', $session_tokens_data, true );

		$actual = wp_user_personal_data_exporter( $test_user->user_email );

		$this->assertTrue( $actual['done'] );

		// Contains Session Tokens.
		$this->assertSame( 'Session Tokens', $actual['data'][1]['group_label'] );

		// Contains Expiration.
		$this->assertSame( 'Expiration', $actual['data'][1]['data'][0]['name'] );
		$this->assertSame( 'January 31, 2020 09:13 AM', $actual['data'][1]['data'][0]['value'] );

		// Contains IP.
		$this->assertSame( 'IP', $actual['data'][1]['data'][1]['name'] );
		$this->assertSame( '0.0.0.0', $actual['data'][1]['data'][1]['value'] );

		// Contains IP.
		$this->assertSame( 'User Agent', $actual['data'][1]['data'][2]['name'] );
		$this->assertSame( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36', $actual['data'][1]['data'][2]['value'] );

		// Contains IP.
		$this->assertSame( 'Last Login', $actual['data'][1]['data'][3]['name'] );
		$this->assertSame( 'January 29, 2020 09:13 AM', $actual['data'][1]['data'][3]['value'] );
	}

	/**
	 * Test `$user_data['meta_input']` args in `wp_insert_user( $user_data )`.
	 *
	 * @ticket 41950
	 */
	public function test_wp_insert_user_with_meta() {
		$user_data   = array(
			'user_login' => 'test_user',
			'user_pass'  => 'test_password',
			'user_email' => 'user@example.com',
			'meta_input' => array(
				'test_meta_key' => 'test_meta_value',
				'custom_meta'   => 'custom_value',
			),
		);
		$create_user = wp_insert_user( $user_data );

		$this->assertSame( 'test_meta_value', get_user_meta( $create_user, 'test_meta_key', true ) );
		$this->assertSame( 'custom_value', get_user_meta( $create_user, 'custom_meta', true ) );

		// Update the user meta thru wp_insert_user.
		$update_data = array(
			'ID'         => $create_user,
			'user_login' => 'test_user',
			'user_email' => 'test.user@example.com',
			'meta_input' => array(
				'test_meta_key' => 'test_meta_updated',
				'custom_meta'   => 'updated_value',
				'new_meta_k'    => 'new_meta_val',
			),
		);
		$update_user = wp_insert_user( $update_data );

		$this->assertSame( 'test_meta_updated', get_user_meta( $update_user, 'test_meta_key', true ) );
		$this->assertSame( 'updated_value', get_user_meta( $update_user, 'custom_meta', true ) );
		$this->assertSame( 'new_meta_val', get_user_meta( $update_user, 'new_meta_k', true ) );

		// Create new user.
		$new_user_data = array(
			'user_login' => 'new_test',
			'user_pass'  => 'new_password',
			'user_email' => 'new_user@newexample.com',
			'meta_input' => array(
				'test_meta_key' => 'test_meta_value',
				'custom_meta'   => 'new_user_custom_value',
			),
		);

		// Hook filter
		add_filter( 'insert_custom_user_meta', array( $this, 'filter_custom_meta' ) );

		$new_user = wp_insert_user( $new_user_data );

		// This meta is updated by the filter.
		$this->assertSame( 'update_from_filter', get_user_meta( $new_user, 'test_meta_key', true ) );
		$this->assertSame( 'new_user_custom_value', get_user_meta( $new_user, 'custom_meta', true ) );
		// This meta is inserted by the filter.
		$this->assertSame( 'new_from_filter', get_user_meta( $new_user, 'new_meta_from_filter', true ) );
	}

	/**
	 * Hook a filter to alter custom meta when inserting new user.
	 * This hook is used in `test_wp_insert_user_with_meta()`.
	 */
	public function filter_custom_meta( $meta_input ) {
		// Update some meta inputs.
		$meta_input['test_meta_key'] = 'update_from_filter';
		// Add a new meta.
		$meta_input['new_meta_from_filter'] = 'new_from_filter';

		return $meta_input;
	}

	/**
	 * Testing the `wp_privacy_additional_user_profile_data` filter works.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 47509
	 */
	public function test_filter_wp_privacy_additional_user_profile_data() {
		$test_user = new WP_User( self::$contrib_id );

		add_filter( 'wp_privacy_additional_user_profile_data', array( $this, 'export_additional_user_profile_data' ) );

		$actual = wp_user_personal_data_exporter( $test_user->user_email );

		remove_filter( 'wp_privacy_additional_user_profile_data', array( $this, 'export_additional_user_profile_data' ) );

		$this->assertTrue( $actual['done'] );

		// Number of exported users.
		$this->assertCount( 1, $actual['data'] );

		// Number of exported user properties (the 11 core properties,
		// plus 1 additional from the filter).
		$this->assertCount( 12, $actual['data'][0]['data'] );

		// Check that the item added by the filter was retained.
		$this->assertSame(
			1,
			count(
				wp_list_filter(
					$actual['data'][0]['data'],
					array(
						'name'  => 'Test Additional Data Name',
						'value' => 'Test Additional Data Value',
					)
				)
			)
		);

		// _doing_wrong() should be called because the filter callback
		// adds a item with a 'name' that is the same as one generated by core.
		$this->setExpectedIncorrectUsage( 'wp_user_personal_data_exporter' );
		add_filter( 'wp_privacy_additional_user_profile_data', array( $this, 'export_additional_user_profile_data_with_dup_name' ) );

		$actual = wp_user_personal_data_exporter( $test_user->user_email );

		remove_filter( 'wp_privacy_additional_user_profile_data', array( $this, 'export_additional_user_profile_data' ) );

		$this->assertTrue( $actual['done'] );

		// Number of exported users.
		$this->assertCount( 1, $actual['data'] );

		// Number of exported user properties
		// (the 11 core properties, plus 1 additional from the filter).
		$this->assertCount( 12, $actual['data'][0]['data'] );

		// Check that the duplicate 'name' => 'User ID' was stripped.
		$this->assertSame(
			1,
			count(
				wp_list_filter(
					$actual['data'][0]['data'],
					array(
						'name' => 'User ID',
					)
				)
			)
		);

		// Check that the item added by the filter was retained.
		$this->assertSame(
			1,
			count(
				wp_list_filter(
					$actual['data'][0]['data'],
					array(
						'name'  => 'Test Additional Data Name',
						'value' => 'Test Additional Data Value',
					)
				)
			)
		);
	}

	/**
	 * Filter callback to add additional profile data to the User Group on Export Requests.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 47509
	 *
	 * @return array The additional user data.
	 */
	public function export_additional_user_profile_data() {
		$additional_profile_data = array(
			// This item should be retained and included in the export.
			array(
				'name'  => 'Test Additional Data Name',
				'value' => 'Test Additional Data Value',
			),
		);

		return $additional_profile_data;
	}

	/**
	 * Filter callback to add additional profile data to the User Group on Export Requests.
	 *
	 * This callback should generate a `_doing_it_wrong()`.
	 *
	 * @since 5.4.0
	 *
	 * @ticket 47509
	 *
	 * @return array The additional user data.
	 */
	public function export_additional_user_profile_data_with_dup_name() {
		$additional_profile_data = array(
			// This item should be stripped out by wp_user_personal_data_exporter()
			// because it's 'name' duplicates one exported by core.
			array(
				'name'  => 'User ID',
				'value' => 'Some User ID',
			),
			// This item should be retained and included in the export.
			array(
				'name'  => 'Test Additional Data Name',
				'value' => 'Test Additional Data Value',
			),
		);

		return $additional_profile_data;
	}

	/**
	 * Test the guess_name_from_email function.
	 *
	 * @dataProvider data_emails_to_guess
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_guess_name_from_email( string $email, string $expected ) {
		// Silence errors that might be generated due to invalid email address.
		$old_handler = set_error_handler(
			static function ( $errno, $errcode ) {
				return true;
			}
		);
		$this->assertSame( guess_name_from_email( $email ), $expected );
		// Restore previous error handler.
		set_error_handler( $old_handler );
	}

	public function data_emails_to_guess() : array {
		return [
			[ 'admin', 'admin' ],     // non email at all
			[ 'admi@in', 'admi@in' ], // just looks like an email.
			[ 'a@b.com', 'A' ],
			[ 'sim@example.com', 'Sim' ],
			[ 'for.me.you@stam.com', 'For Me You' ],
			[ 'lets.go+test@calm.com', "Lets Go" ], // With tag.
		];
	}
}
