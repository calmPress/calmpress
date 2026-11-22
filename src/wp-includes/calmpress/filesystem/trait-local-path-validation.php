<?php
/**
 * Local filesystem path validation for a calmPress installation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Installation path validation shared by filesystem access implementations.
 *
 * @since 1.0.0
 */
trait Local_Path_Validation {

	/**
	 * Throw if a path is not an absolute local path within the installation scope.
	 *
	 * The installation directory and explicitly configured content, plugin, language,
	 * and upload directories are allowed, including paths to files not yet created.
	 *
	 * wp-config.php may also be accessed in the directory immediately above ABSPATH.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Local installation path to validate.
	 *
	 * @throws \DomainException If the path is not absolute and local, contains parent traversal, or is outside the installation scope.
	 */
	private function throw_if_not_absolute_local_path( string $path ): void {

		// path_is_absolute() also accepts existing paths provided by registered stream wrappers.
		if ( wp_is_stream( $path ) || ! path_is_absolute( $path ) ) {
			throw new \DomainException( 'Path must be an absolute local file path.' );
		}

		$path = wp_normalize_path( $path );

		if ( in_array( '..', explode( '/', $path ), true ) ) {
			throw new \DomainException( 'Installation paths cannot contain parent-directory traversal.' );
		}

		$root        = rtrim( wp_normalize_path( ABSPATH ), '/' );
		$directories = [ $root ];

		// Configured filesystem locations remain part of this installation even when stored outside ABSPATH.
		foreach ( [ 'WP_CONTENT_DIR', 'WP_PLUGIN_DIR', 'WPMU_PLUGIN_DIR', 'WP_LANG_DIR' ] as $name ) {

			if ( defined( $name ) ) {
				$directories[] = rtrim( wp_normalize_path( constant( $name ) ), '/' );
			}

		}

		if ( defined( 'UPLOADS' ) ) {
			$directories[] = rtrim( wp_normalize_path( ABSPATH . UPLOADS ), '/' );
		}

		foreach ( $directories as $directory ) {

			if ( $path === $directory || str_starts_with( $path, $directory . '/' ) ) {
				return;
			}

		}

		// Only the parent configuration file is allowed, rather than the surrounding directory.
		if ( $path === dirname( $root ) . '/wp-config.php' ) {
			return;
		}

		throw new \DomainException( 'Path must belong to the calmPress installation or a configured filesystem location.' );

	}
}
