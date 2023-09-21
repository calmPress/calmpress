<?php
/**
 * Unit tests covering APCu object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\Session_Memory;
use calmpress\object_cache\APCu;

// Mocks for APCu API

// Store the Session_Memory cache used to moce the APCu cache
global $APCu_Mock_Cache;

// The expected prefix of all keys.
global $expected_prefix;

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
 * Validate the keys to have the expected prefix, throx runtime exception if not.
 * 
 * @since 1.0.0
 *
 * @param $keys string|string[] The keys to validate.
 */
function apcu_validate_prefix( $keys ) {
	global $expected_prefix;

	if ( ! is_array( $keys ) ) {
		$keys = array( $keys );
	}

	foreach ( $keys as $key ) {
		if (  0 !== strpos( $key, $expected_prefix ) ) {
			throw \RuntimeException( 'key do not have proper prefix ' . $key );
		}
	}
}

/**
 * Mock the apcu_store function. It operates with two mode, one in which $keys is array
 * in which the requested behaviour is of setMultiple and when it is not the behaviour is of set.
 * 
 * @since 1.0.0
 *
 * @param string|string[] $keys If a string the key to store, if array it consist of key => value
 *                              to store.
 * @param mixed $value          The value to store if $keys is a string.
 * @param int   $ttl            The TTL for the entry, 0 indicates forever.
 *
 * @return bool                 true on success, false on fail.
 */
function apcu_store( $keys, $values, $ttl ) {
	global $APCu_Mock_Cache;

	if ( ! is_array( $keys ) ) {
		apcu_validate_prefix( $keys );
		return $APCu_Mock_Cache->set( $keys, $values, $ttl );
	} else {
		apcu_validate_prefix( array_keys( $keys ) );
		return $APCu_Mock_Cache->setMultiple( $keys, $ttl );
	}
}

/**
 * Mock the apcu_fetch function. It operates with two mode, one in which $keys is array
 * in which the requested behaviour is of getMultiple and when it is not the behaviour is of get.
 *
 * @since 1.0.0
 *
 * @param string|string[] $keys  The key(s) for which to fetch the value(s).
 * @param bool            $exist A reference to a variable that will be set to true if the key
 *                               actually had a value in the cache, otherwise will be set to false.
 *
 * @return mixed          The value fetched if $keys is a string and found, otherwise an array of
 *                        key => value for all the keys for which an entry was found. 
 */
function apcu_fetch( $keys, &$exist ) {
	global $APCu_Mock_Cache;

	apcu_validate_prefix( $keys );
	if ( ! is_array( $keys ) ) {
		$ret   = $APCu_Mock_Cache->get( $keys, '__NULL' );
		$exist = ( '__NULL' !== $ret );
		return $ret;
	} else {
		$ret     = [];
		$fetched = $APCu_Mock_Cache->getMultiple( $keys, '__NULL' );
		foreach ( $fetched as $key => $value ) {
			if ( '__NULL' !== $value ) {
				$ret[ $key ] = $value;
			}
		}

		return $ret;
	}
}

/**
 * Mock the apcu_delete function. It operates with two mode, one in which $keys is array
 * in which the requested behaviour is of deleteMultiple and when it is not the behaviour is of delete.
 *
 * @since 1.0.0
 *
 * @param string|string[] $keys The key(s) to delete.
 */
function apcu_delete( $keys ) {
	global $APCu_Mock_Cache;

	apcu_validate_prefix( $keys );
	if ( ! is_array( $keys ) ) {
		return $APCu_Mock_Cache->delete( $keys );
	} else {
		return $APCu_Mock_Cache->deleteMultiple( $keys );
	}
}

/**
 * Mock the apcu_exists function.
 *
 * @since 1.0.0
 *
 * @param string $key The key to check.
 *
 * @return true if key exist in the cache, otherwise false.
 */
