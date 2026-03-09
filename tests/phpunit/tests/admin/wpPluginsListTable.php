<?php

/**
 * @group admin
 *
 * @covers WP_Plugins_List_Table
 */
class Tests_Admin_wpPluginsListTable extends WP_UnitTestCase {
	/**
	 * @var WP_Plugins_List_Table
	 */
	public $table = false;

	/**
	 * An admin user ID.
	 *
	 * @var int
	 */
	private static $admin_id;

	/**
	 * The original value of the `$s` global.
	 *
	 * @var string|null
	 */
	private static $original_s;

	/**
	 * @var array
	 */
	public $fake_plugin = array(
		'fake-plugin.php' => array(
			'Name'        => 'Fake Plugin',
			'PluginURI'   => 'https://wordpress.org/',
			'Version'     => '1.0.0',
			'Description' => 'A fake plugin for testing.',
			'Author'      => 'WordPress',
			'AuthorURI'   => 'https://wordpress.org/',
			'TextDomain'  => 'fake-plugin',
			'DomainPath'  => '/languages',
			'Network'     => false,
			'Title'       => 'Fake Plugin',
			'AuthorName'  => 'WordPress',
		),
	);

	/**
	 * Creates an admin user before any tests run and backs up the `$s` global.
	 */
	public static function set_up_before_class() {
		global $s;

		parent::set_up_before_class();

		self::$admin_id   = self::factory()->user->create(
			array(
				'role'       => 'administrator',
				'user_login' => 'test_wp_plugins_list_table',
				'user_pass'  => 'password',
				'user_email' => 'testadmin@example.com',
			)
		);
		self::$original_s = $s;
	}

	public function set_up() {
		parent::set_up();
		$this->table = _get_list_table( 'WP_Plugins_List_Table', array( 'screen' => 'plugins' ) );
	}

	/**
	 * Restores the `$s` global after each test.
	 */
	public function tear_down() {
		global $s;

		$s = self::$original_s;

		parent::tear_down();
	}

	/**
	 * @ticket 42066
	 *
	 * @covers WP_Plugins_List_Table::get_views
	 */
	public function test_get_views_should_return_views_by_default() {
		global $totals;

		$totals_backup = $totals;
		$totals        = array(
			'all'                  => 45,
			'active'               => 1,
			'recently_activated'   => 2,
			'inactive'             => 3,
			'mustuse'              => 4,
			'dropins'              => 5,
			'upgrade'              => 7,
		);

		$expected = array(
			'all'                  => '<a href="plugins.php?plugin_status=all" class="current" aria-current="page">All <span class="count">(45)</span></a>',
			'active'               => '<a href="plugins.php?plugin_status=active">Active <span class="count">(1)</span></a>',
			'recently_activated'   => '<a href="plugins.php?plugin_status=recently_activated">Recently Active <span class="count">(2)</span></a>',
			'inactive'             => '<a href="plugins.php?plugin_status=inactive">Inactive <span class="count">(3)</span></a>',
			'mustuse'              => '<a href="plugins.php?plugin_status=mustuse">Must-Use <span class="count">(4)</span></a>',
			'dropins'              => '<a href="plugins.php?plugin_status=dropins">Drop-ins <span class="count">(5)</span></a>',
			'upgrade'              => '<a href="plugins.php?plugin_status=upgrade">Update Available <span class="count">(7)</span></a>',
		);

		$actual = $this->table->get_views();
		$totals = $totals_backup;

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_status_mustuse_and_dropins() {
		return array(
			'Must-Use' => array( 'mustuse' ),
			'Drop-ins' => array( 'dropins' ),
		);
	}

	/**
	 * Tests that WP_Plugins_List_Table::prepare_items()
	 * applies 'plugins_list' filters.
	 *
	 * @ticket 57278
	 *
	 * @covers WP_Plugins_List_Table::prepare_items
	 */
	public function test_plugins_list_filter() {
		global $status, $s;

		$old_status = $status;
		$status     = 'mustuse';
		$s          = '';

		add_filter( 'plugins_list', array( $this, 'plugins_list_filter' ), 10, 1 );
		$this->table->prepare_items();
		$plugins = $this->table->items;
		remove_filter( 'plugins_list', array( $this, 'plugins_list_filter' ), 10 );

		// Restore to default.
		$status = $old_status;
		$this->table->prepare_items();

		$this->assertSame( $plugins, $this->fake_plugin );
	}

	/**
	 * Adds a fake plugin to an array of plugins.
	 *
	 * Used as a callback for the 'plugins_list' hook.
	 *
	 * @return array
	 */
	public function plugins_list_filter( $plugins_list ) {
		$plugins_list['mustuse'] = $this->fake_plugin;

		return $plugins_list;
	}
}
