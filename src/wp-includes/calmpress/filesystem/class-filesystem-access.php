<?php
/**
 * Filesystem access for a calmPress installation.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Access to the files and directories used by a calmPress installation.
 *
 * This includes application files, runtime directories, and configured filesystem locations.
 *
 * Each implementation receives absolute local paths and provides access to the corresponding
 * files or directories through local filesystem operations or a remote protocol.
 *
 * Configured locations can be outside ABSPATH, such as wp-config.php in its parent directory.
 *
 * @since 1.0.0
 */
interface Filesystem_Access {

	/**
	 * Change permissions on a filesystem path.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute local path mapped by this implementation.
	 * @param int    $permissions Unix permission bits.
	 *
	 * @return bool Whether the permission change succeeded.
	 *
	 * @throws \DomainException If the path or filesystem command is invalid.
	 * @throws Filesystem_Access_Exception If filesystem access fails.
	 */
	public function change_permissions( string $path, int $permissions ): bool;

	/**
	 * Generate a stream URL for a file or directory in the installation.
	 *
	 * wp-config.php may be located in the directory above ABSPATH. Access to
	 * that file is allowed as an exception to the installation directory scope.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to a file or directory.
	 *
	 * @return string Stream URL for the path.
	 *
	 * @throws \DomainException If the path is invalid or outside the installation scope supported by this implementation.
	 */
	public function stream_url_from_path( string $path );

	/**
	 * The stream context to be used with the stream returned by stream_url_from_path.
	 *
	 * @since 1.0.0
	 *
	 * @return resource|null Stream context for this implementation.
	 *
	 * @throws Filesystem_Access_Exception If preparing filesystem access fails.
	 */
	public function stream_context();
}
