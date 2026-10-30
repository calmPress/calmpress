<?php
/**
 * Definition of the credentials interface.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * Credentials for accessing files in a calmPress installation as streams.
 *
 * @since 1.0.0
 */
interface Credentials {

	/**
	 * Generate a stream URL for a file or directory in the installation.
	 *
	 * wp-config.php may be located in the directory above ABSPATH. Credentials used to access
	 * that file must also cover its parent directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Absolute path to a file or directory.
	 *
	 * @return string Stream URL for the path.
	 *
	 * @throws \DomainException When the path is not absolute or its directory is outside the area
	 *                          mapped by these credentials.
	 */
	public function stream_url_from_path( string $path );

	/**
	 * The stream context to be used with the stream returned by stream_url_from_path.
	 *
	 * @since 1.0.0
	 *
	 * @return resource stream context allowing file overwrite.
	 */
	public function stream_context();
}
