<?php

/**
 * @group user
 * @group capabilities
 * @covers ::map_meta_cap
 */
class Tests_User_MapMetaCap extends WP_UnitTestCase {

	protected static $post_type    = 'mapmetacap';
	protected static $super_admins = null;
	protected static $user_id      = null;
	protected static $author_id    = null;
	protected static $post_id      = null;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$user_id   = $factory->user->create( array( 'role' => 'administrator' ) );
		self::$author_id = $factory->user->create( array( 'role' => 'administrator' ) );

		if ( isset( $GLOBALS['super_admins'] ) ) {
			self::$super_admins = $GLOBALS['super_admins'];
		}
		$user                    = new WP_User( self::$user_id );
		$GLOBALS['super_admins'] = array( $user->user_login );

		register_post_type( self::$post_type );

		self::$post_id = $factory->post->create(
			array(
				'post_type'   => self::$post_type,
				'post_status' => 'private',
				'post_author' => self::$author_id,
			)
		);
	}

	public static function wpTearDownAfterClass() {
		$GLOBALS['super_admins'] = self::$super_admins;
		unset( $GLOBALS['wp_post_types'][ self::$post_type ] );
	}

	/**
	 * @ticket 13905
	 */
	public function test_capability_type_post_with_invalid_id() {
		$this->assertSame(
			array( 'do_not_allow' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id + 1 )
		);
	}

	/**
	 * Tests that deletion capability is denied for a configured Site Icon attachment.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_delete_post_is_denied_for_site_icon_attachment() {
		$attachment_id = self::factory()->attachment->create();
		update_option( 'site_icon', $attachment_id );

		$this->assertSame(
			[ 'do_not_allow' ],
			map_meta_cap( 'delete_post', self::$user_id, $attachment_id )
		);

		delete_option( 'site_icon' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Tests that deletion capability is denied for a configured Logo attachment.
	 */
	public function test_delete_post_is_denied_for_logo_attachment() {
		$attachment_id = self::factory()->attachment->create();
		update_option( 'custom_logo', $attachment_id );

		$this->assertSame(
			[ 'do_not_allow' ],
			map_meta_cap( 'delete_post', self::$user_id, $attachment_id )
		);

		delete_option( 'custom_logo' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Tests that deletion capability is denied for a Network Site Icon attachment.
	 *
	 * @since calmPress 1.0.0
	 * @group ms-required
	 */
	public function test_delete_post_is_denied_for_network_site_icon_attachment() {
		$attachment_id = self::factory()->attachment->create();
		update_network_option( null, 'site_icon', $attachment_id );

		$this->assertSame(
			[ 'do_not_allow' ],
			map_meta_cap( 'delete_post', self::$user_id, $attachment_id )
		);

		delete_network_option( null, 'site_icon' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Tests that deletion capability is denied for a Network Logo attachment.
	 *
	 * @group ms-required
	 */
	public function test_delete_post_is_denied_for_network_logo_attachment() {
		$attachment_id = self::factory()->attachment->create();
		update_network_option( null, 'custom_logo', $attachment_id );

		$this->assertSame(
			[ 'do_not_allow' ],
			map_meta_cap( 'delete_post', self::$user_id, $attachment_id )
		);

		delete_network_option( null, 'custom_logo' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Tests that the Network Site Icon ID does not deny deletion capability on another site.
	 *
	 * @since calmPress 1.0.0
	 * @group ms-required
	 */
	public function test_network_site_icon_id_does_not_deny_deletion_capability_on_another_site() {
		$attachment_id = self::factory()->attachment->create( [ 'import_id' => 9876 ] );
		update_network_option( null, 'site_icon', $attachment_id );

		$blog_id = self::factory()->blog->create();
		add_user_to_blog( $blog_id, self::$user_id, 'administrator' );
		switch_to_blog( $blog_id );

		$other_attachment_id = self::factory()->attachment->create( [ 'import_id' => $attachment_id ] );

		$this->assertSame( $attachment_id, $other_attachment_id );
		$this->assertTrue( user_can( self::$user_id, 'delete_post', $other_attachment_id ) );

		wp_delete_attachment( $other_attachment_id, true );
		restore_current_blog();
		delete_network_option( null, 'site_icon' );
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * Tests that the Network Logo ID does not deny deletion capability on another site.
	 *
	 * @group ms-required
	 */
	public function test_network_logo_id_does_not_deny_deletion_capability_on_another_site() {
		$attachment_id = self::factory()->attachment->create( [ 'import_id' => 9877 ] );
		update_network_option( null, 'custom_logo', $attachment_id );

		$blog_id = self::factory()->blog->create();
		add_user_to_blog( $blog_id, self::$user_id, 'administrator' );
		switch_to_blog( $blog_id );

		$other_attachment_id = self::factory()->attachment->create( [ 'import_id' => $attachment_id ] );

		$this->assertSame( $attachment_id, $other_attachment_id );
		$this->assertTrue( user_can( self::$user_id, 'delete_post', $other_attachment_id ) );

		wp_delete_attachment( $other_attachment_id, true );
		restore_current_blog();
		delete_network_option( null, 'custom_logo' );
		wp_delete_attachment( $attachment_id, true );
	}

	public function test_capability_type_post_with_no_extra_caps() {

		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'post',
			)
		);
		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertTrue( $post_type_object->map_meta_cap );

		$this->assertSame(
			array( 'edit_others_posts', 'edit_private_posts' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'edit_others_posts', 'edit_private_posts' ),
			map_meta_cap( $post_type_object->cap->edit_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'read_private_posts' ),
			map_meta_cap( 'read_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'read_private_posts' ),
			map_meta_cap( $post_type_object->cap->read_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'delete_others_posts', 'delete_private_posts' ),
			map_meta_cap( 'delete_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'delete_others_posts', 'delete_private_posts' ),
			map_meta_cap( $post_type_object->cap->delete_post, self::$user_id, self::$post_id )
		);
	}

	public function test_custom_capability_type_with_map_meta_cap() {
		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'book',
				'map_meta_cap'    => true,
			)
		);

		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertSame(
			array( 'edit_others_books', 'edit_private_books' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'edit_others_books', 'edit_private_books' ),
			map_meta_cap( $post_type_object->cap->edit_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'read_private_books' ),
			map_meta_cap( 'read_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'read_private_books' ),
			map_meta_cap( $post_type_object->cap->read_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'delete_others_books', 'delete_private_books' ),
			map_meta_cap( 'delete_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'delete_others_books', 'delete_private_books' ),
			map_meta_cap( $post_type_object->cap->delete_post, self::$user_id, self::$post_id )
		);
	}

	public function test_capability_type_post_with_one_renamed_cap() {
		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'post',
				'capabilities'    => array( 'edit_posts' => 'edit_books' ),
			)
		);

		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertFalse( $post_type_object->map_meta_cap );

		$this->assertSame(
			array( 'edit_post' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'edit_post' ),
			map_meta_cap( $post_type_object->cap->edit_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'read_post' ),
			map_meta_cap( 'read_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'read_post' ),
			map_meta_cap( $post_type_object->cap->read_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'delete_post' ),
			map_meta_cap( 'delete_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'delete_post' ),
			map_meta_cap( $post_type_object->cap->delete_post, self::$user_id, self::$post_id )
		);
	}

	public function test_capability_type_post_map_meta_cap_true_with_renamed_cap() {
		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array(
					'edit_post'         => 'edit_book', // maps back to itself.
					'edit_others_posts' => 'edit_others_books',
				),
			)
		);

		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertTrue( $post_type_object->map_meta_cap );

		$this->assertSame(
			array( 'edit_others_books', 'edit_private_posts' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'edit_others_books', 'edit_private_posts' ),
			map_meta_cap( $post_type_object->cap->edit_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'read_private_posts' ),
			map_meta_cap( 'read_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'read_private_posts' ),
			map_meta_cap( $post_type_object->cap->read_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'delete_others_posts', 'delete_private_posts' ),
			map_meta_cap( 'delete_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'delete_others_posts', 'delete_private_posts' ),
			map_meta_cap( $post_type_object->cap->delete_post, self::$user_id, self::$post_id )
		);
	}

	public function test_capability_type_post_with_all_meta_caps_renamed() {
		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'post',
				'capabilities'    => array(
					'edit_post'   => 'edit_book',
					'read_post'   => 'read_book',
					'delete_post' => 'delete_book',
				),
			)
		);

		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertFalse( $post_type_object->map_meta_cap );

		$this->assertSame(
			array( 'edit_book' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'edit_book' ),
			map_meta_cap( $post_type_object->cap->edit_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'read_book' ),
			map_meta_cap( 'read_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'read_book' ),
			map_meta_cap( $post_type_object->cap->read_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'delete_book' ),
			map_meta_cap( 'delete_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'delete_book' ),
			map_meta_cap( $post_type_object->cap->delete_post, self::$user_id, self::$post_id )
		);
	}

	public function test_capability_type_post_with_all_meta_caps_renamed_mapped() {
		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'post',
				'map_meta_cap'    => true,
				'capabilities'    => array(
					'edit_post'   => 'edit_book',
					'read_post'   => 'read_book',
					'delete_post' => 'delete_book',
				),
			)
		);

		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertTrue( $post_type_object->map_meta_cap );

		$this->assertSame(
			array( 'edit_others_posts', 'edit_private_posts' ),
			map_meta_cap( 'edit_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'edit_others_posts', 'edit_private_posts' ),
			map_meta_cap( $post_type_object->cap->edit_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'read_private_posts' ),
			map_meta_cap( 'read_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'read_private_posts' ),
			map_meta_cap( $post_type_object->cap->read_post, self::$user_id, self::$post_id )
		);

		$this->assertSame(
			array( 'delete_others_posts', 'delete_private_posts' ),
			map_meta_cap( 'delete_post', self::$user_id, self::$post_id )
		);
		$this->assertSame(
			array( 'delete_others_posts', 'delete_private_posts' ),
			map_meta_cap( $post_type_object->cap->delete_post, self::$user_id, self::$post_id )
		);
	}

	/**
	 * @ticket 30991
	 */
	public function test_delete_posts_cap_without_map_meta_cap() {
		register_post_type(
			self::$post_type,
			array(
				'capability_type' => 'post',
				'map_meta_cap'    => false,
			)
		);

		$post_type_object = get_post_type_object( self::$post_type );

		$this->assertFalse( $post_type_object->map_meta_cap );
		$this->assertSame( 'delete_posts', $post_type_object->cap->delete_posts );
	}

	public function test_unfiltered_html_cap() {
		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) ) {
			$this->assertFalse( DISALLOW_UNFILTERED_HTML );
		}

		if ( is_multisite() ) {
			$this->assertSame( array( 'do_not_allow' ), map_meta_cap( 'unfiltered_html', 0 ) );
			$this->assertSame( array( 'unfiltered_html' ), map_meta_cap( 'unfiltered_html', self::$user_id ) );
		} else {
			$this->assertSame( array( 'unfiltered_html' ), map_meta_cap( 'unfiltered_html', self::$user_id ) );
		}
	}

	/**
	 * Test a post without an author.
	 *
	 * @ticket 27020
	 */
	public function test_authorless_posts_capabilities() {
		$post_id = self::factory()->post->create(
			array(
				'post_author' => 0,
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);
		$editor  = self::factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertSame( array( 'edit_others_posts', 'edit_published_posts' ), map_meta_cap( 'edit_post', $editor, $post_id ) );
		$this->assertSame( array( 'delete_others_posts', 'delete_published_posts' ), map_meta_cap( 'delete_post', $editor, $post_id ) );
	}

	/**
	 * Test deleting front page.
	 *
	 * @ticket 37580
	 */
	public function test_only_users_who_can_manage_options_can_delete_page_on_front() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		update_option( 'page_on_front', $post_id );
		$caps = map_meta_cap( 'delete_page', self::$user_id, $post_id );
		delete_option( 'page_on_front' );

		$this->assertSame( array( 'manage_options' ), $caps );
	}

	/**
	 * Test deleting posts page.
	 *
	 * @ticket 37580
	 */
	public function test_only_users_who_can_manage_options_can_delete_page_for_posts() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		update_option( 'page_for_posts', $post_id );
		$caps = map_meta_cap( 'delete_page', self::$user_id, $post_id );
		delete_option( 'page_for_posts' );

		$this->assertSame( array( 'manage_options' ), $caps );
	}

	/**
	 * @dataProvider data_meta_caps_throw_doing_it_wrong_without_required_argument_provided
	 * @ticket 44591
	 *
	 * @param string $cap The meta capability requiring an argument.
	 */
	public function test_meta_caps_throw_doing_it_wrong_without_required_argument_provided( $cap ) {
		$admin_user = self::$user_id;
		$this->setExpectedIncorrectUsage( 'map_meta_cap' );
		$this->assertContains( 'do_not_allow', map_meta_cap( $cap, $admin_user ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[] Test parameters {
	 *     @type string $cap The meta capability requiring an argument.
	 * }
	 */
	public function data_meta_caps_throw_doing_it_wrong_without_required_argument_provided() {
		return array(
			array( 'delete_post' ),
			array( 'delete_page' ),
			array( 'edit_post' ),
			array( 'edit_page' ),
			array( 'read_post' ),
			array( 'read_page' ),
			array( 'publish_post' ),
			array( 'edit_post_meta' ),
			array( 'delete_post_meta' ),
			array( 'add_post_meta' ),
			array( 'edit_comment_meta' ),
			array( 'delete_comment_meta' ),
			array( 'add_comment_meta' ),
			array( 'edit_term_meta' ),
			array( 'delete_term_meta' ),
			array( 'add_term_meta' ),
			array( 'edit_user_meta' ),
			array( 'delete_user_meta' ),
			array( 'add_user_meta' ),
			array( 'edit_comment' ),
			array( 'edit_term' ),
			array( 'delete_term' ),
			array( 'assign_term' ),
		);
	}
}
