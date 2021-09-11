<?php
/**
 * Implementation of Null object caching per cache group.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * An implementation of the cache interface that does nothing.
 *
 * @since 1.0.0
 * 
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
class Null_Cache implements \Psr\SimpleCache\CacheInterface {

	/**
	 * Fetches a value from the cache.
	 *
	 * As there is no real cache it always returns the default.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The $default value.
	 */
	public function get( $key, $default = null ) {
		return $default;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * Since there is no real cache, does nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param string                 $key   The key of the item to store.
	 * @param mixed                  $value The value of the item to store. Must be serializable.
	 * @param null|int|\DateInterval $ttl   The caching interval. If null is given the item will be
	 *                                      cached for a day.
	 *
	 * @return bool Always false.
	 */
	public function set( $key, $value, $ttl = null ) {
		return false;
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * Since there is no real cache, does nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool Always true.
	 */
	public function delete( $key ) {
		return true;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * Since there is no real cache, does nothing.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always true.
	 */
	public function clear() {
		return true;
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * As there is no real cache just returns the default.
	 *
	 * @since 1.0.0
	 *
	 * @param iterable $keys    A list of keys that can obtained in a single operation.
	 * @param mixed    $default Default value to return for keys that do not exist.
	 *
	 * @return iterable A list of key => value pairs. As there is no actual cache here the values will be
	 *                  the default
	 */
	public function getMultiple( $keys, $default = null ) {
		$ret = [];
		foreach ( $keys as $key ) {
			$ret[ $key ] = $default;
		}

		return $ret;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * Always fails as there is no real cache.
	 *
	 * @since 1.0.0
	 *
	 * @param iterable               $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|\DateInterval $ttl    The caching interval. If null is given the item will be
	 *                                       cached for a day.
	 *
	 * @return bool Always false.
	 */
	public function setMultiple( $values, $ttl = null ) {
		return false;
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * As there is no real cache it doew nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool Always true.
	 */
	public function deleteMultiple( $keys ) {
		return true;
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * As there is no cache, always fails.
	 * 
	 * @since 1.0.0
	 *
	 * @return bool Always false.
	 */
	public function has( $key ) {
		return false;
	}
}
