<?php
/**
 * Object Cache API: WP_Object_Cache class
 *
 * @package WordPress
 * @subpackage Cache
 * @since 5.4.0
 */

/**
 * Core class that implements an object cache.
 *
 * The WordPress Object Cache is used to save on trips to the database. The
 * Object Cache stores all of the cache data to memory and makes the cache
 * contents available by using a key, which is used to name and later retrieve
 * the cache contents.
 *
 * The Object Cache can be replaced by other caching mechanisms by placing files
 * in the wp-content folder which is looked at in wp-settings. If that file
 * exists, then this file will not be included.
 *
 * @since 2.0.0
 */
class WP_Object_Cache {

	/**
	 * List of global cache groups where the existance of a key with group name indicates its global.
	 *
	 * @since 3.0.0
	 *
	 * @var array
	 */
	protected array $global_groups = [];

	/**
	 * Holder for the cache per cache group. Global groups are in "top" array while
	 * for the per blog the are collected in an array per blog, and that array is in the top array.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @var array
	 */
	protected array $cache_groups = [];

	/**
	 * The blog id to use to differentiate between non-global groups in different blogs.
	 *
	 * @since calmPress 1.0.0
	 * @var int
	 */
	private int $blog_id;

	/**
	 * Fetch, while creating if does not exist yet, the memory cache for the current blog
	 * and the specified group.
	 *
	 * @param string $group The group for which to fetch the cache.
	 *
	 * @return \Psr\SimpleCache\CacheInterface
	 */
	private function group_cache( $group ) {

		// if it is a global group, it is not blog specific.
		if ( isset( $this->global_groups[ $group ] ) ) {
			if ( ! isset( $this->cache_groups[ $group ] ) ) {
				$this->cache_groups[ $group ] = new \calmpress\object_cache\Session_Memory( $group );
			} 
			return $this->cache_groups[ $group ];
		}

		// not global group, it is a per blog one.
		$blog_id     = $this->blog_id;
		$blog_groups = [];
		
		if ( ! isset( $this->cache_groups[ $blog_id ] ) ) {
			$this->cache_groups[ $blog_id ] = [];
		} else {
			$blog_groups = $this->cache_groups[ $blog_id ];
		}

		if ( ! isset( $blog_groups[ $group ] ) ) {
			$cache                                     = new \calmpress\object_cache\Session_Memory( $group . '_' . $blog_id );
			$this->cache_groups[ $blog_id ][ $group ] = $cache;
		} else {
			$cache = $blog_groups[ $group ];
		}

		return $cache;
	}

	/**
	 * Sets up object properties; PHP 5 style constructor.
	 *
	 * @since 2.0.8
	 */
	public function __construct() {
		$this->blog_id = is_multisite() ? get_current_blog_id() : 1;
	}
	
	/**
	 * Adds data to the cache if it doesn't already exist.
	 *
	 * @since 2.0.0
	 *
	 * @uses WP_Object_Cache::_exists() Checks to see if the cache already has data.
	 * @uses WP_Object_Cache::set()     Sets the data after the checking the cache
	 *                                  contents existence.
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents. Default 0 (no expiration).
	 * @return bool True on success, false if cache key and group already exist.
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( wp_suspend_cache_addition() ) {
			return false;
		}

		if ( is_int( $key ) ) {
			$key = (string) $key;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$cache = $this->group_cache( $group );

		if ( '__NULL' !== $cache->get( $key, '__NULL' ) ) {
			return false;
		}

		return $this->set( $key, $data, $group, (int) $expire );
	}

	/**
	 * Sets the list of global cache groups.
	 *
	 * @since 3.0.0
	 *
	 * @param string|string[] $groups List of groups that are global.
	 */
	public function add_global_groups( $groups ) {
		$groups = (array) $groups;
 
		$groups              = array_fill_keys( $groups, true );
		$this->global_groups = array_merge( $this->global_groups, $groups );
	}

	/**
	 * Decrements numeric cache item's value.
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key    The cache key to decrement.
	 * @param int        $offset Optional. The amount by which to decrement the item's value. Default 1.
	 * @param string     $group  Optional. The group the key is in. Default 'default'.
	 * @return int|false The item's new value on success, false on failure.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( is_int( $key ) ) {
			$key = (string) $key;
		}
		
		$cache = $this->group_cache( $group );

		$current = $cache->get( $key, '__NULL' );

		if ( '__NULL' === $current ) {
			return false;
		}

		if ( ! is_numeric( $current ) ) {
			$current = 0;
		}

		$offset = (int) $offset;

		$current -= $offset;

		if ( $current < 0 ) {
			$current = 0;
		}

		$cache->set( $key, $current );

		return $current;
	}

	/**
	 * Removes the contents of the cache key in the group.
	 *
	 * If the cache key does not exist in the group, then nothing will happen.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key        What the contents in the cache are called.
	 * @param string     $group      Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool       $deprecated Optional. Unused. Default false.
	 * @return bool False if the contents weren't deleted and true on success.
	 */
	public function delete( $key, $group = 'default', $deprecated = false ) {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}

		if ( empty( $group ) ) {
			$group = 'default';
		}

		$cache = $this->group_cache( $group );

