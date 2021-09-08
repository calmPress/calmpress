<?php
/**
 * Unit tests covering psr-16 parameters util trait functionality.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\object_cache\Psr16_Parameter_Utils;
use calmpress\object_cache\Invalid_Argument_Exception;

class trait_test {
	use Psr16_Parameter_Utils {
		throw_if_not_string_int as public;
		throw_if_not_iterable as public;
		ttl_to_seconds as public;
	}
}

class WP_Test_Psr16_Parameter_Utils extends WP_UnitTestCase {
	
	/**
	 * Test that the method throws for non string but not for string parameters.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $key    The value to test against.
	 * @param bool  $throws Indicate wheter an exception should be thrwon for $key.
	 * 
	 * @dataProvider keys
	 */
	public function throw_if_not_string_int( $key, bool $throws ) {

		$trait = new trait_test();
		
		$thrown = false;
		try {
			$trait->throw_if_not_string_int( $key );
		} catch ( Invalid_argument_Exception $e ) {
			$thrown = true;
		}
		$this->assertSame( $thrown , $throws );
	}

	/**
	 * Test that the method throws on non iterators, or iterators with non string
	 * value, but not on iterators with all string values.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $keys   The value to test against.
	 * @param bool  $throws Indicate wheter an exception should be thrwon for $key.
	 *
	 * @dataProvider multiple_keys
	 */
	public function test_throw_if_not_iterable( $keys, $throws ) {
		$trait = new trait_test();
		
		$thrown = false;
		try {
			$trait->throw_if_not_iterable( $keys, true );
		} catch ( Invalid_argument_Exception $e ) {
			$thrown = true;
		}
		$this->assertSame( $thrown , $throws );
	}

	public function test_ttl_to_seconds() {
		$trait = new trait_test();

		// Test default value.
		$this->assertSame( DAY_IN_SECONDS, $trait->ttl_to_seconds( null ) );

		// Test int remains the same.
		$this->assertSame( 4563, $trait->ttl_to_seconds( 4563 ) );

		// Test DateInteval conversion.
		$interval = new \DateInterval( 'PT1H' );
		$this->assertSame( HOUR_IN_SECONDS, $trait->ttl_to_seconds( $interval ) );

		// Test other types throw (just sample).
		$thrown = false;
		try {
			$trait->ttl_to_seconds( '5' );
		} catch ( Invalid_argument_Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * Keys data provider.
	 * 
	 * @since 1.0.0
	 */
	public function keys() {
		return [
			[34, true],
			[[], true],
			[4.3, true],
			[new \stdClass, true],
			[true, true],
			[null, true],
			['test', false],
		];
	}

	/**
	 * Multiple keys data provider.
	 * 
	 * @since 1.0.0
	 */
	public function multiple_keys() {
		return [
			[[[]], true],
			[[new \stdClass], true],
			[[new \stdClass, '1', '2'], true],
			[['1', new \stdClass, '2'], true],
			[['1', '2', [43]], true],
			[['1', '2', 43], false],
			[['1'], false],
		];
	}

}