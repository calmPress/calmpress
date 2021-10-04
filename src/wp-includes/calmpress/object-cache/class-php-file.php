<?php
/**
 * Implementation of PHP file object caching per cache group.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * An PHP file based object cache per a caching group.
 *
 * The idea of this cache is to utilize the PHP opcode caching for storing cached values as
 * part of the interpreted PHP code which is stored in the common memory of the server and is
 * avaialable to every HTTP session without a need of an external storage medium.
 *
 * This is done by generating a file with a PHP code that holds an array including the
 * expiry time at its first element, and value at its second.
 *
 * When the cache needs to fetch a value for a key it looks at the cache directory for the
 * correct file and includes it. The first time it is done PHP will parse the file and store
 * its interpreted content before "running". After the first time the content is already parsed
 * and it is just run.
 *
 * The advantage of this cache is that it is always there in modern PHP. APCu which provides similar
 * functionality is an extension that needs to be installed.
 * The disadvantage is that it is a hack which might have adverse impact on the general PHP performance
 * if the memory that PHP reserve for its interpreter cache runs out which will require that part of the
 * actual code will be purged from the cache and reread later which will make things slower. It should be
 * used for values which are required for almost every request and change very rarely.
 *
 * Extends the File class as they have many coomon areas with regard to managing files.
 * 
 * @since 1.0.0
 * 
 * There are no type hinting to be compatible with the interface defined in PSR-16,
 * instead there are type checks.
 */
class PHP_File extends File {

	/**
	 * The root directory in which the cache files are located.
	 */
	public const CACHE_ROOT_DIR = WP_CONTENT_DIR . '/.private/object-cache/phpfile/';

	/**
	 * Construct a group cache over a PHP file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_directory The path of the directory in which to store the relevant file
	 *                                relative to the general PHP file object caching root.
	 *
	 * @throws \RuntimeException if APCu is not active.
	 */
	public function __construct( string $cache_directory ) {

		if ( ! static::is_available() ) {
			throw new \RuntimeException( 'Opcache is not available' );
		}

		parent::__construct( $cache_directory );
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
			try {
				// protect against files which generate output.
				ob_start();
				$ret = include $file;
				ob_end_clean();
				if ( ! is_array( $ret ) ) {
					// the file supposed to contain an array, but it isn't so treat it like
					// curropted file.
					$ret = [];
					static::purge_file( $file );
				}
			} catch (\Throwable  $e) {
				ob_end_clean();
				// Indicates there was problem with reading or parsing the file.
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
		\calmpress\opcache\Opcache_Connector::invalidate_file( $file );
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

		return $this->root_dir . $key . '.php';
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

		$file = $this->key_to_file( $key );

		$content = sprintf( '<?php return [%d, %s];', $expiry, var_export( $value, true ) );

		file_put_contents( $file, $content );

		// Invalidate whatever is in cache right now.
		\calmpress\opcache\Opcache_Connector::invalidate_file( $file );
	}

	/**
	 * Check if opcache is enabled. Without it being enabled there is no point in having
	 * this kind of cache.
	 * 
	 * Check done by checking that the relevant functions are avaiable and that it is enabled
	 * in the PHP configuration.
	 * 
	 * In addition check that it is possible to write cache files.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if enabled, otherwise false.
	 */
	public static function is_available(): bool {
		if ( \calmpress\opcache\Opcache_Connector::api_is_avaialable() ) {
				return wp_is_writable( self::CACHE_ROOT_DIR );
		}

		return false;
	}
}
