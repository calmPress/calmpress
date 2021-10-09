<?php
/**
 * Implementation of utilities functions for object cache.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * An implementation of utility functions related to object cache.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Verify capability, nonce, and validity of referer data a POST request. Die if the
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
				'<p>' . __( 'Sorry, you are not allowed to manage object cache at this site.' ) . '</p>',
				403
			);
		}
		check_admin_referer( $action );
	}

	/**
	 * Handles the form post regarding APCu reset, tries to reset the APCu cache if the request is valid.
	 *
	 * Used as a hook on admin-post.
	 *
	 * @since 1.0.0
	 */
	public static function handle_object_cache_reset() {
		static::verify_post_request( 'object_cache_reset' );

		wp_cache_flush();
		add_settings_error(
			'object_cache_reset',
			'settings_updated',
			__( 'Object Cache reset was initiated.' ),
			'success'
		);	

		set_transient( 'settings_errors', get_settings_errors(), 30 );	
	
		// Redirect back to the page from which the form was submitted.
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;			
	}
}
