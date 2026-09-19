<?php

require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/ms.php';

/**
 * Tests specific to users in multisite.
 *
 * @group user
 * @group ms-required
 * @group ms-user
 * @group multisite
 */
class Tests_User_Multisite extends WP_UnitTestCase {

	public function test_remove_user_from_blog() {
		$user1 = self::factory()->user->create_and_get();
		$user2 = self::factory()->user->create_and_get();

		$post_id = self::factory()->post->create( array( 'post_author' => $user1->ID ) );

		remove_user_from_blog( $user1->ID, 1, $user2->ID );

		$post = get_post( $post_id );

		$this->assertNotEquals( $user1->ID, $post->post_author );
		$this->assertEquals( $user2->ID, $post->post_author );
	}

	/**
	 * Test the returned data from get_blogs_of_user()
	 */
	public function test_get_blogs_of_user() {
		$user1_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		// Maintain a list of 6 total sites and include the primary network site.
		$blog_ids = self::factory()->blog->create_many( 5, array( 'user_id' => $user1_id ) );
		$blog_ids = array_merge( array( 1 ), $blog_ids );

		// All sites are new and not marked as archived, or deleted.
		$blog_ids_of_user = array_keys( get_blogs_of_user( $user1_id ) );

		// User should be a member of the created sites and the network's initial site.
		$this->assertSame( $blog_ids, $blog_ids_of_user );

		$this->assertTrue( remove_user_from_blog( $user1_id, $blog_ids[0] ) );
		$this->assertTrue( remove_user_from_blog( $user1_id, $blog_ids[2] ) );
		$this->assertTrue( remove_user_from_blog( $user1_id, $blog_ids[4] ) );

		unset( $blog_ids[0] );
		unset( $blog_ids[2] );
		unset( $blog_ids[4] );
		sort( $blog_ids );

		$blogs_of_user = get_blogs_of_user( $user1_id, false );

		// The user should still be a member of all remaining sites.
		$blog_ids_of_user = array_keys( $blogs_of_user );
		$this->assertSame( $blog_ids, $blog_ids_of_user );

		// Each site retrieved should match the expected structure.
		foreach ( $blogs_of_user as $blog_id => $blog ) {
			$this->assertSame( $blog_id, $blog->userblog_id );
			$this->assertObjectHasProperty( 'userblog_id', $blog );
			$this->assertObjectHasProperty( 'blogname', $blog );
			$this->assertObjectHasProperty( 'domain', $blog );
			$this->assertObjectHasProperty( 'path', $blog );
			$this->assertObjectHasProperty( 'site_id', $blog );
			$this->assertObjectHasProperty( 'siteurl', $blog );
		}

		// Passing true as the second parameter should retrieve ALL sites, even if marked.
		$blogs_of_user    = get_blogs_of_user( $user1_id, true );
		$blog_ids_of_user = array_keys( $blogs_of_user );
		$this->assertSame( $blog_ids, $blog_ids_of_user );
	}

	public function test_is_user_member_of_blog() {
		global $wpdb;

		$user1_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$user2_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$old_current = get_current_user_id();

		$this->assertSame( 0, $old_current );

		// Test for "get current user" when not logged in.
		$this->assertFalse( is_user_member_of_blog() );

		wp_set_current_user( $user1_id );
		$site_id = get_current_blog_id();

		$this->assertTrue( is_user_member_of_blog() );
		$this->assertTrue( is_user_member_of_blog( 0, 0 ) );
		$this->assertTrue( is_user_member_of_blog( 0, $site_id ) );
		$this->assertTrue( is_user_member_of_blog( $user1_id ) );
		$this->assertTrue( is_user_member_of_blog( $user1_id, $site_id ) );

		$blog_id = self::factory()->blog->create( array( 'user_id' => get_current_user_id() ) );

		$this->assertIsInt( $blog_id );

		// Current user gets added to new blogs.
		$this->assertTrue( is_user_member_of_blog( $user1_id, $blog_id ) );
		// Other users should not.
		$this->assertFalse( is_user_member_of_blog( $user2_id, $blog_id ) );

		switch_to_blog( $blog_id );

		$this->assertTrue( is_user_member_of_blog( $user1_id ) );
		$this->assertFalse( is_user_member_of_blog( $user2_id ) );

		// Remove user 1 from blog.
		$this->assertTrue( remove_user_from_blog( $user1_id, $blog_id ) );

		// Add user 2 to blog.
		$this->assertTrue( add_user_to_blog( $blog_id, $user2_id, 'subscriber' ) );

		$this->assertFalse( is_user_member_of_blog( $user1_id ) );
		$this->assertTrue( is_user_member_of_blog( $user2_id ) );

		restore_current_blog();

		$this->assertFalse( is_user_member_of_blog( $user1_id, $blog_id ) );
		$this->assertTrue( is_user_member_of_blog( $user2_id, $blog_id ) );

		wpmu_delete_user( $user1_id );
		$user = new WP_User( $user1_id );
		$this->assertTrue( $user->exists() );
		$this->assertSame( '1', $user->deleted );
		$this->assertFalse( is_user_member_of_blog( $user1_id ) );

		wp_set_current_user( $old_current );
	}

