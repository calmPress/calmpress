<?php

/**
 * Tests for WP_Text_Diff_Renderer_Table.
 *
 * @group diff
 */
class Tests_Diff_WpTextDiffRendererTable extends WP_UnitTestCase {
	/**
	 * @var WP_Text_Diff_Renderer_Table
	 */
	private $diff_renderer_table;

	public static function set_up_before_class() {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-includes/Text/Diff/Renderer.php';
		require_once ABSPATH . 'wp-includes/class-wp-text-diff-renderer-table.php';
	}

	public function set_up() {
		parent::set_up();
		$this->diff_renderer_table = new WP_Text_Diff_Renderer_Table();
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__get()
	 *
	 * @param string $property_name Property name to get.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_get_compat_fields( $property_name, $expected ) {
		$this->assertSame( $expected, $this->diff_renderer_table->$property_name );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__set()
	 *
	 * @param string $property_name Property name to set.
	 */
	public function test_should_set_compat_fields( $property_name ) {
		$value                                     = uniqid();
		$this->diff_renderer_table->$property_name = $value;

		$this->assertSame( $value, $this->diff_renderer_table->$property_name );
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__isset()
	 *
	 * @param string $property_name Property name to check.
	 * @param mixed $expected       Expected value.
	 */
	public function test_should_isset_compat_fields( $property_name, $expected ) {
		$actual = isset( $this->diff_renderer_table->$property_name );
		if ( is_null( $expected ) ) {
			$this->assertFalse( $actual );
		} else {
			$this->assertTrue( $actual );
		}
	}

	/**
	 * @dataProvider data_compat_fields
	 * @ticket 58898
	 *
	 * @covers WP_Text_Diff_Renderer_Table::__unset()
	 *
	 * @param string $property_name Property name to unset.
	 */
	public function test_should_unset_compat_fields( $property_name ) {
		unset( $this->diff_renderer_table->$property_name );
		$this->assertFalse( isset( $this->diff_renderer_table->$property_name ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_compat_fields() {
		return array(
			'_show_split_view'     => array(
				'property_name' => '_show_split_view',
				'expected'      => true,
			),
			'inline_diff_renderer' => array(
				'property_name' => 'inline_diff_renderer',
				'expected'      => 'WP_Text_Diff_Renderer_inline',
			),
			'_diff_threshold'      => array(
				'property_name' => '_diff_threshold',
				'expected'      => 0.6,
			),
		);
	}
}
