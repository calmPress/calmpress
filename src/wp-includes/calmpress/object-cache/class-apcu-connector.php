<?php
/**
 * Implementation of a connector to the APCu extension.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\object_cache;

/**
 * Implementation of a connector to the APCu extension. Provised general information regarding
 * the APCu and accessing the relevant cached keys on it.
 *
 * @since 1.0.0
 */
class APCu_Connector {

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
	 * @param string $namespace The name underwhich the keys related to this connector will be grouped.
	 *
	 * @throws \RuntimeException if APCu is not active.
	 */
	public function __construct( string $namespace ) {

		if ( ! static::APCu_is_avaialable() ) {
			throw new \RuntimeException( 'APCu is not available' );
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
		return function_exists( 'apcu_enabled' ) && apcu_enabled();
	}

	/**
	 * Create an APCu cache object for a global cache group.
	 *
	 * @since 1.0.0
	 *
	 * @param string $group The group name.
	 *
	 * @return APCu The cache object.
	 */
	public  function create_cache( string $namespace ) : APCu {
		return new APCu( $this, $namespace );
	}
}