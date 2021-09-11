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
	 * Wordpress uses ttl value of 0 to indicate "forever", in that case also limit to one day.
	 *
	 * @since 1.0.0
	 * 
	 * @param null|int|\DateInterval $ttl The value to convert.
	 *
	 * @return int The interval in seconds
	 */
	protected static function ttl_to_seconds( $ttl ) : int {
		if ( null === $ttl || 0 === $ttl ) {
			return DAY_IN_SECONDS;
		}

		if ( is_int( $ttl ) ) {
			return $ttl;
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
	protected static function throw_if_not_string_int( $value ) {
		if ( ! is_string( $value ) && ! is_int( $value ) ) {
			throw new Invalid_Argument_Exception( 'key is not a string nor integer' );
		}
	}

	/**
	 * Helper that throws when a value is not iterable with valid string keys.
	 *
	 * @param mixed $keys        The keys to validate.
	 * @param bool  $check_value Indicates if the values should be check for being int or string.
	 *
	 * @throws Invalid_argument_Exception If $value is not string
	 */
	protected static function throw_if_not_iterable( $keys, bool $check_value = true ) {
		if ( ! is_iterable( $keys ) ) {
			throw new Invalid_Argument_Exception( 'parameter is not iterable' );
		}

		if ( $check_value ) {
			foreach ( $keys as $value ) {
				static::throw_if_not_string_int( $value );
			}
		}
	}
}
