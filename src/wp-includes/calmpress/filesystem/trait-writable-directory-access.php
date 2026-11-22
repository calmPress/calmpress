<?php
/**
 * Directory write-access validation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Directory write-access checks through filesystem access implementations.
 *
 * The consuming class supplies parse_parameters() and a constructor accepting its
 * parsed parameters object, plus throw_if_remote_root_directory_is_unreachable() for remote directory validation.
 *
 * @since 1.0.0
 */
trait Writable_Directory_Access {

	/**
	 * Validate submitted settings, verify installation access, and test directory write access.
	 *
	 * @since 1.0.0
	 *
	 * @param array $vars Unslashed form values.
	 * @param string $directory Absolute directory requiring write access.
	 *
	 * @return Remote_Filesystem_Access|\WP_Error Filesystem access or validation and connection errors.
	 *
	 * @throws \LogicException If required parameters are missing or malformed.
	 */
	public static function writable_directory_access( array $vars, string $directory ): Remote_Filesystem_Access|\WP_Error {
		try {
			$parameters = static::parse_parameters( $vars );

			if ( is_wp_error( $parameters ) ) {
				return $parameters;
			}

			$filesystem_access = new static( $parameters );

			try {
				$filesystem_access->stream_url_from_path( $directory );
			} catch ( \DomainException $error ) {
				return new \WP_Error( 'connection_path_invalid', sprintf( __( 'Failed to access directory: %s' ), $directory ), [ 'directory' => $directory ] );
			}

			$filesystem_access->throw_if_remote_root_directory_is_unreachable();
			$result = self::validate_directory_write_access( $filesystem_access, $directory );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return $filesystem_access;
		} catch ( Filesystem_Access_Exception $error ) {
			return $error->to_wp_error();
		} catch ( \DomainException $error ) {
			return new \WP_Error( 'invalid_credentials', __( 'The connection settings or private key could not be used. Check them and try again.' ) );
		}
	}

	/**
	 * Verify that filesystem access can create and delete a file in a directory.
	 *
	 * @since 1.0.0
	 *
	 * @param Filesystem_Access $filesystem_access Filesystem access used to check the directory.
	 * @param string $directory Absolute directory path to check.
	 *
	 * @return true|\WP_Error True when the directory is writable, or a connection or write-access error.
	 */
	private static function validate_directory_write_access( Filesystem_Access $filesystem_access, string $directory ): true|\WP_Error {
		$probe = rtrim( $directory, '/\\' ) . '/.calmpress-access-' . wp_generate_uuid4();

		try {
			$url = $filesystem_access->stream_url_from_path( $probe );
			$context = $filesystem_access->stream_context();
		} catch ( \DomainException $error ) {
			return new \WP_Error( 'connection_path_invalid', sprintf( __( 'Failed to access directory: %s' ), $directory ), [ 'directory' => $directory ] );
		} catch ( Filesystem_Access_Exception $error ) {
			return $error->to_wp_error();
		} catch ( \RuntimeException $error ) {
			return new \WP_Error( 'connection_failed', $error->getMessage() );
		}

		$stream = @fopen( $url, 'wb', false, $context );

		if ( false === $stream ) {
			return new \WP_Error( 'connection_directory_not_writable', sprintf( __( 'Failed to write to directory: %s. Check its path and permissions.' ), $directory ), [ 'directory' => $directory ] );
		}

		fclose( $stream );
		$removed = @unlink( $url, $context );

		if ( ! $removed ) {
			return new \WP_Error(
				'connection_file_delete_failed',

				/* translators: 1: Directory path, 2: Temporary file name. */
				sprintf( __( 'Failed to delete files from directory: %1$s. Temporary file %2$s may have been left in it.' ), $directory, basename( $probe ) ),
				[ 'directory' => $directory, 'file' => $probe ]
			);
		}

		return true;
	}
}
