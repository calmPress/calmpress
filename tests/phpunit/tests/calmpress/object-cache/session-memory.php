<?php
/**
 * Unit tests covering Memory object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\Session_Memory;

/**
 * Mock the key validation functions for testing
 *
 * Since we are mocking static function, zero the state on each object creation.
 *
 * @since 1.0.0
 */
class Mock_Session_Memory extends Session_Memory {

	// Conter for how many times throw_if_not_string was called.
	public static int $validation_key_called = 0;

	// Conter for how many times throw_if_not_iterable was called.
	public static int $validation_iterable_called = 0;

	/**
	 * Zero out the counter as we start new test.
	 */
	public function __construct() {
		self::$validation_key_called = 0;
		self::$validation_iterable_called = 0;
	}

	protected static function throw_if_not_string( $key ) {
		self::$validation_key_called++;
	}

	protected static function throw_if_not_iterable( $keys ) {
		self::$validation_iterable_called++;
	}
}

class WP_Test_Session_Memory extends WP_UnitTestCase {

	/**
	 * Test that get returns default when key not in cache and calls key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_get_uses_default_when_not_exist() {
		$cache = new Mock_Session_Memory();
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
		$this->assertSame( 1, Mock_Session_Memory::$validation_key_called );
	}

	/**
	 * Test that set works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = new Mock_Session_Memory();
		$cache->set( 'key', 'value' );
		$this->assertSame( 1, Mock_Session_Memory::$validation_key_called );
		$this->assertSame( 'value', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that has works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = new Mock_Session_Memory();
		$this->assertFalse( $cache->has( 'key' ) );
		$this->assertSame( 1, Mock_Session_Memory::$validation_key_called );
		$cache->set( 'key', 'value' );
		$this->assertTrue( $cache->has( 'key' ) );
	}

	/**
	 * Test that delete works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_delete() {
		$cache = new Mock_Session_Memory();
		$cache->set( 'key', 'value' );
		$cache->delete( 'key' );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_Session_Memory::$validation_key_called );
		$this->assertFalse( $cache->has( 'key' ) );
	}

	/**
	 * Test that getMultiple returns default when key not in cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_getMultiple_uses_default_when_not_exist() {
		$cache = new Mock_Session_Memory();
		$this->assertSame( ['key1'=>'test', 'key2'=>'test'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
		$this->assertSame( 1, Mock_Session_Memory::$validation_iterable_called );
	}

	/**
	 * Test that setMultiple add the values to the cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = new Mock_Session_Memory();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$this->assertSame( 1, Mock_Session_Memory::$validation_iterable_called );
		$this->assertSame( ['key1' => 'value1', 'key2' => 'value2'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that deleteMultiple deletes the values from the cache  and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_deleteMultiple() {
		$cache = new Mock_Session_Memory();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->deleteMultiple( ['key1', 'key2'] );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_Session_Memory::$validation_iterable_called );
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}

	/**
	 * Test that clear deletes the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_clear() {
		$cache = new Session_Memory();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->clear();
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}
}