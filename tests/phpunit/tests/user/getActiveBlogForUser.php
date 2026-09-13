<?php

/**
 * Tests specific to users in multisite.
 *
 * @group user
 * @group ms-required
 * @group ms-user
 * @group multisite
 */
class Tests_User_GetActiveBlogForUser extends WP_UnitTestCase {

	public static $user_id = false;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id = $factory->user->create();
	}

	public static function wpTearDownAfterClass() {
		self::delete_user( self::$user_id );

		global $wp_rewrite;
		$wp_rewrite->init();
	}

	/**
	 * @ticket 38355
	 */
	public function test_get_active_blog_for_user_with_no_sites() {
		$current_site_id = get_current_blog_id();

		remove_user_from_blog( self::$user_id, $current_site_id );

		$result = get_active_blog_for_user( self::$user_id );

		$this->assertNull( $result );
	}

	/**
	 * @ticket 38355
	 */
	public function test_get_active_blog_for_user_with_site() {
		$sites           = get_blogs_of_user( self::$user_id );
		$site_ids        = array_keys( $sites );
		$active_site_id  = $site_ids[0];

		$result = get_active_blog_for_user( self::$user_id );

		wp_delete_site( $active_site_id );

		$this->assertSame( $active_site_id, $result->id );
	}
}
