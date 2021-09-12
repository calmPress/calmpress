<?php
/**
 * Unit tests covering file object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\File;

/**
 * Mock the key validation functions for testing
 *
 * Since we are mocking static function, zero the state on each object creation.
 *
 * @since 1.0.0
 */
class Mock_File extends File {

	// Conter for how many times throw_if_not_string was called.
	public static int $validation_key_called = 0;

	// Conter for how many times throw_if_not_iterable was called.
	public static int $validation_iterable_called = 0;

	// Counter for how many times throw_if_not_iterable was called.
	public static int $ttl_called = 0;

	/**
	 * Zero out the counter as we start new test.
	 */
	public function __construct( string $path) {
		parent::__construct( $path );
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

/**
 * Tests for the File class.
 * 
 * @since 1.0.0
 */
class WP_Test_File extends WP_UnitTestCase {

	/**
	 * Test that get returns default when key not in cache and calls key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_get_uses_default_when_not_exist_or_not_valid() {
		$cache = new Mock_File( 'test/sub' );

		// File do not exist after initialization.
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
		$this->assertSame( 1, Mock_File::$validation_key_called );

		// File contain "garbage".
		file_put_contents( File::CACHE_ROOT_DIR . 'test/sub/key.dat', 'garbage' );
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );

		// File contains expired value.
		$data = serialize( [ time() - 1000, 45] );
		file_put_contents( File::CACHE_ROOT_DIR . 'test/sub/key.dat', $data );
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that files created with a value of a specific type can be properly read.
	 *
	 * @since 1.0.0
	 */
	public function test_get_with_some_types() {
		$cache = new Mock_File( 'test/sub' );

		// File contains integer.
		$data = serialize( [ time() + 1000, 45] );
		file_put_contents( File::CACHE_ROOT_DIR . 'test/sub/key.dat', $data );
		$this->assertSame( 45, $cache->get( 'key', 'test' ) );

		// File contains string.
		$data = serialize( [ time() + 1000, 'str'] );
		file_put_contents( File::CACHE_ROOT_DIR . 'test/sub/key.dat', $data );
		$this->assertSame( 'str', $cache->get( 'key', 'test' ) );

		// File contains array.
		$data = serialize( [ time() + 1000, [1, 'str'] ] );
		file_put_contents( File::CACHE_ROOT_DIR . 'test/sub/key.dat', $data );
		$this->assertSame( [1, 'str'], $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that set works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = new Mock_File( 'test/sub' );
		$cache->set( 'key', 'value' );
		$this->assertSame( 1, Mock_File::$validation_key_called );
		$this->assertSame( 1, Mock_File::$ttl_called );
		$this->assertSame( 'value', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that has works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = new Mock_File( 'test/sub' );
		@unlink( File::CACHE_ROOT_DIR . 'test/sub/key.dat' );
		$this->assertFalse( $cache->has( 'key' ) );

		// two validation calls, one for the delete and one for the has.
		$this->assertSame( 2, Mock_File::$validation_key_called );
		$cache->set( 'key', 'value' );
		$this->assertTrue( $cache->has( 'key' ) );
	}

	/**
	 * Test that delete works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_delete() {

		$cache = new Mock_File( 'test/sub' );
		$cache->set( 'key', 'value' );
		$cache->delete( 'key' );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_File::$validation_key_called );
		$this->assertFalse( $cache->has( 'key' ) );
	}

	/**
	 * Test that getMultiple returns default when key not in cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_getMultiple_uses_default_when_not_exist() {
		$cache = new Mock_File( 'test/sub' );
		$this->assertSame( ['key1'=>'test', 'key2'=>'test'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
		$this->assertSame( 1, Mock_File::$validation_iterable_called );
	}

	/**
	 * Test that setMultiple add the values to the cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = new Mock_File( 'test/sub' );
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$this->assertSame( 1, Mock_File::$validation_iterable_called );
		$this->assertSame( 1, Mock_File::$ttl_called );
		$this->assertSame( ['key1' => 'value1', 'key2' => 'value2'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that deleteMultiple deletes the values from the cache  and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_deleteMultiple() {
		$cache = new Mock_File( 'test/sub' );
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->deleteMultiple( ['key1', 'key2'] );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_File::$validation_iterable_called );
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}

	/**
	 * Test that clear deletes the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_clear() {
		$cache = new Mock_File( 'test/sub' );
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->clear();
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}
}