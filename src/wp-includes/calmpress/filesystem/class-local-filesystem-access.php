<?php
/**
 * Local filesystem access.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Access to files and directories through PHP's local filesystem functions.
 *
 * @since 1.0.0
 */
class Local_Filesystem_Access implements Filesystem_Access {

	use Local_Path_Validation;

	/**
	 * Change permissions on a local filesystem path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute local file or directory path.
	 * @param int    $permissions Unix permission bits.
	 *
	 * @return bool Whether the permission change succeeded.
	 *
	 * @throws \DomainException If the path is not a valid absolute local path within the installation scope.
	 */
	public function change_permissions( string $path, int $permissions ): bool {
		$this->throw_if_not_absolute_local_path( $path );

		return @chmod( $path, $permissions );
	}

	/**
	 * Generate a stream URL to a file. At this case just the path to the file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute local file path.
	 *
	 * @return string The same path given as parameter.
	 *
	 * @throws \DomainException If the path is not a valid absolute local path within the installation scope.
	 */
	public function stream_url_from_path( string $path ) {
		$this->throw_if_not_absolute_local_path( $path );

		return $path;
	}

	/**
	 * The stream context to be used with the stream returned by stream_url_from_path.
	 *
	 * @since 1.0.0
	 *
	 * @return null.
	 */
	public function stream_context() {
		return null;
	}
}
