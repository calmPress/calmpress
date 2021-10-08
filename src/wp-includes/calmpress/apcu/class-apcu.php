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
	 *                           used. It is either define in wp-config or derived from one of the salts.
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
	 * Create an APCu object cache object for a global cache group.
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
}