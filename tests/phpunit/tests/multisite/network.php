<?php

use calmpress\site\Site as CalmPress_Site;
use calmpress\utils\One_Time_Password;

/**
 * Tests specific to networks in multisite.
 *
 * @group ms-network
 * @group ms-required
 * @group multisite
 */
class Tests_Multisite_Network extends WP_UnitTestCase {

	protected $plugin_hook_count = 0;

	protected static $different_network_id;
	protected static $different_site_ids = array();

	public function tear_down() {
		global $current_site;
		$current_site->id = 1;
		parent::tear_down();
	}

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$different_network_id = $factory->network->create(
			array(
				'domain' => 'wordpress.org',
				'path'   => '/',
			)
		);

		$sites = array(
			array(
				'domain'     => 'wordpress.org',
				'path'       => '/',
				'network_id' => self::$different_network_id,
			),
			array(
				'domain'     => 'wordpress.org',
				'path'       => '/foo/',
				'network_id' => self::$different_network_id,
			),
			array(
				'domain'     => 'wordpress.org',
				'path'       => '/bar/',
				'network_id' => self::$different_network_id,
			),
		);

		foreach ( $sites as $site ) {
			self::$different_site_ids[] = $factory->blog->create( $site );
		}
	}

	public static function wpTearDownAfterClass() {
		global $wpdb;

		foreach ( self::$different_site_ids as $id ) {
			wp_delete_site( $id );
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->sitemeta} WHERE site_id = %d", self::$different_network_id ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->site} WHERE id= %d", self::$different_network_id ) );

		wp_update_network_site_counts();
	}

	/**
	 * By default, only one network exists and has a network ID of 1.
	 */
	public function test_get_main_network_id_default() {
		$this->assertSame( 1, get_main_network_id() );
	}

	/**
	 * Tests that unused network attachments older than a day are deleted.
	 */
	public function test_delete_unused_network_attachments() {
		$old_date = gmdate( 'Y-m-d H:i:s', time() - ( 2 * DAY_IN_SECONDS ) );

		$unused_attachment_id = self::factory()->attachment->create(
			[
				'post_status'   => 'network',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			]
		);
		$recent_attachment_id = self::factory()->attachment->create(
			[
				'post_status' => 'network',
			]
		);
		$site_icon_id = self::factory()->attachment->create(
			[
				'post_status'   => 'network',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			]
		);
		$logo_id = self::factory()->attachment->create(
			[
				'post_status'   => 'network',
				'post_date'     => $old_date,
				'post_date_gmt' => $old_date,
			]
		);
		update_network_option( null, 'site_icon', $site_icon_id );
		update_network_option( null, 'custom_logo', $logo_id );

		get_network()->delete_unused_network_attachments();

		$this->assertNull( get_post( $unused_attachment_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $recent_attachment_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $site_icon_id ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $logo_id ) );

		delete_network_option( null, 'site_icon' );
		delete_network_option( null, 'custom_logo' );
	}

	/**
	 * If a second network is created, network ID 1 should still be returned
	 * as the main network ID.
	 */
	public function test_get_main_network_id_two_networks() {
		self::factory()->network->create();

		$this->assertSame( 1, get_main_network_id() );
	}

	/**
	 * When the `$current_site` global is populated with another network, the
	 * main network should still return as 1.
	 */
	public function test_get_main_network_id_after_network_switch() {
		global $current_site;

		$id = self::factory()->network->create();

		$current_site->id = (int) $id;

		$this->assertSame( 1, get_main_network_id() );
	}

	/**
	 * When the first network is removed, the next should return as the main
	 * network ID.
	 *
	 * @todo In the future, we'll have a smarter way of deleting a network. For now,
	 * fake the process with UPDATE queries.
	 */
	public function test_get_main_network_id_after_network_delete() {
		global $wpdb, $current_site;

		$temp_id = self::$different_network_id + 1;

		$current_site->id = (int) self::$different_network_id;
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->site} SET id=%d WHERE id=1", $temp_id ) );
		$main_network_id = get_main_network_id();
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->site} SET id=1 WHERE id=%d", $temp_id ) );

		$this->assertSame( self::$different_network_id, $main_network_id );
	}

	public function test_get_main_network_id_filtered() {
		add_filter( 'get_main_network_id', array( $this, 'get_main_network_id' ) );
		$this->assertSame( 3, get_main_network_id() );
		remove_filter( 'get_main_network_id', array( $this, 'get_main_network_id' ) );
	}

	public function get_main_network_id() {
		return 3;
	}

	/**
	 * Tests that the `WP_Network::$id` property is an integer.
	 *
	 * @ticket 37050
	 *
	 * @covers WP_Network::__get
	 */
	public function test_wp_network_object_id_property_is_int() {
		$id = self::factory()->network->create();

		$network = WP_Network::get_instance( $id );

		$this->assertSame( (int) $id, $network->id );
	}

	/**
	 * Tests that the `WP_Network::$id` property is stored as an integer.
	 *
	 * Uses reflection to access the private property.
	 * Differs from using the public getter method, which casts to an integer.
	 *
	 * @ticket 62035
	 *
	 * @covers WP_Network::__construct
	 */
	public function test_wp_network_object_id_property_stored_as_int() {
		$id = self::factory()->network->create();

		$network = WP_Network::get_instance( $id );

		$reflection = new ReflectionObject( $network );
		$property   = $reflection->getProperty( 'id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$this->assertSame( (int) $id, $property->getValue( $network ) );
	}

	/**
	 * Tests that the `WP_Network::$blog_id` property is a string.
	 *
	 * @ticket 62035
	 *
	 * @covers WP_Network::__get
	 */
	public function test_wp_network_object_blog_id_property_is_int() {
		$id = self::factory()->network->create();

		$network = WP_Network::get_instance( $id );

		$this->assertIsString( $network->blog_id );
	}

	/**
	 * Tests that the `WP_Network::$blog_id` property is stored as a string.
	 *
	 * Uses reflection to access the private property.
	 * Differs from using the public getter method, which casts to a string.
	 *
	 * @ticket 62035
	 *
	 * @covers WP_Network::__construct
	 */
	public function test_wp_network_object_blog_id_property_stored_as_string() {
		$id = self::factory()->network->create();

		$network = WP_Network::get_instance( $id );

		$reflection = new ReflectionObject( $network );
		$property   = $reflection->getProperty( 'blog_id' );
		if ( PHP_VERSION_ID < 80100 ) {
			$property->setAccessible( true );
		}

		$this->assertIsString( $property->getValue( $network ) );
	}

	/**
	 * @ticket 22917
	 */
	public function test_get_blog_count_no_filter_applied() {
		wp_update_network_counts();
		$site_count_start = get_blog_count();

		$site_ids = self::factory()->blog->create_many( 1 );
		$actual   = (int) get_blog_count(); // Count only updated when cron runs, so should be unchanged.

		foreach ( $site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
		wp_update_network_counts();

		$this->assertSame( $site_count_start + 1, $actual );
	}

	/**
	 * @ticket 22917
	 */
	public function test_get_blog_count_enable_live_network_counts_false() {
		wp_update_network_counts();
		$site_count_start = get_blog_count();

		add_filter( 'enable_live_network_counts', '__return_false' );
		$site_ids = self::factory()->blog->create_many( 1 );
		$actual   = (int) get_blog_count(); // Count only updated when cron runs, so should be unchanged.
		remove_filter( 'enable_live_network_counts', '__return_false' );

		foreach ( $site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
		wp_update_network_counts();

		$this->assertEquals( $site_count_start, $actual );
	}

	/**
	 * @ticket 22917
	 */
	public function test_get_blog_count_enabled_live_network_counts_true() {
		wp_update_network_counts();
		$site_count_start = get_blog_count();

		add_filter( 'enable_live_network_counts', '__return_true' );
		$site_ids = self::factory()->blog->create_many( 1 );
		$actual   = get_blog_count();
		remove_filter( 'enable_live_network_counts', '__return_true' );

		foreach ( $site_ids as $site_id ) {
			wp_delete_site( $site_id );
		}
		wp_update_network_counts();

		$this->assertSame( $site_count_start + 1, $actual );
	}

	/**
	 * @ticket 37865
	 */
	public function test_get_blog_count_on_different_network() {
		wp_update_network_site_counts( self::$different_network_id );

		$site_count = get_blog_count( self::$different_network_id );

		$this->assertEquals( count( self::$different_site_ids ), $site_count );
	}

	public function test_active_network_plugins() {
		$path = 'hello.php';

		// Local activate, should be invisible for the network.
		activate_plugin( $path ); // Enable the plugin for the current site.
		$active_plugins = wp_get_active_network_plugins();
		$this->assertSame( array(), $active_plugins );

		add_action( 'deactivated_plugin', array( $this, 'helper_deactivate_hook' ) );

		// Activate the plugin sitewide.
		activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
		$active_plugins = wp_get_active_network_plugins();
		$this->assertSame( array( WP_PLUGIN_DIR . '/hello.php' ), $active_plugins );

		// Deactivate the plugin.
		deactivate_plugins( $path );
		$active_plugins = wp_get_active_network_plugins();
		$this->assertSame( array(), $active_plugins );

		$this->assertSame( 1, $this->plugin_hook_count ); // Testing actions and silent mode.

		activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
		deactivate_plugins( $path, true );  // Silent mode.

		$this->assertSame( 1, $this->plugin_hook_count ); // Testing actions and silent mode.
	}

	/**
	 * @ticket 28651
	 */
	public function test_duplicate_network_active_plugin() {
		$path = 'hello.php';
		$mock = new MockAction();
		add_action( 'activate_' . $path, array( $mock, 'action' ) );

		// Should activate on the first try.
		activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
		$active_plugins = wp_get_active_network_plugins();
		$this->assertCount( 1, $active_plugins );
		$this->assertSame( 1, $mock->get_call_count() );

		// Should do nothing on the second try.
		activate_plugin( $path, '', true ); // Enable the plugin for all sites in the network.
		$active_plugins = wp_get_active_network_plugins();
		$this->assertCount( 1, $active_plugins );
		$this->assertSame( 1, $mock->get_call_count() );

		remove_action( 'activate_' . $path, array( $mock, 'action' ) );
	}

	public function test_is_plugin_active_for_network_true() {
		activate_plugin( 'hello.php', '', true );
		$this->assertTrue( is_plugin_active_for_network( 'hello.php' ) );
	}

	public function test_is_plugin_active_for_network_false() {
		deactivate_plugins( 'hello.php', false, true );
		$this->assertFalse( is_plugin_active_for_network( 'hello.php' ) );
	}

	public function helper_deactivate_hook() {
		++$this->plugin_hook_count;
	}

	public function test_wp_schedule_update_network_counts() {
		$this->assertFalse( wp_next_scheduled( 'update_network_counts' ) );

		// We can't use wp_schedule_update_network_counts() because WP_INSTALLING is set.
		wp_schedule_event( time(), 'twicedaily', 'update_network_counts' );

		$this->assertIsInt( wp_next_scheduled( 'update_network_counts' ) );
	}

	/**
	 * @ticket 37528
	 */
	public function test_wp_update_network_site_counts() {
		update_network_option( null, 'blog_count', 40 );

		$expected = get_sites(
			array(
				'network_id' => get_current_network_id(),
				'count'      => true,
			)
		);

		wp_update_network_site_counts();

		$result = get_blog_count();
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 37528
	 */
	public function test_wp_update_network_site_counts_on_different_network() {
		update_network_option( self::$different_network_id, 'blog_count', 40 );

		wp_update_network_site_counts( self::$different_network_id );

		$result = get_blog_count( self::$different_network_id );
		$this->assertSame( 3, $result );
	}

	/**
	 * @ticket 40349
	 */
	public function test_wp_update_network_user_counts() {
		global $wpdb;

		update_network_option( null, 'user_count', 40 );

		$expected = (int) $wpdb->get_var( "SELECT COUNT(ID) as c FROM $wpdb->users WHERE spam = '0' AND deleted = '0'" );

		wp_update_network_user_counts();

		$result = get_user_count();
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 40349
	 */
	public function test_wp_update_network_user_counts_on_different_network() {
		global $wpdb;

		update_network_option( self::$different_network_id, 'user_count', 40 );

		$expected = (int) $wpdb->get_var( "SELECT COUNT(ID) as c FROM $wpdb->users WHERE spam = '0' AND deleted = '0'" );

		wp_update_network_user_counts( self::$different_network_id );

		$result = get_user_count( self::$different_network_id );
		$this->assertSame( $expected, $result );
	}

	/**
	 * @ticket 40386
	 */
	public function test_wp_update_network_counts() {
		delete_network_option( null, 'blog_count' );
		delete_network_option( null, 'user_count' );

		wp_update_network_counts();

		$site_count = (int) get_blog_count();
		$user_count = (int) get_user_count();

		$this->assertGreaterThan( 0, $site_count );
		$this->assertGreaterThan( 0, $user_count );
	}

	/**
	 * @ticket 40386
	 */
	public function test_wp_update_network_counts_on_different_network() {
		delete_network_option( self::$different_network_id, 'blog_count' );
		delete_network_option( self::$different_network_id, 'user_count' );

		wp_update_network_counts( self::$different_network_id );

		$site_count = (int) get_blog_count( self::$different_network_id );
		$user_count = (int) get_user_count( self::$different_network_id );

		$this->assertGreaterThan( 0, $site_count );
		$this->assertGreaterThan( 0, $user_count );
	}

	/**
	 * Test the default behavior of upload_size_limit_filter.
	 * If any default option is changed, the function returns the min value between the
	 * parameter passed and the `fileupload_maxk` site option (1500Kb by default)
	 *
	 * @ticket 55926
	 */
	public function test_upload_size_limit_filter() {
		$return = upload_size_limit_filter( 1499 * KB_IN_BYTES );
		$this->assertSame( 1499 * KB_IN_BYTES, $return );
		$return = upload_size_limit_filter( 1501 * KB_IN_BYTES );
		$this->assertSame( 1500 * KB_IN_BYTES, $return );
	}

	/**
	 * Test if upload_size_limit_filter behaves as expected when the `fileupload_maxk` is 0 or an empty string.
	 *
	 * @ticket 55926
	 * @dataProvider data_upload_size_limit_filter_empty_fileupload_maxk
	 */
	public function test_upload_size_limit_filter_empty_fileupload_maxk( $callable_set_fileupload_maxk ) {
		add_filter( 'site_option_fileupload_maxk', $callable_set_fileupload_maxk );
		$return = upload_size_limit_filter( 1500 );
		$this->assertSame( 0, $return );
	}

	/**
	 * @ticket 55926
	 */
	public function data_upload_size_limit_filter_empty_fileupload_maxk() {
		return array(
			array( '__return_zero' ),
			array( '__return_empty_string' ),
		);
	}

	/**
	 * When upload_space_check is enabled, the space allowed is also considered by `upload_size_limit_filter`.
	 *
	 * @ticket 55926
	 */
	public function test_upload_size_limit_filter_when_upload_space_check_enabled() {
		add_filter( 'get_space_allowed', '__return_zero' );
		add_filter( 'site_option_upload_space_check_disabled', '__return_false' );
		$return = upload_size_limit_filter( 100 );
		$this->assertSame( 0, $return );
	}

	/**
	 * @ticket 40489
	 * @dataProvider data_wp_is_large_network
	 */
	public function test_wp_is_large_network( $using, $count, $expected, $different_network ) {
		$network_id     = $different_network ? self::$different_network_id : null;
		$network_option = 'users' === $using ? 'user_count' : 'blog_count';

		update_network_option( $network_id, $network_option, $count );

		$result = wp_is_large_network( $using, $network_id );
		if ( $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	public function data_wp_is_large_network() {
		return array(
			array( 'sites', 10000, false, false ),
			array( 'sites', 10001, true, false ),
			array( 'users', 10000, false, false ),
			array( 'users', 10001, true, false ),
			array( 'sites', 10000, false, true ),
			array( 'sites', 10001, true, true ),
			array( 'users', 10000, false, true ),
			array( 'users', 10001, true, true ),
		);
	}

	/**
	 * @ticket 40489
	 * @dataProvider data_wp_is_large_network_filtered_by_component
	 */
	public function test_wp_is_large_network_filtered_by_component( $using, $count, $expected, $different_network ) {
		$network_id     = $different_network ? self::$different_network_id : null;
		$network_option = 'users' === $using ? 'user_count' : 'blog_count';

		update_network_option( $network_id, $network_option, $count );

		add_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_for_users' ), 10, 3 );
		$result = wp_is_large_network( $using, $network_id );
		remove_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_for_users' ), 10 );

		if ( $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	public function data_wp_is_large_network_filtered_by_component() {
		return array(
			array( 'sites', 10000, false, false ),
			array( 'sites', 10001, true, false ),
			array( 'users', 1000, false, false ),
			array( 'users', 1001, true, false ),
			array( 'sites', 10000, false, true ),
			array( 'sites', 10001, true, true ),
			array( 'users', 1000, false, true ),
			array( 'users', 1001, true, true ),
		);
	}

	public function filter_wp_is_large_network_for_users( $is_large_network, $using, $count ) {
		if ( 'users' === $using ) {
			return $count > 1000;
		}

		return $is_large_network;
	}

	/**
	 * @ticket 40489
	 * @dataProvider data_wp_is_large_network_filtered_by_network
	 */
	public function test_wp_is_large_network_filtered_by_network( $using, $count, $expected, $different_network ) {
		$network_id     = $different_network ? self::$different_network_id : null;
		$network_option = 'users' === $using ? 'user_count' : 'blog_count';

		update_network_option( $network_id, $network_option, $count );

		add_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_on_different_network' ), 10, 4 );
		$result = wp_is_large_network( $using, $network_id );
		remove_filter( 'wp_is_large_network', array( $this, 'filter_wp_is_large_network_on_different_network' ), 10 );

		if ( $expected ) {
			$this->assertTrue( $result );
		} else {
			$this->assertFalse( $result );
		}
	}

	public function data_wp_is_large_network_filtered_by_network() {
		return array(
			array( 'sites', 10000, false, false ),
			array( 'sites', 10001, true, false ),
			array( 'users', 10000, false, false ),
			array( 'users', 10001, true, false ),
			array( 'sites', 1000, false, true ),
			array( 'sites', 1001, true, true ),
			array( 'users', 1000, false, true ),
			array( 'users', 1001, true, true ),
		);
	}

	public function filter_wp_is_large_network_on_different_network( $is_large_network, $using, $count, $network_id ) {
		if ( $network_id === (int) self::$different_network_id ) {
			return $count > 1000;
		}

		return $is_large_network;
	}

	/**
	 * @ticket 38699
	 */
	public function test_wpmu_create_blog_updates_correct_network_site_count() {
		global $wpdb;

		$original_count = get_blog_count( self::$different_network_id );

		$suppress = $wpdb->suppress_errors();
		$site_id  = wpmu_create_blog( 'example.org', '/', '', 1, array(), self::$different_network_id );
		$wpdb->suppress_errors( $suppress );

		$result = get_blog_count( self::$different_network_id );

		wpmu_delete_blog( $site_id, true );

		$this->assertSame( $original_count + 1, $result );
	}

	/**
	 * @ticket 29684
	 */
	public function test_network_blog_id_set() {
		$network = get_network( self::$different_network_id );

		$this->assertSame( (string) self::$different_site_ids[0], $network->blog_id );
	}

	/**
	 * @ticket 42251
	 */
	public function test_get_network_not_found_cache() {
		$new_network_id = $this->_get_next_network_id();
		$this->assertNull( get_network( $new_network_id ) );

		$num_queries = get_num_queries();
		$this->assertNull( get_network( $new_network_id ) );
		$this->assertSame( $num_queries, get_num_queries() );
	}

	/**
	 * @ticket 42251
	 */
	public function test_get_network_not_found_cache_clear() {
		$new_network_id = $this->_get_next_network_id();
		$this->assertNull( get_network( $new_network_id ) );

		$new_network = self::factory()->network->create_and_get();

		// Double-check we got the ID of the new network correct.
		$this->assertSame( $new_network_id, $new_network->id );

		// Verify that if we fetch the network now, it's no longer false.
		$fetched_network = get_network( $new_network_id );
		$this->assertInstanceOf( 'WP_Network', $fetched_network );
		$this->assertSame( $new_network_id, $fetched_network->id );
	}

	/**
	 * Tests that the current multisite site retrieves its administrators.
	 */
	public function test_current_site_retrieves_its_administrators() {
		$site    = CalmPress_Site::current();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertEquals( get_current_blog_id(), $site->blog_id );
		$this->assertContains( $user_id, wp_list_pluck( $site->administrators(), 'ID' ) );
	}

	/**
	 * Tests that the current site resolves its parent network.
	 */
	public function test_current_site_resolves_its_network() {
		$this->assertEquals( get_current_network_id(), CalmPress_Site::current()->network()->id );
	}

	/**
	 * Tests that the network admin email is controlled by the notification super administrator.
	 */
	public function test_network_admin_email_maps_to_notification_user() {
		$original_admin_user_id = (int) get_site_option( 'admin_user_id' );
		$user_id                = self::factory()->user->create(
			array(
				'user_email' => 'network-notifications@example.org',
			)
		);
		grant_super_admin( $user_id );

		update_site_option( 'admin_user_id', $user_id );

		$network = get_network();

		$this->assertContains( $user_id, wp_list_pluck( $network->administrators(), 'ID' ) );
		$this->assertTrue( get_userdata( $user_id )->is_system_notification_recipient( $network ) );
		$this->assertSame( 'network-notifications@example.org', $network->admin_email() );
		$this->assertSame( 'network-notifications@example.org', get_site_option( 'admin_email' ) );

		update_site_option( 'admin_user_id', -$user_id );
		$this->assertSame( $user_id, (int) get_site_option( 'admin_user_id' ) );

		$non_super_admin_id = self::factory()->user->create();
		update_site_option( 'admin_user_id', $non_super_admin_id );
		$this->assertSame( $user_id, (int) get_site_option( 'admin_user_id' ) );

		update_site_option( 'admin_email', 'unrelated@example.org' );
		$this->assertSame( 'network-notifications@example.org', get_site_option( 'admin_email' ) );

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => 'updated-network-notifications@example.org',
			)
		);
		$this->assertSame( 'updated-network-notifications@example.org', get_site_option( 'admin_email' ) );
		$this->assertFalse( revoke_super_admin( $user_id ) );

		update_site_option( 'admin_user_id', $original_admin_user_id );
		$this->assertTrue( revoke_super_admin( $user_id ) );
	}

	/**
	 * Tests that authenticating an account accepts the current network's invitation.
	 */
	public function test_network_user_invitation_activation() {
		$network       = get_network();
		$other_network = get_network( self::$different_network_id );
		$email         = 'pending-network-user@example.org';
		$display_name  = 'Pending Network User';

		// Create an invitation-only account with an invitation from the current network.
		$user_id = self::factory()->user->create(
			[
				'user_email'   => $email,
				'display_name' => $display_name,
			]
		);
		remove_user_from_blog( $user_id, (int) $network->site_id );
		$user = get_userdata( $user_id );
		$user->mark_as_created_for_network_invitation();
		$user->invite_to_network( $network );

		// Verify that the account has no site capabilities and is pending only on the current network.
		$this->assertInstanceOf( WP_User::class, $user );
		$this->assertTrue( $user->was_created_for_network_invitation() );
		$this->assertSame( array(), get_blogs_of_user( $user->ID ) );

		$invited_users = $network->users_pending_activation();
		$invited_user  = end( $invited_users );

		$this->assertSame( $email, $invited_user->user_email );
		$this->assertSame( $display_name, $invited_user->display_name );
		$this->assertSame(
			[ (int) $network->id ],
			array_map( 'intval', get_user_meta( $user->ID, WP_User::INVITED_BY_NETWORK_META_KEY ) )
		);
		$this->assertTrue( $user->has_network_invite( $network ) );
		$this->assertFalse( $user->has_network_invite( $other_network ) );
		$this->assertFalse( $network->has_user( $user ) );

		// Add a second invitation and verify that each network tracks its invitation independently.
		$user->invite_to_network( $other_network );
		$this->assertSame(
			[ (int) $network->id, (int) $other_network->id ],
			array_map( 'intval', get_user_meta( $user->ID, WP_User::INVITED_BY_NETWORK_META_KEY ) )
		);
		$this->assertTrue( $user->has_network_invite( $network ) );
		$this->assertTrue( $user->has_network_invite( $other_network ) );
		$this->assertContains( $user->ID, wp_list_pluck( $other_network->users_pending_activation(), 'ID' ) );

		// Authenticate through the current network using a one-time password.
		$one_time_password = One_Time_Password::new( HOUR_IN_SECONDS );
		update_user_meta( $user->ID, WP_User::OTP_META_ID, $one_time_password->serialize() );
		$authenticated_user = wp_signon(
			array(
				'user_login'    => $email,
				'user_password' => $one_time_password->password,
			)
		);

		// Verify that only the current network's invitation was accepted and the active account is preserved.
		$this->assertInstanceOf( WP_User::class, $authenticated_user );
		$this->assertSame( $user->ID, $authenticated_user->ID );
		$this->assertSame( array(), get_blogs_of_user( $user->ID ) );
		$this->assertSame(
			[ (int) $other_network->id ],
			array_map( 'intval', get_user_meta( $user->ID, WP_User::INVITED_BY_NETWORK_META_KEY ) )
		);
		$this->assertFalse( $user->was_created_for_network_invitation() );
		$this->assertFalse( $user->has_network_invite( $network ) );
		$this->assertTrue( $user->has_network_invite( $other_network ) );
		$this->assertTrue( $network->has_user( $user ) );
		$this->assertContains( $user->ID, $network->orphaned_user_ids() );
		$this->assertFalse( $other_network->has_user( $user ) );
		$this->assertContains( $user->ID, wp_list_pluck( $other_network->users_pending_activation(), 'ID' ) );

		// Assigning a site role removes the user's orphaned state.
		add_user_to_blog( (int) $network->site_id, $user->ID, 'subscriber' );
		$this->assertNotContains( $user->ID, $network->orphaned_user_ids() );
	}

	/**
	 * Tests that granting Super Admin status removes the user's orphaned state.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_grant_super_admin_removes_orphaned_state() {
		$network = get_network();
		$user    = self::factory()->user->create_and_get();

		remove_user_from_blog( $user->ID, (int) $network->site_id );
		$network->add_orphaned_user( $user );
		$this->assertContains( $user->ID, $network->orphaned_user_ids() );

		$this->assertTrue( grant_super_admin( $user->ID ) );
		$this->assertNotContains( $user->ID, $network->orphaned_user_ids() );

		revoke_super_admin( $user->ID );
	}

	/**
	 * Gets the ID of the site with the highest ID.
	 * @return int
	 */
	protected function _get_next_network_id() {
		global $wpdb;
		// Create an extra network, just to make sure we know the ID of the following one.
		static::factory()->network->create();
		return (int) $wpdb->get_var( 'SELECT id FROM ' . $wpdb->site . ' ORDER BY id DESC LIMIT 1' ) + 1;
	}
}
