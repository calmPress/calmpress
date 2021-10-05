<?php
/**
 * Unit tests covering PHP file object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\PHP_File;

/**
 * Mock the key validation functions for testing
 *
 * Since we are mocking static function, zero the state on each object creation.
 *
 * @since 1.0.0
 */
class Mock_PHP_File extends PHP_File {

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

	/**
	 * Helper to expose the file path of a file which will be used for a key.
	 * 
	 * @since 1.0.0;
	 */
	public function file_for_key( string $key ) {
		return parent::key_to_file( $key );
	}

	/**
	 * Mock opcache to be enabled.
	 */
	public static function api_is_available():bool {
		return true;
	}
}

/**
 * Mock the function opcache_invalidate to avoid errors and see if the correct file
 * is invalidated.
 */
function opcache_invalidate( string $file ) {
	global $opcache_invalidate_file;

	$opcache_invalidate_file = $file;
}

/**
 * Tests for the PHP_File class.
 * 
 * @since 1.0.0
 */
class WP_Test_PHP_File extends WP_UnitTestCase {

	/**
	 * Test that get returns default when key not in cache and calls key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_get_uses_default_when_not_exist_or_not_valid() {
		$cache = new Mock_PHP_File( 'test/sub' );

		// File do not exist after initialization.
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
		$this->assertSame( 1, Mock_PHP_File::$validation_key_called );

		// File contain "garbage".
		file_put_contents( $cache->file_for_key( 'key' ), 'garbage' );
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );

		// File contain php with syntax error.
		file_put_contents( $cache->file_for_key( 'key' ), '<?php return [' .( time() + 1000 ) . ', 45]' );
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );

		// File contains expired value.
		file_put_contents( $cache->file_for_key( 'key' ), '<?php return [' . ( time() - 1000 ) . ', 45];' );
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that files created with a value of a specific type can be properly read.
	 *
	 * @since 1.0.0
	 */
	public function test_get_with_some_types() {
		$cache = new Mock_PHP_File( 'test/sub' );

		// File contains integer.
		file_put_contents( $cache->file_for_key( 'key' ), '<?php return [' . ( time() + 1000 ) . ', 45];' );
		$this->assertSame( 45, $cache->get( 'key', 'test' ) );

		// File contains string.
		file_put_contents( $cache->file_for_key( 'key' ), '<?php return [' . ( time() + 1000 ) . ', "str"];' );
		$this->assertSame( 'str', $cache->get( 'key', 'test' ) );

		// File contains array.
		file_put_contents( $cache->file_for_key( 'key' ), '<?php return [' . ( time() + 1000 ) . ', [1, "str"]];' );
		$this->assertSame( [1, 'str'], $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that set works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = new Mock_PHP_File( 'test/sub' );
		$cache->set( 'key', 'value' );
		$this->assertSame( 1, Mock_PHP_File::$validation_key_called );
		$this->assertSame( 1, Mock_PHP_File::$ttl_called );
		$this->assertSame( 'value', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that has works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = new Mock_PHP_File( 'test/sub' );
		@unlink( $cache->file_for_key( 'key' ) );
		$this->assertFalse( $cache->has( 'key' ) );

		// two validation calls, one for the delete and one for the has.
		$this->assertSame( 2, Mock_PHP_File::$validation_key_called );
		$cache->set( 'key', 'value' );
		$this->assertTrue( $cache->has( 'key' ) );
	}

	/**
	 * Test that delete works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_delete() {
		global $opcache_invalidate_file;

		$cache = new Mock_PHP_File( 'test/sub' );
		$cache->set( 'key', 'value' );
		$cache->delete( 'key' );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_PHP_File::$validation_key_called );
		$this->assertFalse( $cache->has( 'key' ) );
		$this->assertSame( $cache->file_for_key( 'key' ), $opcache_invalidate_file );
	}

	/**
	 * Test that getMultiple returns default when key not in cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_getMultiple_uses_default_when_not_exist() {
		$cache = new Mock_PHP_File( 'test/sub' );
		$this->assertSame( ['key1'=>'test', 'key2'=>'test'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
		$this->assertSame( 1, Mock_PHP_File::$validation_iterable_called );
	}

	/**
	 * Test that setMultiple add the values to the cache and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = new Mock_PHP_File( 'test/sub' );
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$this->assertSame( 1, Mock_PHP_File::$validation_iterable_called );
		$this->assertSame( 1, Mock_PHP_File::$ttl_called );
		$this->assertSame( ['key1' => 'value1', 'key2' => 'value2'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that deleteMultiple deletes the values from the cache  and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_deleteMultiple() {
		$cache = new Mock_PHP_File( 'test/sub' );
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->deleteMultiple( ['key1', 'key2'] );

		// validation called twice, first for set, than for delete.
		$this->assertSame( 2, Mock_PHP_File::$validation_iterable_called );
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}

	/**
	 * Test that clear deletes the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_clear() {
		$cache = new Mock_PHP_File( 'test/sub' );
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->clear();
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}

	/**
	 * Test that the function properly detects when the relevant directory is not writable.
	 *
	 * @since 1.0.0
	 */
	public function test_is_available() {
		
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$this->markTestSkipped( 'Manipulating windows access permissions is too crazy');
		}

		// Test when the cache directory is writable.
		chmod( Mock_File::CACHE_ROOT_DIR, 777 );
		$this->assertTrue( Mock_File::is_available() );

		// Test when the cache directory is writable.
		chmod( Mock_File::CACHE_ROOT_DIR, 077 );
		$this->assertFalse( Mock_File::is_available() );

		chmod( Mock_File::CACHE_ROOT_DIR, 777 );
	}

	/**
	 * Test that the constructor fails when directory nor writable.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor_fails_on_not_writable() {
		
		if ( PHP_OS_FAMILY === 'Windows' ) {
			$this->markTestSkipped( 'Manipulating windows access permissions is too crazy');
		}

		// Objects can be created when directories are writable.
		chmod( Mock_File::CACHE_ROOT_DIR, 777 );

		$thrown = false;
		try {
			new MocK_File( 'testi' );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}
		$this->assertFalse( $thrown );

		// Objects can not be created when directories are not writable.
		chmod( Mock_File::CACHE_ROOT_DIR, 077 );

		$thrown = false;
		try {
			new MocK_File( 'testi' );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}
		$this->assertFalse( $thrown );

		chmod( Mock_File::CACHE_ROOT_DIR, 777 );
	}

}