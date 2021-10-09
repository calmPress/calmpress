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

		if ( ! static::api_is_available() ) {
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
	 * Tries to reset the opcache. A reset might actually put the opcache into "reset pending"
	 * state and actually happen later. The request will fail if there is already a pending reset request.
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
	 * Check if the Opcache is active (implies API is available).
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if it can, otherwise false.
	 */
	public static function is_active() : bool {
		if ( static::api_is_available() ) {
			$state = opcache_get_status( false );
			return $state['opcache_enabled'];
		}

		return false;
	}

	/**
	 * Check if the Opcache API can be used.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if it can, otherwise false.
	 */
	public static function api_is_available() : bool {
		if ( function_exists( 'opcache_get_status' ) ) {
			// This check ensures that it is possible to use the api to do stuff,
			// especially invalidate cache files. Without invalidation there is
			// a potential of using stale values.
			if ( false !== opcache_get_status( false ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify capability, nonce, and validitty of referer data a POST request. Die if the
	 * user is not allowed to manage opcache, or nonce/referer include
	 * bad data. 
	 *
	 * @since 1.0.0
	 *
	 * @param string $action The name of the action expected to be used for generating the nonce
	 *                       and admin referer fields in the request.
	 */
	private static function verify_post_request( string $action ) {
		if ( ! current_user_can( 'manage_server' ) ) {
			wp_die(
				'<h1>' . __( 'You need additional permission.' ) . '</h1>' .
				'<p>' . __( 'Sorry, you are not allowed to manage opcache at this site.' ) . '</p>',
				403
			);
		}
		check_admin_referer( $action );
	}

	/**
	 * Handles the form post regarding opcache reset, tries to reset the opcache if the request is valid.
	 *
	 * Used as a hook on admin-post.
	 *
	 * @since 1.0.0
	 */
	public static function handle_opcache_reset() {
		static::verify_post_request( 'opcache_reset' );

		try {
			$opcache = new Opcache();
			$opcache->reset();
			add_settings_error(
				'opcache_reset',
				'settings_updated',
				__( 'Opcode Cache reset was initiated.' ),
				'success'
			);	
		} catch ( \Throwable $e ) {
			add_settings_error(
				'opcache_reset',
				'opcache_reset',
				esc_html__( 'Something went wrong, please try again' ),
				'error'
			);
		}

		set_transient( 'settings_errors', get_settings_errors(), 30 );	
	
		// Redirect back to the page from which the form was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}
}
