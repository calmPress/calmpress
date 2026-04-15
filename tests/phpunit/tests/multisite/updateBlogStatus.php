<?php

/**
 * @group ms-required
 * @group ms-site
 * @group multisite
 */
class Tests_Multisite_UpdateBlogStatus extends WP_UnitTestCase {

	/**
	 * Updating an invalid field returns the same value that was passed.
	 */
	public function test_update_blog_status_invalid_status() {
		$result = update_blog_status( 1, 'doesnotexist', 'invalid' );
		$this->assertSame( 'invalid', $result );
	}

	public function test_update_blog_status_archive_blog_action() {
		$test_action_counter = new MockAction();

		$blog_id = self::factory()->blog->create();

		add_action( 'archive_blog', array( $test_action_counter, 'action' ) );
		update_blog_status( $blog_id, 'archived', 1 );
		$blog = get_site( $blog_id );

		$this->assertSame( '1', $blog->archived );
		$this->assertSame( 1, $test_action_counter->get_call_count() );

		// The action should not fire if the status of 'archived' stays the same.
		update_blog_status( $blog_id, 'archived', 1 );
		$blog = get_site( $blog_id );

		$this->assertSame( '1', $blog->archived );
		$this->assertSame( 1, $test_action_counter->get_call_count() );
	}

	public function test_update_blog_status_unarchive_blog_action() {
		$test_action_counter = new MockAction();

		$blog_id = self::factory()->blog->create();
		update_blog_details( $blog_id, array( 'archived' => 1 ) );

		add_action( 'unarchive_blog', array( $test_action_counter, 'action' ) );
		update_blog_status( $blog_id, 'archived', 0 );
		$blog = get_site( $blog_id );

		$this->assertSame( '0', $blog->archived );
		$this->assertSame( 1, $test_action_counter->get_call_count() );

		// The action should not fire if the status of 'archived' stays the same.
		update_blog_status( $blog_id, 'archived', 0 );
		$blog = get_site( $blog_id );
		$this->assertSame( '0', $blog->archived );
		$this->assertSame( 1, $test_action_counter->get_call_count() );
	}

	public function test_update_blog_status_make_delete_blog_action() {
		$test_action_counter = new MockAction();

		$blog_id = self::factory()->blog->create();

		add_action( 'make_delete_blog', array( $test_action_counter, 'action' ) );
		update_blog_status( $blog_id, 'deleted', 1 );
		$blog = get_site( $blog_id );

		$this->assertSame( '1', $blog->deleted );
		$this->assertSame( 1, $test_action_counter->get_call_count() );

		// The action should not fire if the status of 'deleted' stays the same.
		update_blog_status( $blog_id, 'deleted', 1 );
		$blog = get_site( $blog_id );

		$this->assertSame( '1', $blog->deleted );
		$this->assertSame( 1, $test_action_counter->get_call_count() );
	}

	public function test_update_blog_status_make_undelete_blog_action() {
		$test_action_counter = new MockAction();

		$blog_id = self::factory()->blog->create();
		update_blog_details( $blog_id, array( 'deleted' => 1 ) );

		add_action( 'make_undelete_blog', array( $test_action_counter, 'action' ) );
		update_blog_status( $blog_id, 'deleted', 0 );
		$blog = get_site( $blog_id );

		$this->assertSame( '0', $blog->deleted );
		$this->assertSame( 1, $test_action_counter->get_call_count() );

		// The action should not fire if the status of 'deleted' stays the same.
		update_blog_status( $blog_id, 'deleted', 0 );
		$blog = get_site( $blog_id );

		$this->assertSame( '0', $blog->deleted );
		$this->assertSame( 1, $test_action_counter->get_call_count() );
	}

	public function test_update_blog_status_update_blog_public_action() {
		$test_action_counter = new MockAction();

		$blog_id = self::factory()->blog->create();

		add_action( 'update_blog_public', array( $test_action_counter, 'action' ) );
		update_blog_status( $blog_id, 'public', 0 );

		$blog = get_site( $blog_id );
		$this->assertSame( '0', $blog->public );
		$this->assertSame( 1, $test_action_counter->get_call_count() );

		// The action should not fire if the status of 'mature' stays the same.
		update_blog_status( $blog_id, 'public', 0 );
		$blog = get_site( $blog_id );

		$this->assertSame( '0', $blog->public );
		$this->assertSame( 1, $test_action_counter->get_call_count() );
	}
}
