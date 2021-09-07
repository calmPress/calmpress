<?php
/**
 * Implementation of in seeion memory storage for object caching.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * Implemantation of APCU based object cache per a caching group.
 * 
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
trait Psr16_Parameter_Utils {

	/**
	 * Convert a ttl parameter to an interval in seconds.
	 * 
	 * If no explicit ttl is set ( $ttl is null ) the expiry interval is set to a day.
	 *
	 * @since 1.0.0
	 * 
	 * @param null|int|\DateInterval $ttl The value to convert.
	 *
	 * @return int The interval in seconds
	 */
	protected static function ttl_to_seconds( $ttl ) : int {
		if ( is_int( $ttl ) ) {
			return $ttl;
		}

		if ( null === $ttl ) {
			return DAY_IN_SECONDS;
		}

		if ( $ttl instanceof \DateInterval ) {
			$reference = new \DateTimeImmutable;
			$endTime = $reference->add( $ttl );

			return $endTime->getTimestamp() - $reference->getTimestamp();
		}

		throw new Invalid_Argument_Exception( 'ttl is of an invalid type' );
	}
	
	/**
	 * Helper that throws when a value is not string.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @throws Invalid_argument_Exception If $value is not string
	 */
	protected static function throw_if_not_string( $value ) {
		if ( ! is_string( $value ) ) {
			throw new Invalid_Argument_Exception( 'key is not a string' );
		}
	}

	/**
	 * Helper that throws when a value is not iterable with valid string keys.
	 *
	 * @param mixed $keys      The keys to validate.
	 * @param bool  $check_key Indicates if the key should be check instead of the value.
	 *
	 * @throws Invalid_argument_Exception If $value is not string
	 */
	protected static function throw_if_not_iterable( $keys, bool $check_key = false ) {
		if ( ! is_iterable( $keys ) ) {
			throw new Invalid_Argument_Exception( 'parameter is not iterable' );
		}

		foreach ( $keys as $key => $value ) {
			if ( $check_key ) {
				static::throw_if_not_string( $key );
			} else {
				static::throw_if_not_string( $value );
			}
		}
	}
}