	/**
	 * Tests that a pending site activation is not treated as site membership.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_pending_activation_is_not_site_membership(): void {
		$site_id = self::factory()->blog->create();
		$user    = self::factory()->user->create_and_get();

		// Assign the site-scoped invitation role.
		$this->assertTrue( add_user_to_blog( $site_id, $user->ID, 'pending_activation' ) );

		// Verify that membership APIs exclude the invited site.
		$this->assertTrue( $user->is_pending_activation_on_site( get_site( $site_id ) ) );
		$this->assertFalse( is_user_member_of_blog( $user->ID, $site_id ) );
		$this->assertNotContains( $site_id, $user->site_ids() );
		$this->assertArrayNotHasKey( $site_id, get_blogs_of_user( $user->ID ) );

		// Verify that default discovery excludes the invitation while an explicit role query finds it.
		$this->assertNotContains(
			$user->ID,
			array_map( 'intval', get_users( [ 'blog_id' => $site_id, 'fields' => 'ids' ] ) )
		);
		$this->assertContains(
			$user->ID,
			array_map( 'intval', get_users( [ 'blog_id' => $site_id, 'role' => 'pending_activation', 'fields' => 'ids' ] ) )
		);

		// Verify that the invitation has a separate role count but is not part of the active total.
		foreach ( [ 'time', 'memory' ] as $strategy ) {
			$counts = count_users( $strategy, $site_id );
			$this->assertSame( 0, $counts['total_users'] );
			$this->assertSame( 1, $counts['avail_roles']['pending_activation'] );
		}
	}

	/**
	 * Tests that edit_user() prepares a network-site invitation before sending its email.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_edit_user_prepares_network_site_invitation_before_notification(): void {
		$administrator = self::factory()->user->create_and_get( [ 'role' => 'administrator' ] );
		$mail_count     = 0;
		$previous_post  = $_POST;

		/**
		 * Counts and suppresses outgoing email.
		 *
		 * @since calmPress 1.0.0
		 */
		$count_mail = static function () use ( &$mail_count ) {
			++$mail_count;

			return false;
		};

		wp_set_current_user( $administrator->ID );
		$_POST = [
			'role'         => 'editor',
			'email'        => 'network-site-invitee@example.com',
			'display_name' => 'Network Site Invitee',
			'pass1'        => 'network-site-invitee-password',
			'pass2'        => 'network-site-invitee-password',
		];

		add_filter( 'pre_wp_mail', $count_mail );
		$user_id = edit_user();
		remove_filter( 'pre_wp_mail', $count_mail );
		$_POST = $previous_post;

		$user = get_userdata( $user_id );
		$site = get_site();

