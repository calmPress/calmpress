<?php
/**
 * Unit tests covering Memory object cache functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\Session_Memory;
use calmpress\object_cache\Invalid_Argument_Exception;

class WP_Test_Session_Memory extends WP_UnitTestCase {

	/**
	 * Test that the get method throws exception when used with non string keys.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $bad_key A key with non string type
	 * 
	 * @dataProvider bad_keys
	 */
	public function test_get_exception_non_string_keys( $bad_key ) {
		$cache = new Session_Memory();

		$passed = false;
		try {
			$cache->get( $bad_key );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );
	}

	/**
	 * Test that the set method throws exception when used with non string keys.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $bad_key A key with non string type
	 * 
	 * @dataProvider bad_keys
	 */
	public function test_set_exception_non_string_keys( $bad_key ) {
		$cache = new Session_Memory();

		$passed = false;
		try {
			$cache->set( $bad_key, 1 );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );
	}

	/**
	 * Test that the set method throws exception when used with non string keys.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $bad_key A key with non string type
	 * 
	 * @dataProvider bad_keys
	 */
	public function test_has_exception_non_string_keys( $bad_key ) {
		$cache = new Session_Memory();

		$passed = false;
		try {
			$cache->has( $bad_key );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );
	}

	/**
	 * Test that the set method throws exception when used with non string keys.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $bad_key A key with non string type
	 * 
	 * @dataProvider bad_keys
	 */
	public function test_delete_exception_non_string_keys( $bad_key ) {
		$cache = new Session_Memory();

		$passed = false;
		try {
			$cache->delete( $bad_key );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );
	}

	/**
	 * Test that the multiple type APIs throw exception if keys passes are not iterable.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $bad_keys A non iterator or iterator with non string keys
	 *
	 * @dataProvider bad_multiple_keys
	 */
	public function test_multiple_exception_non_iterable_keys( $bad_keys ) {
		$cache        = new Session_Memory();

		// getMultiple.
		$passed = false;
		try {
			$cache->getMultiple( $bad_keys );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );

		// setMultiple.
		$passed = false;
		try {
			if ( is_array( $bad_keys ) ) {
				$check = array_flip( $bad_keys );
			} else {
				$check = $bad_keys;
			}

			$cache->setMultiple( $check );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );

		// deleteMultiple.
		$passed = false;
		try {
			$cache->deleteMultiple( $bad_keys );
		} catch ( Invalid_argument_Exception $e ) {
			$passed = true;
		}
		$this->assertTrue( $passed );
	}

	/**
	 * Test that get returns default when key not in cache.
	 *
	 * @since 1.0.0
	 */
	public function test_get_uses_default_when_not_exist() {
		$cache = new Session_Memory();
		$this->assertSame( 'test', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that set works.
	 *
	 * @since 1.0.0
	 */
	public function test_set() {
		$cache = new Session_Memory();
		$cache->set( 'key', 'value' );
		$this->assertSame( 'value', $cache->get( 'key', 'test' ) );
	}

	/**
	 * Test that has works.
	 *
	 * @since 1.0.0
	 */
	public function test_has() {
		$cache = new Session_Memory();
		$this->assertFalse( $cache->has( 'key' ) );
		$cache->set( 'key', 'value' );
		$this->assertTrue( $cache->has( 'key' ) );
	}

	/**
	 * Test that delete works.
	 *
	 * @since 1.0.0
	 */
	public function test_delete() {
		$cache = new Session_Memory();
		$cache->set( 'key', 'value' );
		$cache->delete( 'key' );
		$this->assertFalse( $cache->has( 'key' ) );
	}

	/**
	 * Test that getMultiple returns default when key not in cache.
	 *
	 * @since 1.0.0
	 */
	public function test_getMultiple_uses_default_when_not_exist() {
		$cache = new Session_Memory();
		$this->assertSame( ['key1'=>'test', 'key2'=>'test'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that setMultiple add the values to the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_setMultiple() {
		$cache = new Session_Memory();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$this->assertSame( ['key1' => 'value1', 'key2' => 'value2'] , $cache->getMultiple( ['key1', 'key2'], 'test' ) );
	}

	/**
	 * Test that deleteMultiple deletes the values from the cache.
	 *
	 * @since 1.0.0
	 */
	public function test_deleteMultiple() {
		$cache = new Session_Memory();
		$cache->setMultiple( ['key1' => 'value1', 'key2' => 'value2'] );
		$cache->deleteMultiple( ['key1', 'key2'] );
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

	/**
	 * Bad keys data provider.
	 * 
	 * @since 1.0.0
	 */
	public function bad_keys() {
		return [
			[34],
			[[]],
			[4.3],
			[new \stdClass],
			[true],
			[null],
		];
	}

	/**
	 * Bad multiple keys data provider.
	 *
	 * Provides data which is either not iterable, or contains non string keys.
	 * 
	 * @since 1.0.0
	 */
	public function bad_multiple_keys() {
		return [
			[34],
			[[43]],
			[[43, '1', '2']],
			[['1', 43, '2']],
			[['1', '2', 43]],
		];
	}

}