function apcu_exists( $key ) {
	global $APCu_Mock_Cache;

	apcu_validate_prefix( $key );
	return $APCu_Mock_Cache->has( $key );
}

/**
 * Mock APCu connector that returns Mock_ACPu caches.
 *
 * @since 1.0.0
 */
class Mock_APCu_Connector extends \calmpress\apcu\APCu {
    public  function create_cache( string $namespace ) : APCu {
        return new Mock_APCu( $this, $namespace );
    }
}

/**
 * Mock the key validation and ttl conversion functions for testing
 *
 * Since we are mocking static function, zero the state on each object creation.
 *
 * @since 1.0.0
 */
class Mock_APCu extends APCu {

	// Counter for how many times throw_if_not_string was called.
	public static int $validation_key_called = 0;

	// Counter for how many times throw_if_not_iterable was called.
	public static int $validation_iterable_called = 0;

	// Counter for how many times throw_if_not_iterable was called.
	public static int $ttl_called = 0;

	/**
	 * Zero out the cache and counters as we start new test, set the expected prefix to match
	 * the cache prefix.
	 */
	public function __construct( \calmpress\apcu\APCu $connector, string $sub_namespace ) {
		parent::__construct( $connector, $sub_namespace );
		global $APCu_Mock_Cache;
		global $expected_prefix;

		$APCu_Mock_Cache = new Session_Memory();
		$expected_prefix = $this->prefix;
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
 * Tests for the APCu based implementation of objecy cache.
 *
 * @since 1.0.0
 */
class WP_Test_APCu extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		global $calmpress_apcu_enabled;
		$calmpress_apcu_enabled = true;
	}

	public function tearDown() : void {
		parent::tearDown();

		global $calmpress_apcu_enabled;
		$calmpress_apcu_enabled = false;
	}

	/**
	 * Create a mock APCu cache.
	 *
	 * @since 1.0.0
	 */
	private function create_mock_cache(): Mock_APCu {
		$connector = new Mock_APCu_Connector( 'test' );
		return $connector->create_cache( 'sub' );
	}

	/**
	 * Test that get returns defualt when key do not exists and calls key validation.
	 * Getting an existing key is actually tested as part of test_set.
	 *
	 * @since 1.0.0
	 */
	public function test_get() {

		// Should return $default when there are no values stored for the key.
		$cache = $this->create_mock_cache();
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
		$this->assertSame( 1, Mock_APCu::$validation_key_called );
	}

	/**
	 * Test that set populates both caches and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = $this->create_mock_cache();
		$cache->set( 'key', 'value' );
		$this->assertSame( 1, Mock_APCu::$validation_key_called );
		$this->assertSame( 1, Mock_APCu::$ttl_called );
		$this->assertSame( 'value', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that has works and calles key validation.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = $this->create_mock_cache();
		$this->assertFalse( $cache->has( 'key' ) );
		$this->assertSame( 1, Mock_APCu::$validation_key_called );

		$cache->set( 'key', 'value' );
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
		$this->assertSame( 2, Mock_APCu::$validation_key_called );
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
		$this->assertSame( 1, Mock_APCu::$validation_iterable_called );

		$cache->setMultiple( ['key1'=>'test1', 'key2'=>'test2'] );
		$this->assertSame( ['key1'=>'test1', 'key2'=>'test2'], $cache->getMultiple( ['key1', 'key2'] ) );
	}

	/**
	 * Test that setMultiple add the values to all caches and validates keys.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = $this->create_mock_cache();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$this->assertSame( 1, Mock_APCu::$validation_iterable_called );
		$this->assertSame( 1, Mock_APCu::$ttl_called );
		$this->assertSame( ['key1' => 'value1', 'key2' => 'value2'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
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
		$this->assertSame( 2, Mock_APCu::$validation_iterable_called );
		$this->assertFalse( $cache->has( 'key1' ) );
		$this->assertFalse( $cache->has( 'key2' ) );
	}

}