		$this->assertSame( 1, $mail_count );
		$this->assertTrue( $user->has_network_invite( get_network() ) );
		$this->assertTrue( $user->is_pending_activation_on_site( $site ) );
		$this->assertSame( 'editor', $user->site_invitation_role( $site ) );
		$this->assertFalse( metadata_exists( 'user', $user->ID, 'activate_to_role' ) );
	}

	/**
	 * Tests that wp_new_user_notification() sends activation instructions to a pending network user.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_wp_new_user_notification_sends_activation_email_for_pending_network_user(): void {
		$user       = self::factory()->user->create_and_get();
		$mail       = [];

		/**
		 * Captures the outgoing invitation email.
		 *
		 * @since calmPress 1.0.0
		 */
		$store_mail = static function ( $return, array $attributes ) use ( &$mail ) {
			$mail = $attributes;

			return false;
		};

		$user->invite_to_network( get_network() );
		add_filter( 'pre_wp_mail', $store_mail, 10, 2 );
		wp_new_user_notification( $user->ID, null, 'user' );
		remove_filter( 'pre_wp_mail', $store_mail, 10 );

		$this->assertStringContainsString( wp_login_url(), $mail['message'] );
		$this->assertStringNotContainsString( 'action=rp', $mail['message'] );
	}

	/**
	 * Tests that wp_signon() activates the network account without accepting site invitations.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @covers ::wp_signon
	 */
	public function test_wp_signon_does_not_accept_pending_site_invitations(): void {
		$password       = 'pending-network-user-password';
		$user           = self::factory()->user->create_and_get( [ 'user_pass' => $password ] );
		$first_site_id  = self::factory()->blog->create();
		$second_site_id = self::factory()->blog->create();
		$network        = get_network();

		// Give the account an independent network invitation and two pending site invitations.
		$user->invite_to_network( $network );
		$user->invite_to_network_site( get_site( $first_site_id ), 'editor' );
		$user->invite_to_network_site( get_site( $second_site_id ), 'author' );

		// Authenticate in the context of one invited site.
		switch_to_blog( $first_site_id );
		$authenticated_user = wp_signon(
			[
				'user_login'    => $user->user_email,
				'user_password' => $password,
			]
		);
		restore_current_blog();

		// Only network activation occurs; both site invitations remain pending.
		$this->assertNotWPError( $authenticated_user );
		$this->assertFalse( $user->has_network_invite( $network ) );
		$this->assertTrue( $user->is_pending_activation_on_site( get_site( $first_site_id ) ) );
		$this->assertTrue( $user->is_pending_activation_on_site( get_site( $second_site_id ) ) );
	}

	/**
	 * Tests that wp_signon() reports activation of a network-only account.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @covers ::wp_signon
	 */
	public function test_wp_signon_fires_account_activated_action_for_network_invitation(): void {
		$password          = 'network-invitation-password';
		$user              = self::factory()->user->create_and_get(
			[
				'role'      => '',
				'user_pass' => $password,
			]
		);
		$network           = get_network();
		$activated_user_id = 0;

		$user->invite_to_network( $network );

		/**
		 * Records the user whose account was activated.
		 *
		 * @since calmPress 1.0.0
		 *
		 * @param WP_User $activated_user The newly activated user.
		 */
		$record_activation = static function ( WP_User $activated_user ) use ( &$activated_user_id ): void {
			$activated_user_id = $activated_user->ID;
		};

		add_action( 'user_account_activated', $record_activation );
		$authenticated_user = wp_signon(
			[
				'user_login'    => $user->user_email,
				'user_password' => $password,
			]
		);
		$this->assertNotWPError( $authenticated_user );
		$this->assertSame( $user->ID, $activated_user_id );
		$this->assertFalse( $user->has_network_invite( $network ) );

		// Later authentication must not report another activation.
		$activated_user_id = 0;
		$authenticated_user = wp_signon(
			[
				'user_login'    => $user->user_email,
				'user_password' => $password,
			]
		);
		remove_action( 'user_account_activated', $record_activation );

		$this->assertNotWPError( $authenticated_user );
		$this->assertSame( 0, $activated_user_id );
	}

	/**
	 * Tests that activating a network invitation notifies its configured administrator.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @covers ::wp_signon
	 */
	public function test_wp_signon_notifies_network_account_activation(): void {
		$password  = 'network-notification-password';
		$user      = self::factory()->user->create_and_get(
			[
				'role'      => '',
				'user_pass' => $password,
			]
		);
		$network   = get_network();
		$recipient = get_userdata( (int) get_network_option( $network->id, 'admin_user_id' ) );
		$mutator   = new Tests_User_Account_Activated_Email_Mutator();

		$user->invite_to_network( $network );

		calmpress\email\User_Account_Activated_Email::register_mutator( $mutator );
		try {
			$authenticated_user = wp_signon(
				[
					'user_login'    => $user->user_email,
					'user_password' => $password,
				]
			);
		} finally {
			calmpress\email\User_Account_Activated_Email::remove_mutation_observer( $mutator );
		}

		$this->assertNotWPError( $authenticated_user );
		$this->assertInstanceOf( calmpress\email\User_Account_Activated_Email::class, $mutator->email );
		$this->assertSame( $recipient->ID, $mutator->email->user->ID );
		$this->assertSame( $user->ID, $mutator->email->activated_user->ID );
		$this->assertSame( $network, $mutator->email->context );
	}

	/**
	 * Tests that wp_signon() accepts the only site invitation when activating a network account.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @covers ::wp_signon
	 */
	public function test_wp_signon_accepts_only_site_invitation_for_new_account(): void {
		$password = 'single-site-invitation-password';
		$user     = self::factory()->user->create_and_get( [ 'user_pass' => $password ] );
		$site     = get_site( self::factory()->blog->create() );
		$network  = get_network();

		// Give the pending network account one site invitation.
		$user->invite_to_network( $network );
		$user->invite_to_network_site( $site, 'editor' );

		// Authenticate the newly created account in the invited site's context.
		switch_to_blog( (int) $site->blog_id );
		$authenticated_user = wp_signon(
			[
				'user_login'    => $user->user_email,
				'user_password' => $password,
			]
		);
		restore_current_blog();

		// Network and site activation complete together with the site's requested role.
		$this->assertNotWPError( $authenticated_user );
		$this->assertFalse( $user->has_network_invite( $network ) );
		$this->assertSame( [ 'editor' ], ( new WP_User( $user->ID, '', (int) $site->blog_id ) )->roles );
		$this->assertTrue( is_user_member_of_blog( $user->ID, (int) $site->blog_id ) );
	}

	/**
	 * Tests that add_existing_user_to_blog() applies each site's requested role independently.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @covers ::add_existing_user_to_blog
	 */
	public function test_add_existing_user_to_blog_activates_only_its_site_invitation(): void {
		$user           = self::factory()->user->create_and_get();
		$first_site_id  = self::factory()->blog->create();
		$second_site_id = self::factory()->blog->create();

		// Create two site invitations with roles that will differ after acceptance.
		$user->invite_to_network_site( get_site( $first_site_id ), 'editor' );
		$user->invite_to_network_site( get_site( $second_site_id ), 'author' );

		// Accept only the first site's invitation with its requested role.
		switch_to_blog( $first_site_id );
		$result = add_existing_user_to_blog(
			[
				'user_id' => $user->ID,
				'role'    => 'editor',
			]
		);
		restore_current_blog();

		// The accepted site gets its requested role while the other invitation remains pending.
		$this->assertTrue( $result );
		$this->assertSame( [ 'editor' ], ( new WP_User( $user->ID, '', $first_site_id ) )->roles );
		$this->assertTrue( $user->is_pending_activation_on_site( get_site( $second_site_id ) ) );

		// Accepting the second invitation applies that site's independently requested role.
		switch_to_blog( $second_site_id );
		$result = add_existing_user_to_blog(
			[
				'user_id' => $user->ID,
				'role'    => 'author',
			]
		);
		restore_current_blog();

		$this->assertTrue( $result );
		$this->assertSame( [ 'author' ], ( new WP_User( $user->ID, '', $second_site_id ) )->roles );
		$this->assertSame( [ 'editor' ], ( new WP_User( $user->ID, '', $first_site_id ) )->roles );
	}

	/**
	 * @ticket 20601
	 */
	public function test_user_member_of_blog() {
		global $wp_rewrite;

		self::factory()->blog->create();
		$user_id = self::factory()->user->create();
		self::factory()->blog->create( array( 'user_id' => $user_id ) );

		$blogs = get_blogs_of_user( $user_id );
		$this->assertCount( 2, $blogs );
		$first = reset( $blogs )->userblog_id;
		remove_user_from_blog( $user_id, $first );

		$blogs  = get_blogs_of_user( $user_id );
		$second = reset( $blogs )->userblog_id;
		$this->assertCount( 1, $blogs );

		switch_to_blog( $first );
		$wp_rewrite->init();

		switch_to_blog( $second );
		$wp_rewrite->init();

		add_user_to_blog( $first, $user_id, 'administrator' );
		$blogs = get_blogs_of_user( $user_id );
		$this->assertCount( 2, $blogs );
	}

	public function test_revoked_super_admin_can_be_deleted() {
		$user_id = self::factory()->user->create();
		grant_super_admin( $user_id );
		revoke_super_admin( $user_id );

		$this->assertTrue( wpmu_delete_user( $user_id ) );
	}

	/**
	 * Tests that deleting a revoked super administrator retains and marks its user record.
	 */
	public function test_revoked_super_admin_is_deleted() {
		$user_id = self::factory()->user->create();
		grant_super_admin( $user_id );
		revoke_super_admin( $user_id );
		wpmu_delete_user( $user_id );
		$user = new WP_User( $user_id );

		$this->assertTrue( $user->exists(), 'WP_User->exists' );
		$this->assertSame( '1', $user->deleted );
	}

	public function test_super_admin_cannot_be_deleted() {
		$user_id = self::factory()->user->create();
		grant_super_admin( $user_id );

		$this->assertFalse( wpmu_delete_user( $user_id ) );
	}

	/**
	 * @ticket 27205
	 */
	public function test_granting_super_admins() {
		$user_id = self::factory()->user->create();

		$this->assertFalse( is_super_admin( $user_id ) );
		$this->assertFalse( revoke_super_admin( $user_id ) );
		$this->assertTrue( grant_super_admin( $user_id ) );
		$this->assertTrue( is_super_admin( $user_id ) );
		$this->assertFalse( grant_super_admin( $user_id ) );
		$this->assertTrue( revoke_super_admin( $user_id ) );

		// Try with two users.
		$second_user = self::factory()->user->create();
		$this->assertTrue( grant_super_admin( $user_id ) );
		$this->assertTrue( grant_super_admin( $second_user ) );
		$this->assertTrue( is_super_admin( $second_user ) );
		$this->assertTrue( is_super_admin( $user_id ) );
		$this->assertTrue( revoke_super_admin( $user_id ) );
		$this->assertTrue( revoke_super_admin( $second_user ) );
	}

	/**
	 * Tests that a missing super admin option does not grant privileges to the admin login.
	 */
	public function test_get_super_admins_has_no_default_user() {
		$site_admins = get_site_option( 'site_admins' );

		delete_site_option( 'site_admins' );
		$actual = get_super_admins();
		update_site_option( 'site_admins', $site_admins );

		$this->assertSame( array(), $actual );
	}

	/**
	 * Tests that a numeric string can identify a user for network deletion.
	 */
	public function test_numeric_string_user_id() {
		$u = self::factory()->user->create();

		$u_string = (string) $u;
		$this->assertTrue( wpmu_delete_user( $u_string ) );
		$this->assertSame( '1', get_user_by( 'id', $u )->deleted );
	}

	/**
	 * Tests that network deletion anonymizes the user without deleting authored content.
	 */
	public function test_wpmu_delete_user_anonymizes_user_and_preserves_content() {
		global $wpdb;

		$password = 'correct horse battery staple';
		$user_id  = self::factory()->user->create(
			array(
				'user_email'   => 'network-user@example.org',
				'user_pass'    => $password,
				'display_name' => 'Network User',
			)
		);
		$post_id  = self::factory()->post->create( array( 'post_author' => $user_id ) );
		update_user_meta( $user_id, 'personal_data', 'private value' );

		$this->assertTrue( wpmu_delete_user( $user_id ) );

		$user = get_user_by( 'id', $user_id );

		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertSame( '1', $user->deleted );
		$this->assertFalse( $user->can_login() );
		$this->assertMatchesRegularExpression( '/^deleted-[0-9]{8}$/', $user->user_login );
		$this->assertMatchesRegularExpression( '/^deleted-[0-9]{8}@example\.invalid$/', $user->user_email );
		$this->assertSame( 'deleted ' . substr( $user->user_email, 8, 8 ), $user->display_name );
		$this->assertSame( '', $user->user_url );
		$this->assertSame( '', $user->user_activation_key );
		$this->assertSame( array(), get_user_meta( $user_id ) );
		$this->assertSame( $user_id, (int) get_post( $post_id )->post_author );
		$this->assertWPError( wp_authenticate( $user->user_login, $password ) );
		$this->assertSame( 1, (int) $wpdb->get_var( $wpdb->prepare( "SELECT deleted FROM $wpdb->users WHERE ID = %d", $user_id ) ) );
	}

	/**
	 * Tests that an already deleted network user is not deleted a second time.
	 */
	public function test_wpmu_delete_user_returns_false_for_deleted_user() {
		$user_id = self::factory()->user->create();

		$this->assertTrue( wpmu_delete_user( $user_id ) );
		$this->assertFalse( wpmu_delete_user( $user_id ) );
	}

	/**
	 * Tests that a user can be added with a network-deleted user's former email address.
	 */
	public function test_can_add_user_with_network_deleted_user_email_address() {
		$email = 'former-network-user@example.org';
		remove_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10 );

		$_POST = array(
			'email' => $email,
			'pass1' => 'original-password',
			'pass2' => 'original-password',
		);
		$deleted_user_id = add_user();

		$this->assertNotWPError( $deleted_user_id );
		$this->assertTrue( wpmu_delete_user( $deleted_user_id ) );

		$_POST = array(
			'email' => $email,
			'pass1' => 'replacement-password',
			'pass2' => 'replacement-password',
		);

		$new_user_id = add_user();
		add_action( 'edit_user_created_user', 'wp_send_new_user_notifications', 10, 2 );

		$this->assertNotWPError( $new_user_id );
		$this->assertSame( $email, get_userdata( $new_user_id )->user_email );
		$this->assertSame( $new_user_id, email_exists( $email ) );
	}

	/**
	 * @ticket 33800
	 */
	public function test_should_return_false_for_non_numeric_string_user_id() {
		$this->assertFalse( wpmu_delete_user( 'abcde' ) );
	}

	/**
	 * @ticket 33800
	 */
	public function test_should_return_false_for_object_user_id() {
		$u_obj = self::factory()->user->create_and_get();
		$this->assertFalse( wpmu_delete_user( $u_obj ) );
		$this->assertSame( $u_obj->ID, username_exists( $u_obj->user_login ) );
	}

	/**
	 * @ticket 38356
	 */
	public function test_add_user_to_blog_subscriber() {
		$site_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create();

		add_user_to_blog( $site_id, $user_id, 'subscriber' );

		switch_to_blog( $site_id );
		$user = get_user_by( 'id', $user_id );
		restore_current_blog();

		wp_delete_site( $site_id );
		wpmu_delete_user( $user_id );

		$this->assertContains( 'subscriber', $user->roles );
	}

	/**
	 * @ticket 38356
	 */
	public function test_add_user_to_blog_invalid_user() {
		global $wpdb;

		$site_id = self::factory()->blog->create();

		$suppress = $wpdb->suppress_errors();
		$result   = add_user_to_blog( 73622, $site_id, 'subscriber' );
		$wpdb->suppress_errors( $suppress );

		wp_delete_site( $site_id );

		$this->assertWPError( $result );
	}

	/**
	 * @ticket 41101
	 */
	public function test_should_fail_can_add_user_to_blog_filter() {
		$site_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create();

		add_filter( 'can_add_user_to_blog', '__return_false' );
		$result = add_user_to_blog( $site_id, $user_id, 'subscriber' );

		$this->assertWPError( $result );
	}

	/**
	 * @ticket 41101
	 */
	public function test_should_succeed_can_add_user_to_blog_filter() {
		$site_id = self::factory()->blog->create();
		$user_id = self::factory()->user->create();

		add_filter( 'can_add_user_to_blog', '__return_true' );
		$result = add_user_to_blog( $site_id, $user_id, 'subscriber' );

		$this->assertTrue( $result );
	}

	/**
	 * @ticket 23016
	 */
	public function test_wp_roles_global_is_reset() {
		global $wp_roles;
		$role      = 'test_global_is_reset';
		$role_name = 'Test Global Is Reset';
		$blog_id   = self::factory()->blog->create();

		$wp_roles->add_role( $role, $role_name, array() );

		$this->assertNotEmpty( $wp_roles->get_role( $role ) );

		switch_to_blog( $blog_id );

		$this->assertEmpty( $wp_roles->get_role( $role ) );

		$wp_roles->add_role( $role, $role_name, array() );

		$this->assertNotEmpty( $wp_roles->get_role( $role ) );

		restore_current_blog();

		$this->assertNotEmpty( $wp_roles->get_role( $role ) );

		$wp_roles->remove_role( $role );
	}

	/**
	 * @ticket 39170
	 */
	public function test_revoke_super_admin_with_network_email() {
		$old_network_email = get_site_option( 'admin_email' );
		$email_address     = 'superadmin333@example.org';

		$user_id = self::factory()->user->create(
			array(
				'user_email' => $email_address,
			)
		);

		grant_super_admin( $user_id );
		update_site_option( 'admin_email', $email_address );

		$result = revoke_super_admin( $user_id );

		update_site_option( 'admin_email', $old_network_email );
		$this->assertTrue( $result );
	}
}
