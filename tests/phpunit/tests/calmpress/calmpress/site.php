<?php
/**
 * Tests for the CalmPress site abstraction.
 *
 * @package calmPress
 */

use calmpress\site\Site as CalmPress_Site;

/**
 * Tests the site abstraction on a standalone installation.
 */
class Site extends WP_UnitTestCase {

	/**
	 * Tests that the current standalone site is represented by a Site object.
	 */
	public function test_current_returns_standalone_site(): void {
		$site = CalmPress_Site::current();

		$this->assertSame( CalmPress_Site::class, get_class( $site ) );
		$this->assertNull( $site->network() );
	}

	/**
	 * Tests that a standalone site retrieves its administrators.
	 */
	public function test_administrators_returns_standalone_site_administrators(): void {
		$user_id         = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$user_ids        = wp_list_pluck( CalmPress_Site::current()->administrators(), 'ID' );
		$sorted_user_ids = $user_ids;
		sort( $sorted_user_ids );

		$this->assertContains( $user_id, $user_ids );
		$this->assertSame( $sorted_user_ids, $user_ids );
	}

	/**
	 * Tests that the system notification email belongs to a site administrator.
	 */
	public function test_admin_email_returns_site_administrator_email(): void {
		self::factory()->user->create(
			[
				'role'       => 'author',
				'user_email' => 'site-author@example.org',
			]
		);
		self::factory()->user->create(
			[
				'role'       => 'administrator',
				'user_email' => 'site-administrator@example.org',
			]
		);

		$site = CalmPress_Site::current();

		foreach ( [ 'invalid', 'site-author@example.org' ] as $email ) {
			$user = get_user_by( 'email', $site->admin_email( $email ) );

			$this->assertContains( 'administrator', $user->roles );
		}

		$this->assertSame( 'site-administrator@example.org', $site->admin_email( 'site-administrator@example.org' ) );
	}

	/**
	 * Tests that the site retrieves its configured comment moderator.
	 */
	public function test_default_comment_moderator_user_returns_configured_user(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'editor' ] );
		update_option( 'comment_moderator_user', $user_id );

		$this->assertSame( $user_id, CalmPress_Site::current()->default_comment_moderator_user()->ID );
	}

	/**
	 * Tests resolution of the comment moderation notification email.
	 */
	public function test_default_comment_moderator_email(): void {
		$author_id = self::factory()->user->create(
			[
				'role'       => 'author',
				'user_email' => 'comment-author@example.org',
			]
		);
		$administrator_id = self::factory()->user->create(
			[
				'role'       => 'administrator',
				'user_email' => 'comment-administrator@example.org',
			]
		);
		$editor_id = self::factory()->user->create(
			[
				'role'       => 'editor',
				'user_email' => 'comment-editor@example.org',
			]
		);
		$site = CalmPress_Site::current();

		delete_option( 'comment_moderator_user' );
		$user = get_user_by( 'email', $site->default_comment_moderator_email() );
		$this->assertContains( 'administrator', $user->roles );

		update_option( 'comment_moderator_user', $author_id );
		$user = get_user_by( 'email', $site->default_comment_moderator_email() );
		$this->assertContains( 'administrator', $user->roles );

		update_option( 'comment_moderator_user', $administrator_id );
		$this->assertSame( 'comment-administrator@example.org', $site->default_comment_moderator_email() );

		update_option( 'comment_moderator_user', $editor_id );
		$this->assertSame( 'comment-editor@example.org', $site->default_comment_moderator_email() );
	}
}
