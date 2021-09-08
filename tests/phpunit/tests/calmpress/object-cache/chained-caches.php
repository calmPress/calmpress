<?php
/**
 * Unit tests covering chained object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\Session_Memory;
use calmpress\object_cache\Chained_Caches;

/**
 * Mock the key validation and ttl conversion functions for testing
 *
 * Since we are mocking static function, zero the state on each object creation.
 *
 * @since 1.0.0
 */
class Mock_Chained_Caches extends Chained_Caches {

	// Counter for how many times throw_if_not_string was called.
	public static int $validation_key_called = 0;

	// Counter for how many times throw_if_not_iterable was called.
	public static int $validation_iterable_called = 0;

	// Counter for how many times throw_if_not_iterable was called.
	public static int $ttl_called = 0;

	/**
	 * Zero out the counter as we start new test.
	 */
	public function __construct( \Psr\SimpleCache\CacheInterface ...$caches ) {
		parent::__construct( ...$caches );
		self::$validation_key_called = 0;
		self::$validation_iterable_called = 0;
		self::$ttl_called = 0;
	}

	protected static function throw_if_not_string_int( $key ) {
		self::$validation_key_called++;
	}

	protected static function throw_if_not_iterable( $keys, bool $check_values = true ) {
		self::$validation_iterable_called++;
	}

	protected static function ttl_to_seconds( $ttl ): int {
		self::$ttl_called++;
		return 0;
	}
}

class WP_Test_Chained_Caches extends WP_UnitTestCase {

	// Holds the first priority session memory cache.
	private \Psr\SimpleCache\CacheInterface $cache1;

	// Holds the last priority session memory cache.
	private \Psr\SimpleCache\CacheInterface $cache2;

	private function create_mock_cache(): Mock_Chained_Caches {
		$this->cache1 = new Session_Memory();
		$this->cache2 = new Session_Memory();
		return new Mock_Chained_Caches( $this->cache1, $this->cache2 );
	}

	/**
	 * Test that get returns correct values and calls key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_get() {

		// Should return $default when there are no values stored for the key.
		$cache = $this->create_mock_cache();
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
		$this->assertSame( 1, Mock_Chained_Caches::$validation_key_called );

		// Test value in second cache.
		$this->cache2->set( 'key', 'cache2' );
		$this->assertSame( 'cache2', $cache->get( 'key', 'test' ) );

		// Value in first cache should be update as well.
		$this->assertSame( 'cache2', $this->cache1->get( 'key', 'test' ) );

		// Test value in first cache should be returned instead of the one in second cache.
		$this->cache1->set( 'key', 'cache1' );
		$this->assertSame( 'cache1', $cache->get( 'key', 'test' ) );

	}

	/**
	 * Test that set populates both caches and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = $this->create_mock_cache();
		$cache->set( 'key', 'value' );
		$this->assertSame( 1, Mock_Chained_Caches::$validation_key_called );
		$this->assertSame( 1, Mock_Chained_Caches::$ttl_called );
		$this->assertSame( 'value', $cache->get( 'key', 'test' ) );

		// Test both caches have the value.
		$this->assertSame( 'value', $this->cache1->get( 'key', 'test' ) );
		$this->assertSame( 'value', $this->cache2->get( 'key', 'test' ) );
	}

	/**
	 * Test that has works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = $this->create_mock_cache();
		$this->assertFalse( $cache->has( 'key' ) );
		$this->assertSame( 1, Mock_Chained_Caches::$validation_key_called );

		// Key value in second cache.
		$this->cache2->set( 'key', 'value' );
		$this->assertTrue( $cache->has( 'key' ) );

		// Key value in both caches
		$this->cache1->set( 'key', 'value' );
		$this->assertTrue( $cache->has( 'key' ) );
	}

	/**
	 * Test that delete works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_delete() {
		$cache = $this->create_mock_cache();
		$cache->set( 'key', 'value' );
		$cache->delete( 'key' );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_Chained_Caches::$validation_key_called );
		$this->assertFalse( $cache->has( 'key' ) );
	}

	/**
	 * Test that getMultiple returns default when key not in cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_getMultiple() {
		$cache = $this->create_mock_cache();
		$this->assertSame( ['key1'=>'test', 'key2'=>'test'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
		$this->assertSame( 1, Mock_Chained_Caches::$validation_iterable_called );

		// Test value in second cache.
		$this->cache2->setMultiple( ['key1'=>'test1', 'key2'=>'test2'] );
		$this->assertSame( ['key1'=>'test1', 'key2'=>'test2'], $cache->getMultiple( ['key1', 'key2'] ) );

		// Test that values in first cache were update as the result of the get.
		$this->assertSame( ['key1'=>'test1', 'key2'=>'test2'], $this->cache1->getMultiple( ['key1', 'key2'] ) );

		// Test value in first cache should be returned instead of the one in second cache.
		$this->cache1->setMultiple( ['key1'=>'test12', 'key2'=>'test22'] );
		$this->assertSame( ['key1'=>'test12', 'key2'=>'test22'], $cache->getMultiple( ['key1', 'key2'] ) );

		// Test that when a value do not exist in first cache it is being seeked in second.
		$this->cache1->delete( 'key1' );
		$this->assertEqualsCanonicalizing( ['key1'=>'test1', 'key2'=>'test22'], $cache->getMultiple( ['key1', 'key2'] ) );

		// ... and the first cache was updated as the result of the get.
		$this->assertSame( 'test1', $this->cache1->get( 'key1' ) );

	}

	/**
	 * Test that setMultiple add the values to all caches and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = $this->create_mock_cache();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$this->assertSame( 1, Mock_Chained_Caches::$validation_iterable_called );
		$this->assertSame( 1, Mock_Chained_Caches::$ttl_called );
		$this->assertEqualsCanonicalizing( ['key1' => 'value1', 'key2' => 'value2'] , $this->cache1->getMultiple( ['key1', 'key2'], 'test' ) );
		$this->assertEqualsCanonicalizing( ['key1' => 'value1', 'key2' => 'value2'] , $this->cache2->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that deleteMultiple deletes the values from the caches and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_deleteMultiple() {
		$cache = $this->create_mock_cache();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->deleteMultiple( ['key1', 'key2'] );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_Chained_Caches::$validation_iterable_called );
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}

	/**
	 * Test that clear deletes the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_clear() {
		$cache = $this->create_mock_cache();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->clear();
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}
}