<?php
/**
 * Implementation of File based object caching per cache group.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * A file based object cache per a caching group.
 *
 * The cached values will be stored in files which will incule a json representation of
 * an array for which the first element is a unix expiry time and the second is the value.
 *
 * @since 1.0.0
 * 
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
class File implements \Psr\SimpleCache\CacheInterface {

	/**
	 * The root directory in which the cache files are located.
	 */
	public const CACHE_ROOT_DIR = WP_CONTENT_DIR . '/.private/object-cache/file/';

	use Psr16_Parameter_Utils;

	/**
	 * The path to the root directory of the files holding the cache.
	 *
	 * @since 1.0.0
	 *
	 * @var string.
	 */
	protected string $root_dir;

	/**
	 * Construct a group cache over a file cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_directory The path of the directory in which to store the relevant file
	 *                                relative to the general PHP file object caching root.
	 *
	 * @throws \RuntimeException if cache path is not writable or can not be created.
	 */
	public function __construct( string $cache_directory ) {
		$this->set_cache_root_dir( self::CACHE_ROOT_DIR . $cache_directory );
	}

	/**
	 * Helper function to create the cache directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $dir The path of the directory in which to store the relevant files.
	 *
	 * @throws \RuntimeException if cache path is not writable or can not be created.
	 */
	protected function set_cache_root_dir( string $dir ) {

		\calmpress\utils\ensure_dir_exists( $dir );

		$this->root_dir = $dir . '/';
	}

	/**
	 * Helper function to read, parse and execute the file containing the cached value of a key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file The file from which the value should be fetched.
	 *
	 * @return array An empty array if the file do not exist or can't be "run". Otherwise
	 *               the array contained in the file in which the first item is an integer with expiry
	 *               time and the second is a serialized value.
	 */
	protected static function read_file( string $file ): array {
		$ret = [];
		if ( is_file( $file ) ) {
			$string = file_get_contents( $file );
			$ret = @unserialize( $string );
			if ( ! is_array( $ret ) ) {
				// the file supposed to contain an array, but it isn't so treat it like
				// curropted file.
				$ret = [];
				static::purge_file( $file );
			}
		}

		return $ret;
	}

	/**
	 * Helper function to delete a file and remove it from the cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file The file from which the value should be fetched.
	 */
	protected static function purge_file( string $file ) {
		unlink( $file );
	}

	/**
	 * Utility to convert keys to file names, sanitizing them for spaces and
	 * other unfreindly characters.
	 * 
	 * To make it simple even if it means a less readable file names, use md5 of the key
	 * as the file name if bad characters are detected.
	 * 
	 * bad characters - space, forward and back slashes, :, <, >, |, ?, *
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The key to get the file path for.
	 *
	 * @return string A path to the file that stores the key related value.
	 */
	protected function key_to_file( string $key ): string {
		if ( $key !== str_replace( [' ', '/', '\\', ':', '<', '>', '?', '*'], '', $key ) ) {
			$key = md5( $key ); 
		}

		return $this->root_dir . $key . '.dat';
	}

	/**
	 * Fetches a value from the cache. Helper function which do not do type validation.
	 *
	 * If the entry had expired it will be purged from the cache and the relevant file deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss,
	 *               bad file format, or cache entry expiry.
	 */
	private function get_value( $key, $default ) {
		$file = $this->key_to_file( (string) $key );
		$value = static::read_file( $file );
		if ( empty( $value ) ) {
			return $default;
		}
		
		$expiry = $value[0];

		// If expiry is not an integer we most likely have bad file. Treat it like the file was not there
		// and return the default.
		if ( ! is_int( $expiry ) ) {
			$this->purge_file( $file );
			return $default;
		}

		// If value has expired return the default.
		if ( $expiry < time() ) {
			$this->purge_file( $file );
			return $default;
		}

		$ret = $value[1];

		return $ret;
	}

	/**
	 * Fetches a value from the cache.
	 *
	 * If the entry had expired it will be purged from the cache and the relevant file deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key     The unique key of this item in the cache.
	 * @param mixed  $default Default value to return if the key does not exist.
	 *
	 * @return mixed The value of the item from the cache, or $default in case of cache miss,
	 *               bad file format, or cache entry expiry.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $ke2y is not string
	 */
	public function get( $key, $default = null ) {

		static::throw_if_not_string_int( $key );

		return $this->get_value( $key, $default );
	}

	/**
	 * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
	 *
	 * Helper function which do not do type validation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key    The key of the item to store.
	 * @param mixed  $value  The value of the item to store. Must be serializable.
	 * @param int    $expiry The unix time stamp in which the entry will expire.
	 */
	protected function set_value( $key, $value, int $expiry ) {

		$file = $this->key_to_file( (string) $key );

		$entry = [ $expiry, $value ];
		$content = serialize( $entry );

		return false !== @file_put_contents( $file, $content );
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
		
		$this->set_value( $key, $value, static::ttl_to_seconds( $ttl ) + time() );

		return true;
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

		$file = $this->key_to_file( (string) $key );
		if ( is_file( $file ) ) {
			static::purge_file( $file );
			return true;
		}

		return false;
	}

	/**
	 * Wipes clean the entire cache's keys.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if the cache was cleared, otherwise false.
	 */
	public function clear() {
		$files = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $this->root_dir, \RecursiveDirectoryIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $fileinfo) {
			if ( $fileinfo->isFile() ) {
				$file = $fileinfo->getRealPath();
				static::purge_file( $file );
			}				
		}

		return true;
	}

	/**
	 * Obtains multiple cache items by their unique keys.
	 *
	 * If the entry had expired it will be purged from the cache and the relevant file deleted.
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

		$ret = [];
		foreach ( $keys as $key ) {
			$ret[ $key ] = $this->get_value( $key, $default );
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
	 * @return bool true if all the values were stored, otherwise false.
	 *
	 * @throws \Psr\SimpleCache\InvalidArgumentException If $values is not iterable or contain a non string
	 *                                                   keys or $ttl is not valid type.
	 */
	public function setMultiple( $values, $ttl = null ) {
		static::throw_if_not_iterable( $values, false );

		$expiry = static::ttl_to_seconds( $ttl ) + time();
		foreach ( $values as $key => $value ) {
			$this->set_value( $key, $value, $expiry );
		}

		return true;
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

		foreach ( $keys as $key ) {
			$file = $this->key_to_file( (string) $key );
			static::purge_file( $file );
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

		return null !== $this->get( $key, null );
	}

	/**
	 * Check if cache files can be written on the root cache directory.
	 *
	 * As trying to detect permission if the directory do not exists proves to be a complex
	 * task which will require first creating the paths, something which is already attempted
	 * at the constructor, the code will just try to create an object and observe if creation fails.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if enabled, otherwise false.
	 */
	public static function is_available(): bool {
		$available = false;
		try {
			new File( '' );
			$available = true;
		} catch ( \Exception $e ) {}

		return $available;
	}
}
