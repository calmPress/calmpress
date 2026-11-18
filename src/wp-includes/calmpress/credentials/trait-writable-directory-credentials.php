<?php
/**
 * Shared factory for protocol-specific connection credentials.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Parses parameters using the consuming class and constructs that class's credentials.
 *
 * The consuming class supplies parse_parameters() and a constructor accepting its
 * parsed parameters object.
 *
 * @since 1.0.0
 */
trait Writable_Directory_Credentials {

	/**
	 * Create credentials and verify write access to the supplied directory.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Unslashed form values.
	 * @param string $directory Absolute directory requiring write access.
	 *
	 * @return Connection_Credentials|\WP_Error Usable credentials or validation and connection errors.
	 *
	 * @throws \LogicException If required parameters are missing or malformed.
	 */
	public static function writable_directory_credentials( array $vars, string $directory ): Connection_Credentials|\WP_Error {
		try {
			$parameters = static::parse_parameters( $vars );

			if ( is_wp_error( $parameters ) ) {
				return $parameters;
			}

			$credentials = new static( $parameters );
			$result = \calmpress\utils\validate_directory_write_access( $credentials, $directory );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return $credentials;
		} catch ( Connection_Exception $error ) {
			return $error->to_wp_error();
		} catch ( \DomainException $error ) {
			return new \WP_Error( 'invalid_credentials', __( 'The connection settings or private key could not be used. Check them and try again.' ) );
		}
	}
}
