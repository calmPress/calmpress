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
 * Implemantation ov in session memory based object cached storage. This cache will exists only
 * during the HTTP session and will be destroyed automatically when it ends.
 * 
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
class Session_Memory implements \Psr\SimpleCache\CacheInterface {

	/**
	 * LHolder of the values cache where the index is the key and the value is the value.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	private array $cache = [];

	/**
	 * Helper that throws when a value is not string.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @throws Invalid_argument_Exception If $value is not string
	 */
	private static function throw_if_not_string( $value ) {
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
	private static function throw_if_not_iterable( $keys, bool $check_key = false ) {
		if ( ! is_iterable( $keys ) ) {
			throw new Invalid_Argument_Exception( 'parameter is not iterable' );
		}

		foreach ( $keys as $key => $value ) {
			if ( $check_key ) {
				self::throw_if_not_string( $key );
			} else {
				self::throw_if_not_string( $value );
			}
		}
	}

	/**
	 * Fetches a value from the cache.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string
	 */
	public function get( $key, $default = null ) {

		static::throw_if_not_string( $key );

		if ( array_key_exists( $key, $this->cache ) ) {
			return $this->cache[ $key ];
		}

		return $default;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @param string                 $key   The key of the item to store.
	 * @param mixed                  $value The value of the item to store. Must be serializable.
	 * @param null|int|\DateInterval $ttl   As the cache is unlikely to persist more than a second
	 *                                      this value is ignored.
	 *
	 * @return bool True indicating success as this operation can fail only if PHP ran out of memory.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string
	 */
	public function set( $key, $value, $ttl = null ) {
		static::throw_if_not_string( $key );
		
		$this->cache[ $key ] = $value;

		return true;
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool True indicating the item was successfully removed.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string
	 */
	public function delete( $key ) {
		static::throw_if_not_string( $key );

		unset( $this->cache[ $key ] );

		return true;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool True as it should alway suceed.
	 */
	public function clear() {
		$this->cache = [];

		return true;
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * @param iterable $keys    A list of keys that can obtained in a single operation.
	 * @param mixed    $default Default value to return for keys that do not exist.
	 *
	 * @return iterable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is not iterable or contain a non string key.
	 */
	public function getMultiple( $keys, $default = null ) {
		static::throw_if_not_iterable( $keys );

		$ret = [];
		foreach ( $keys as $key ) {
			$ret[ $key ] = $this->get( $key, $default );
		}

		return $ret;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @param iterable               $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|\DateInterval $ttl    As the cache is unlikely to persist more than a second
	 *                                       this value is ignored.
	 *
	 * @return bool True indicating success as this operation can fail only if PHP ran out of memory.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $values is not iterable or contain a non string key.
	 */
	public function setMultiple( $values, $ttl = null ) {
		static::throw_if_not_iterable( $values, true );

		foreach ( $values as $key => $value ) {
			$this->set( $key, $value );
		}

		return true;
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool True indicating the item was successfully removed.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is not iterable or contain a non string key.
	 */
	public function deleteMultiple( $keys ) {
		static::throw_if_not_iterable( $keys );

		foreach ( $keys as $key ) {
			$this->delete( $key );
		}

		return true;
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * NOTE: It is recommended that has() is only to be used for cache warming type purposes
	 * and not to be used within your live applications operations for get/set, as this method
	 * is subject to a race condition where your has() will return true and immediately after,
	 * another script can remove it, making the state of your app out of date.
	 *
	 * @param string $key The cache item key.
	 *
	 * @return bool If $key has a value in the cache.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not a string.
	 */
	public function has( $key ) {
		static::throw_if_not_string( $key );

		return array_key_exists( $key, $this->cache );
	}
}
