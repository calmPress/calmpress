<?php
/**
 * Implementation of a connector to PHP's opcode cache.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\opcache;

/**
 * A utility class to query and change the state of the opcache.
 *
 * @since 1.0.0
 */
class Opcache {

	/**
	 * Construct a connector to the opcache fuctionality.
	 *
	 * @since 1.0.0
	 *
	 * @throws \RuntimeException if opcache API is not available.
	 */
	public function __construct() {

		if ( ! static::api_is_avaialable() ) {
			throw new \RuntimeException( 'Opcache API is not available' );
		}
	}

	/**
	 * Get the opcache stats.
	 *
	 * @since 1.0.0
	 *
	 * @return Stats An object serving as an interface to get the stats.
	 */
	public function stats(): Stats {
		return new Stats( opcache_get_status( false ) );
	}

	/**
	 * Reset the opcache.
	 *
	 * @since 1.0.0
	 */
	public function reset() {
		opcache_reset();
	}

	/**
	 * Signal to the opcache to invalidate a file in the cache.
	 *
	 * It is the calling code responsability to make sure the api is available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file The path of the file to invalidate.
	 */
	public static function invalidate_file( string $file ) {
		opcache_invalidate( $file, true );
	}

	/**
	 * Check if the Opcache API can be used.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if it can, otherwise false.
	 */
	public static function api_is_avaialable() : bool {
		if ( function_exists( 'opcache_get_status' ) ) {
			// This check ensures that it is possible to use the api to do stuff,
			// especially invalidate cache files. Without invalidation there is
			// a potential of using stale values.
			if ( false !== opcache_get_status() ) {	
				return true;
			}
		}

		return false;
	}
}