<?php

/**
 * @group admin
 * @group ms-required
 * @group network-admin
 *
 * @covers WP_MS_Users_List_Table
 */
class Tests_Multisite_wpMsUsersListTable extends WP_UnitTestCase {
	protected static $site_ids;

	/**
	 * @var WP_MS_Users_List_Table
	 */
	public $table = false;

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_MS_Users_List_Table', array( 'screen' => 'ms-users' ) );
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$site_ids = array(
			'wordpress.org/'          => array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			),
			'wordpress.org/foo/'      => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/',
			),
			'wordpress.org/foo/bar/'  => array(
				'domain' => 'wordpress.org',
				'path'   => '/foo/bar/',
			),
			'wordpress.org/afoo/'     => array(
				'domain' => 'wordpress.org',
				'path'   => '/afoo/',
			),
			'make.wordpress.org/'     => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/',
			),
			'make.wordpress.org/foo/' => array(
				'domain' => 'make.wordpress.org',
				'path'   => '/foo/',
			),
			'www.w.org/'              => array(
				'domain' => 'www.w.org',
				'path'   => '/',
			),
			'www.w.org/foo/'          => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/',
			),
			'www.w.org/foo/bar/'      => array(
				'domain' => 'www.w.org',
				'path'   => '/foo/bar/',
			),
			'test.example.org/'       => array(
				'domain' => 'test.example.org',
				'path'   => '/',
			),
			'test2.example.org/'      => array(
				'domain' => 'test2.example.org',
				'path'   => '/',
			),
			'test3.example.org/zig/'  => array(
				'domain' => 'test3.example.org',
				'path'   => '/zig/',
			),
			'atest.example.org/'      => array(
				'domain' => 'atest.example.org',
				'path'   => '/',
			),
		);

		foreach ( self::$site_ids as &$id ) {
			$id = $factory->blog->create( $id );
		}
		unset( $id );
	}

	public static function wpTearDownAfterClass() {
		foreach ( self::$site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_MS_Users_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		$all   = get_user_count();
		$super = count( get_super_admins() );

		$expected = array(
			'all'     => '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/network/users.php" class="current" aria-current="page">All <span class="count">(' . $all . ')</span></a>',
			'super'   => '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/network/users.php?role=super">Super Admin <span class="count">(' . $super . ')</span></a>',
			'pending_activation' => '<a href="http://' . WP_TESTS_DOMAIN . '/wp-admin/network/users.php?role=pending_activation">Pending Activation <span class="count">(0)</span></a>',
		);

		$this->assertSame( $expected, $this->table->get_views() );
	}

	/**
	 * Tests that users pending network activation are listed separately from active users.
	 */
	public function test_pending_network_activation_view() {
		$email = 'listed-network-invitation@example.org';

		$user = self::factory()->user->create_and_get( [ 'user_email' => $email ] );
		$user->invite_to_network( get_network() );

		$_REQUEST['role'] = 'pending_activation';
		$this->table->prepare_items();

		unset( $_REQUEST['role'] );

		$this->assertCount( 1, $this->table->items );
		$this->assertSame( $email, $this->table->items[0]->user_email );
		$this->assertArrayHasKey( 'pending_activation', $this->table->get_views() );

		$GLOBALS['role'] = '';
	}

	/**
	 * Tests that a network-level user is hidden from unrelated networks.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_user_of_another_network_is_excluded() {
		$current_network = get_network();
		$other_network   = get_network( self::factory()->network->create() );
		$user            = self::factory()->user->create_and_get();

		remove_user_from_blog( $user->ID, (int) $current_network->site_id );
		$other_network->add_orphaned_user( $user );

		$this->table->prepare_items();
		$this->assertNotContains( $user->ID, wp_list_pluck( $this->table->items, 'ID' ) );

		add_user_to_blog( (int) $current_network->site_id, $user->ID, 'subscriber' );

		$this->table->prepare_items();
		$this->assertContains( $user->ID, wp_list_pluck( $this->table->items, 'ID' ) );
	}
}
