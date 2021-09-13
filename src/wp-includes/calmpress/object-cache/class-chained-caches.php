<?php
/**
 * Implementation of object cache aggregation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * A cache which chains other caches caches.
 *
 * A priority order is given to the caches which compose the chain that indicates
 * the relative speed of retrival. "faster" caches are queried first for values, all caches are update
 * on value update.
 *  
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
class Chained_Caches implements \Psr\SimpleCache\CacheInterface {

	use Psr16_Parameter_Utils;

	/**
	 * The caches order by fasteest to slowest.
	 *
	 * @since 1.0.0
	 *
	 * @var \Psr\SimpleCache\CacheInterface[]
	 */
	private array $caches;

	/**
	 * Construct an chained group cache.
	 *
	 * @since 1.0.0
	 *
	 * @param \Psr\SimpleCache\CacheInterface $caches The list of caches to chain, order from
	 *                                                fast to slow.
	 */
	public function __construct( \Psr\SimpleCache\CacheInterface ...$caches ) {
		if ( empty( $caches ) ) {
			throw new \RuntimeException( 'No caches were passed' );
		}
		$this->caches = $caches;
	}

	/**
	 * Fetches a value from the cache. If the value comes from "slower" cache update the "faster"
	 * ones with it with expiry time of 5 minutes.
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string
	 */
	public function get( $key, $default = null ) {

		static::throw_if_not_string_int( $key );

		$requires_update = [];
		foreach ( $this->caches as $cache ) {
			$val = $cache->get( $key, '__NULL' );
			if ( '__NULL' !== $val ) {
				// update the faster caches if we had a miss on them.
				foreach ( $requires_update as $update_cache ) {
					$update_cache->set( $key, $val, 5 * MINUTE_IN_SECONDS );
				}
				return $val;
			}
			$requires_update[] = $cache;
		}

		return $default;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @param string                 $key   The key of the item to store.
	 * @param mixed                  $value The value of the item to store. Must be serializable.
	 * @param null|int|\DateInterval $ttl   The caching interval. If null is given the item will be
	 *                                      cached for a day.
	 *
	 * @return bool true if the value was stored, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string ot $ttl is of
	 *                                                   improper type.
	 */
	public function set( $key, $value, $ttl = null ) {
		static::throw_if_not_string_int( $key );
		$expiry = static::ttl_to_seconds( $ttl );
		
		$ret = true;
		foreach ( $this->caches as $cache ) {
			$ret = $ret && $cache->set( $key, $value, $expiry );
		}

		return $ret;
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool true if the value was removed, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string
	 */
	public function delete( $key ) {
		static::throw_if_not_string_int( $key );

		$ret = true;
		foreach ( $this->caches as $cache ) {
			$ret = $ret && $cache->delete( $key );
		}

		return $ret;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @return bool true if the cache was cleared, otherwise false.
	 */
	public function clear() {

		$ret = true;
		foreach ( $this->caches as $cache ) {
			$ret = $ret && $cache->clear();
		}

		return $ret;
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
		static::throw_if_not_iterable( $keys, true );

		$ret             = [];
		$requires_update = [];

		foreach ( $this->caches as $cache ) {
			$values = $cache->getMultiple( $keys, '__NULL' );

			// Copy non default values to the return array and create remove the key from the
			// $keys array.
			// The next cache will be queried with whatever is left in the $keys array.
			$next_keys = [];
			foreach ( $values as $key => $value ) {
				if ( '__NULL' !== $value ) {
					$ret[$key] = $value;
				} else {
					$next_keys[] = $key;
				}
			}

			// If all keys are fetched, exit the loop.
			if ( empty( $next_keys ) ) {
				break;
			}

			$requires_update[] = [
				'cache' => $cache,
				'keys'  => $next_keys,
			];

			$keys = $next_keys;
		}

		// Update the faster caches with values that were missed.
		foreach ( $requires_update as $item ) {
			$values_to_set = [];
			foreach ( $item['keys'] as $key ) {
				if ( array_key_exists( $key, $ret ) ) {
					$values_to_set[ $key ] = $ret[ $key ];
				}
			}
			$item['cache']->setMultiple( $values_to_set, 5 * MINUTE_IN_SECONDS );
		}

		// Whatever is left is filled with the default value.
		foreach ( $next_keys as $key ) {
			$ret[ $key ] = $default;
		}

		return $ret;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @param iterable               $values A list of key => value pairs for a multiple-set operation.
	 * @param null|int|\DateInterval $ttl    The caching interval. If null is given the item will be
	 *                                       cached for a day.
	 *
	 * @return bool true if all the value were stored, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $values is not iterable or contain a non string
	 *                                                   keys or $ttl is not valid type.
	 */
	public function setMultiple( $values, $ttl = null ) {
		static::throw_if_not_iterable( $values, false );
		$expiry = static::ttl_to_seconds( $ttl );

		$ret = true;
		foreach ( $this->caches as $cache ) {
			$ret = $ret && $cache->setMultiple( $values, $expiry );
		}

		return $ret;
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool true if all the keys were deleted, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is not iterable or contain a non string key.
	 */
	public function deleteMultiple( $keys ) {
		static::throw_if_not_iterable( $keys, true );

		$ret = true;
		foreach ( $this->caches as $cache ) {
			$ret = $ret && $cache->deleteMultiple( $keys );
		}

		return $ret;
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
		static::throw_if_not_string_int( $key );

		foreach ( $this->caches as $cache ) {
			if ( $cache->has( $key ) ) {
				return true;
			}
		}

		return false;
	}
}
