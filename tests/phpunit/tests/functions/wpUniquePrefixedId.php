<?php

/**
 * Test cases for the `wp_unique_prefixed_id()` function.
 *
 * @package WordPress\UnitTests
 *
 * @since 6.4.0
 *
 * @group functions
 * @covers ::wp_unique_prefixed_id
 */
class Tests_Functions_WpUniquePrefixedId extends WP_UnitTestCase {

	/**
	 * Tests that the expected unique prefixed IDs are created.
	 *
	 * @ticket 59681
	 *
	 * @dataProvider data_should_create_unique_prefixed_ids
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param mixed $prefix   The prefix.
	 * @param array $expected The next two expected IDs.
	 */
	public function test_should_create_unique_prefixed_ids( $prefix, $expected ) {
		$id1 = wp_unique_prefixed_id( $prefix );
		$id2 = wp_unique_prefixed_id( $prefix );

		$this->assertNotSame( $id1, $id2, 'The IDs are not unique.' );
		$this->assertSame( $expected, array( $id1, $id2 ), 'The IDs did not match the expected values.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_should_create_unique_prefixed_ids() {
		return array(
			'prefix as empty string'       => array(
				'prefix'   => '',
				'expected' => array( '1', '2' ),
			),
			'prefix as (string) "0"'       => array(
				'prefix'   => '0',
				'expected' => array( '01', '02' ),
			),
			'prefix as string'             => array(
				'prefix'   => 'test',
				'expected' => array( 'test1', 'test2' ),
			),
			'prefix as string with spaces' => array(
				'prefix'   => '   ',
				'expected' => array( '   1', '   2' ),
			),
			'prefix as (string) "1"'       => array(
				'prefix'   => '1',
				'expected' => array( '11', '12' ),
			),
			'prefix as a (string) "."'     => array(
				'prefix'   => '.',
				'expected' => array( '.1', '.2' ),
			),
			'prefix as a block name'       => array(
				'prefix'   => 'core/list-item',
				'expected' => array( 'core/list-item1', 'core/list-item2' ),
			),
		);
	}

	/**
	 * Prefixes that are or will become the same should generate unique IDs.
	 *
	 * This test is added to avoid future regressions if the function's prefix data type check is
	 * modified to type juggle or check for scalar data types.
	 *
	 * @ticket 59681
	 *
	 * @dataProvider data_same_prefixes_should_generate_unique_ids
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 *
	 * @param array $prefixes The prefixes to check.
	 * @param array $expected The expected unique IDs.
	 */
	public function test_same_prefixes_should_generate_unique_ids( array $prefixes, array $expected ) {
		// Suppress E_USER_NOTICE, which will be raised when a prefix is non-string.
		$original_error_reporting = error_reporting();
		error_reporting( $original_error_reporting & ~E_USER_NOTICE );

		$ids = array();
		foreach ( $prefixes as $prefix ) {
			$ids[] = wp_unique_prefixed_id( $prefix );
		}

		// Reset error reporting.
		error_reporting( $original_error_reporting );

		$this->assertSameSets( $ids, array_unique( $ids ), 'IDs are not unique.' );
		$this->assertSameSets( $expected, $ids, 'The IDs did not match the expected values.' );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function data_same_prefixes_should_generate_unique_ids() {
		return array(
			'prefixes = empty string' => array(
				'prefixes' => array( null, true, '' ),
				'expected' => array( '1', '2', '3' ),
			),
			'prefixes = 0'            => array(
				'prefixes' => array( '0', 0, 0.0, false ),
				'expected' => array( '01', '1', '2', '3' ),
			),
			'prefixes = 1'            => array(
				'prefixes' => array( '1', 1, 1.0, true ),
				'expected' => array( '11', '1', '2', '3' ),
			),
		);
	}
}
