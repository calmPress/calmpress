<?php
/**
 * Implementation of a connector to the APCu extension.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\apcu;

/**
 * Implementation of a connector to the APCu extension. Provised general information regarding
 * the APCu and accessing the relevant cached keys on it.
 *
 * @since 1.0.0
 */
class APCu {

	/**
	 * Holder of the namespace used for keys in the part of cache controlled by the connector.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	private string $namespace;

	/**
	 * Construct a connector using the namespace of the keys used in the caches based on this connector.
	 *
	 * @since 1.0.0
	 *
	 * @param ?string $namespace The name underwhich the keys related to this connector will be grouped.
	 *                           If null is passed ( or parameter not specified ) a default prefix will be
	 *                           used. It is either defined in wp-config or derived from one of the salts.
	 *
	 * @throws \RuntimeException if APCu is not active.
	 */
	public function __construct( ?string $namespace = null ) {

		if ( ! static::APCu_is_avaialable() ) {
			throw new \RuntimeException( 'APCu is not available' );
		}

		if ( null === $namespace ) {
			$namespace = defined( 'APCU_PREFIX') ? APCU_PREFIX : md5( NONCE_SALT );
		}
		
		$this->namespace = $namespace;
	}

	/**
	 * The namespace of the keys used in the caches based on this connector.
	 *
	 * @since 1.0.0
	 *
	 * @return string The namespace.
	 */
	public function namespace() : string {
		return $this->namespace;
	}

	/**
	 * Check if APCu functionality is active.
	 *
	 * @since 1.0.0
	 *
	 * @return bool true if it is active, otherwise false.
	 */
	public static function APCu_is_avaialable() : bool {
		if ( defined( 'APCU_DISABLED' ) && APCU_DISABLED ) {
			return false;
		}
		
		return function_exists( 'apcu_enabled' ) && apcu_enabled();
	}

	/**
	 * Handle a situation where apcu_store had failed.
	 *
	 * Assume the failure is not indication of extremely fatal situation and try to store
	 * the information related to the failure in APCu itself.
	 *
	 * Storage is done with an entry per failure where every entry has a ttl of an hour.
	 * Entries will behave as if they are stored in "apcu_failures" global group.
	 *
	 * @since 1.0.0
	 */
	public function handle_store_failure() {
		$apcu_failure_namespace = $this->namespace . '/apcu_failures_';

		// Using rand to generate a uniq id instead of time() and uniqid to reduce the chance
		// of same id coming up in unit testing.
		apcu_store( $apcu_failure_namespace . rand( 0, 1000000 ), 1, HOUR_IN_SECONDS );

		// Remove the cached count value.
		apcu_delete( $apcu_failure_namespace . 'count' );
	}

	/**
	 * Get the amount of store failures reported.
	 *
	 * The relevant time interval is based on the ttl used in handle_store_failure. The result is cached
	 * for 5 minutes.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of failures currently recoreded.
	 */
	public function recent_store_failures(): int {
		$apcu_failure_namespace = $this->namespace . '/apcu_failures_';

		// If was already cached, use the cached value.
		$number_of_failures = apcu_fetch( $apcu_failure_namespace . 'count', $success );
		if ( $success ) {
			return $number_of_failures;
		}

		// Use the iterator to get the number of entries and cache it for 5 minutes.
		$iter = new \APCUIterator('#^' . $apcu_failure_namespace . '#' );
		apcu_store( $apcu_failure_namespace . 'count', $iter->getTotalCount(), 5 * MINUTE_IN_SECONDS );
		return $iter->getTotalCount();
	}

	/**
	 * Create an APCu object cache object for a cache group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group The group name.
	 *
	 * @return APCu The cache object.
	 */
	public function create_cache( string $namespace ): \calmpress\object_cache\APCu {
		return new \calmpress\object_cache\APCu( $this, $namespace );
	}

	/**
	 * Reset the APCu cache.
	 *
	 * @since 1.0.0
	 */
	public static function reset() {
		apcu_clear_cache();
	}

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
				'<p>' . __( 'Sorry, you are not allowed to manage APCu at this site.' ) . '</p>',
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
	public static function handle_apcu_reset() {
		static::verify_post_request( 'apcu_reset' );

		if ( static::APCu_is_avaialable() ) {
			static::reset();
			add_settings_error(
				'apcu_reset',
				'settings_updated',
				__( 'APCu reset was initiated.' ),
				'success'
			);	
		} else {
			add_settings_error(
				'apcu_reset',
				'apcu_reset',
				esc_html__( 'Can not manipulate APCu from this site' ),
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