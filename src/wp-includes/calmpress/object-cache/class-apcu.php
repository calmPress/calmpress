<?php
/**
 * Implementation of APCu object caching per cache group.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * An APCU based object cache per a caching group.
 * 
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
class APCu implements \Psr\SimpleCache\CacheInterface {

	use Psr16_Parameter_Utils;

	/**
	 * The prefix of all keys in the group cache.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
    protected string $prefix;

	/**
	 * Construct a group cache over APCu.
	 *
	 * @since 1.0.0
	 *
	 * @param APCu_Connector $connector An object which hold information about the APCu and the part
	 *                                  of it which can be used for the group cache.
	 * @param string         $sub_namespace The suffix of the general $connector prefix to be used for
	 *                                  all keys in the group cache
	 */
    public function __construct( APCu_Connector $connector, string $sub_namespace ) {
        $this->prefix = $connector->namespace() . '_' . $sub_namespace . '_';
    }

	/**
	 * Fetches a value from the cache.
	 *
	 * @since 1.0.0
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

		$exists = false;
        $ret = apcu_fetch( $this->prefix . $key, $exists );
        if ( ! $exists ) {
            return $default;
        }

		return $ret;
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * @since 1.0.0
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
		
		return apcu_store( $this->prefix . $key, $value, static::ttl_to_seconds( $ttl ) );
	}

	/**
	 * Delete an item from the cache by its unique key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The unique cache key of the item to delete.
	 *
	 * @return bool true if the value was removed, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not string
	 */
	public function delete( $key ) {
		static::throw_if_not_string_int( $key );

		return apcu_delete( $this->prefix . $key );
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if the cache was cleared, otherwise false.
	 */
	public function clear() {
		return apcu_delete( new \APCUIterator('#^' . $this->prefix . '#' ) );
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * @since 1.0.0
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

        $apcu_keys = array_map( fn( $key ) => $this->prefix . $key, $keys );

        $fetched = apcu_fetch( $apcu_keys, $exists );

		// Remove the prefix from the keys.
		$ret = [];
		foreach ( $fetched as $key => $value ) {
			$ret[ substr( $key, strlen( $this->prefix ) ) ] = $value;
		}

        // Check if all values were returned, set default for the ones that were not.
        if ( count( $keys ) != count( $ret ) ) {
            foreach ( $keys as $key ) {
                if ( ! array_key_exists( $key, $ret ) ) {
                    $ret[ $key ] = $default;
                }
            }
        }

		return $ret;
	}

	/**
	 * Persists a set of key => value pairs in the cache, with an optional TTL.
	 *
	 * @since 1.0.0
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

		$pairs = [];
		foreach ( $values as $key => $value ) {
			$pairs[ $this->prefix . $key ] = $value;
		}

		return false !== apcu_store( $pairs, null, static::ttl_to_seconds( $ttl ) );
	}

	/**
	 * Deletes multiple cache items in a single operation.
	 *
	 * @since 1.0.0
	 *
	 * @param iterable $keys A list of string-based keys to be deleted.
	 *
	 * @return bool true if all the keys were deleted, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $keys is not iterable or contain a non string key.
	 */
	public function deleteMultiple( $keys ) {
		static::throw_if_not_iterable( $keys, true );

		$keys = array_map( fn( $key ) => $this->prefix . $key, $keys );

		$ret = apcu_delete( $keys );
		if ( is_bool( $ret ) ) {
			return $ret;
		}

		return count( $keys ) === count( $ret );
	}

	/**
	 * Determines whether an item is present in the cache.
	 *
	 * NOTE: It is recommended that has() is only to be used for cache warming type purposes
	 * and not to be used within your live applications operations for get/set, as this method
	 * is subject to a race condition where your has() will return true and immediately after,
	 * another script can remove it, making the state of your app out of date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache item key.
	 *
	 * @return bool If $key has a value in the cache.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $key is not a string.
	 */
	public function has( $key ) {
		static::throw_if_not_string_int( $key );

		return apcu_exists( $this->prefix . $key );
	}
}
