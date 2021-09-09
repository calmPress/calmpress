<?php
/**
 * Unit tests covering the APCu_Connector class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\APCu_Connector;
use calmpress\object_cache\APCu;

/**
 * Test the APCu_Connector
 *
 * APCu should not be enabled for this test.
 *
 * @since 1.0.0
 */
class WP_Test_Chained_Caches extends WP_UnitTestCase {

	/**
	 * APCu_is_avaialable should return false when APCu is not active.
	 *
	 * @since 1.0.0
	 */
	public function test_APCu_is_avaialable_when_it_is_not() {
		$this->assertFalse( APCu_Connector::APCu_is_avaialable() );
	}

	/**
	 * constructor throws run time exception  when APCu is not active.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_throws_when_apcu_not_active() {
		$thrown = false;
		try {
			new APCu_Connector( '' );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * Test APCu_is_avaialable when mocked APCu is around.
	 */
	public function test_APCu_is_avaialable() {

		// mock APCu detection to be able to create objects
		function apcu_store() {};
		$this->assertTrue( APCu_Connector::APCu_is_avaialable() );
	}

	/**
	 * Test namespace returns the namespace with which the object was created.
	 */
	public function test_namespace() {

		$connector = new APCu_Connector( 'test' );

		$this->assertSame( 'test', $connector->namespace() );
	}

	/**
	 * Test APCu global group cache creation.
	 */
	public function test_create_global_group_cache() {
		$connector = new APCu_Connector( 'test' );
		$cache     = $connector->create_global_group_cache( 'test' );
		$this->assertTrue( is_a( $cache, '\calmpress\object_cache\APCu' ) );
	}

	/**
	 * Test APCu blog group cache creation.
	 */
	public function test_create_blog_group_cache() {
		$connector = new APCu_Connector( 'test' );
		$cache     = $connector->create_blog_group_cache( 1, 'test' );
		$this->assertTrue( is_a( $cache, '\calmpress\object_cache\APCu' ) );
	}
}