<?php
/**
 * Unit tests covering Null object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\Null_Cache;

/**
 * Test that the class does the require minimum
 *
 * @since 1.0.0
 */
class WP_Test_Null_Cache extends WP_UnitTestCase {

	/**
	 * Test that get returns the default.
	 *
	 * @since 1.0.0
	 */
	public function test_get() {
		$cache = new Null_Cache();
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that set fails.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = new Null_Cache();
		$this->assertFalse( $cache->set( 'key', 'value' ) );
	}

	/**
	 * Test that has fails.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = new Null_Cache();
		$this->assertFalse( $cache->has( 'key' ) );
	}

	/**
	 * Test that delete returns true.
	 *
	 * @since 1.0.0
	 */
	public function test_delete() {
		$cache = new Null_Cache();
		$this->assertTrue( $cache->delete( 'key' ) );
	}

	/**
	 * Test that getMultiple returns default.
	 *
	 * @since 1.0.0
	 */
	public function test_getMultiple_uses_default_when_not_exist() {
		$cache = new Null_Cache();
		$this->assertSame( ['key1'=>'test', 'key2'=>'test'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that setMultiple return false.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = new Null_Cache();
		$this->assertFalse( $cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] ) );
	}

	/**
	 * Test that deleteMultiple returns true.
	 *
	 * @since 1.0.0
	 */
	public function test_deleteMultiple() {
		$cache = new Null_Cache();
		$this->assertTrue( $cache->deleteMultiple( ['key1', 'key2'] ) );
	}

	/**
	 * Test that clear deletes the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_clear() {
		$cache = new Null_Cache();
		$this->assertTrue( $cache->clear() );
	}
}