		return $cache->delete( $key );
	}

	/**
	 * Clears the object cache of all data.
	 *
	 * @since 2.0.0
	 *
	 * @return true Always returns true.
	 */
	public function flush() {
		foreach ( $this->cache_groups as $groups ) {
			if ( ! is_array( $groups ) ) {
				// must be a global group cache.
				$groups->clear();
			} else {
				foreach ( $groups as $cache ) {
					$cache->clear();		
				}
			}
		}

		return true;
	}

	/**
	 * Retrieves the cache contents, if it exists.
	 *
	 * The contents will be first attempted to be retrieved by searching by the
	 * key in the cache group. If the cache is hit (success) then the contents
	 * are returned.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key   The key under which the cache contents are stored.
	 * @param string     $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool       $force Optional. Unused. Whether to force an update of the local cache
	 *                          from the persistent cache. Default false.
	 * @param bool       $found Optional. Whether the key was found in the cache (passed by reference).
	 *                          Disambiguates a return of false, a storable value. Default null.
	 * @return mixed|false The cache contents on success, false on failure to retrieve contents.
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}
		
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$cache = $this->group_cache( $group );

		$value = $cache->get( $key, '__NULL' );
		if ( '__NULL' !== $value ) {
			$found = true;
			if ( is_object( $value ) ) {
				return clone $value;
			} else {
				return $value;
			}
		}

		$found = false;
		return false;
	}

	/**
	 * Retrieves multiple values from the cache in one call.
	 *
	 * @since 5.5.0
	 *
	 * @param array  $keys  Array of keys under which the cache contents are stored.
	 * @param string $group Optional. Where the cache contents are grouped. Default 'default'.
	 * @param bool   $force Optional. Whether to force an update of the local cache
	 *                      from the persistent cache. Default false.
	 * @return array Array of values organized into groups.
	 */
	public function get_multiple( $keys, $group = 'default', $force = false ) {

		// Convert integers to strings.
		$keys = array_map( fn( $value ) => is_int( $value ) ? (string) $value : $value, $keys ); 

		$cache  = $this->group_cache( $group );

		// Sucks if you want to store false value and need to be able to know when they don't exist
		// but that what tests expect.
		$values = $cache->getMultiple( $keys, false );

		return $values;
	}

	/**
	 * Increments numeric cache item's value.
	 *
	 * @since 3.3.0
	 *
	 * @param int|string $key    The cache key to increment
	 * @param int        $offset Optional. The amount by which to increment the item's value. Default 1.
	 * @param string     $group  Optional. The group the key is in. Default 'default'.
	 * @return int|false The item's new value on success, false on failure.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}
		
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$cache   = $this->group_cache( $group );
		$current = $cache->get( $key, '__NULL' );

		if ( '__NULL' === $current ) {
			return false;
		}

		if ( ! is_numeric( $current ) ) {
			$current = 0;
		}

		$offset = (int) $offset;

		$current += $offset;

		if ( $current < 0 ) {
			$current = 0;
		}

		$cache->set( $key, $current );

		return $current;
	}

	/**
	 * Replaces the contents in the cache, if contents already exist.
	 *
	 * @since 2.0.0
	 *
	 * @see WP_Object_Cache::set()
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Optional. When to expire the cache contents. Default 0 (no expiration).
	 * @return bool False if not exists, true if contents were replaced.
	 */
	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}
		
		if ( empty( $group ) ) {
			$group = 'default';
		}

		$cache = $this->group_cache( $group );
		if ( '__NULL' === $cache->get( $key, '__NULL' ) ) {
			return false;
		}

		return $cache->set( $key, $data, $group, (int) $expire );
	}

	/**
	 * Sets the data contents into the cache.
	 *
	 * The cache contents are grouped by the $group parameter followed by the
	 * $key. This allows for duplicate IDs in unique groups. Therefore, naming of
	 * the group should be used with care and should follow normal function
	 * naming guidelines outside of core WordPress usage.
	 *
	 * The $expire parameter is not used, because the cache will automatically
	 * expire for each time a page is accessed and PHP finishes. The method is
	 * more for cache plugins which use files.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $key    What to call the contents in the cache.
	 * @param mixed      $data   The contents to store in the cache.
	 * @param string     $group  Optional. Where to group the cache contents. Default 'default'.
	 * @param int        $expire Not Used.
	 * @return true Always returns true.
	 */
	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}
		
		if ( empty( $group ) ) {
			$group = 'default';
		}

		if ( is_object( $data ) ) {
			$data = clone $data;
		}

		$cache = $this->group_cache( $group );
		return $cache->set( $key, $data, (int) $expire);
	}

	/**
	 * Switches the internal blog ID.
	 *
	 * This changes the blog ID used to create keys in blog specific groups.
	 *
	 * @since 3.5.0
	 * @since calmPress 1.0.0 does nothing.
	 *
	 * @param int $blog_id Blog ID.
	 */
	public function switch_to_blog( $blog_id ) {
		if ( is_multisite() ) {
			$this->blog_id = (int) $blog_id;
		}
	}

	/**
	 * Serves as a utility function to determine whether a key exists in the cache.
	 *
	 * @since 3.4.0
	 *
	 * @param int|string $key   Cache key to check for existence.
	 * @param string     $group Cache group for the key existence check.
	 * @return bool Whether the key exists in the cache for the given group.
	 */
	protected function _exists( $key, $group ) {
		if ( is_int( $key ) ) {
			$key = (string) $key;
		}
		
		$cache = $this->group_cache( $group );
		return '__NULL' !== $cache->get( $key, '__NULL' );
	}
}
