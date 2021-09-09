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

// Protect against redefinition as this code is also used in the apcu specific testing.
if ( ! function_exists( 'apcu_enabled' ) ) {
	global $calmpress_apcu_enabled;
	$calmpress_apcu_enabled = false;

	function apcu_enabled(): bool {
		global $calmpress_apcu_enabled;

		return $calmpress_apcu_enabled;
	}
}

/**
 * Test the APCu_Connector
 *
 * APCu should not be enabled for this test.
 *
 * @since 1.0.0
 */
class WP_Test_APCu_Connector extends WP_UnitTestCase {

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
		global $calmpress_apcu_enabled;
		$calmpress_apcu_enabled = true;

		$this->assertTrue( APCu_Connector::APCu_is_avaialable() );
		$calmpress_apcu_enabled = false;
	}

	/**
	 * Test namespace returns the namespace with which the object was created.
	 */
	public function test_namespace() {
		global $calmpress_apcu_enabled;
		$calmpress_apcu_enabled = true;

		$connector = new APCu_Connector( 'test' );

		$this->assertSame( 'test', $connector->namespace() );
		$calmpress_apcu_enabled = false;
	}

	/**
	 * Test APCu global group cache creation.
	 */
	public function test_create_cache() {
		global $calmpress_apcu_enabled;
		$calmpress_apcu_enabled = true;

		$connector = new APCu_Connector( 'test' );
		$cache     = $connector->create_cache( 'test' );
		$this->assertTrue( is_a( $cache, '\calmpress\object_cache\APCu' ) );
		$calmpress_apcu_enabled = false;
	}
}