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
class Opcache_Connector {

	/**
	 * Signal to the opcache to invalidate a file in the cache.
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