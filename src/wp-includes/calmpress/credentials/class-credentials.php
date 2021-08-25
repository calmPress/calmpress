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
 * An credentials holder, meant to be used as a translator between file paths and stream based url
 * to be able to access paths as streams.
 *
 * @since 1.0.0
 */
interface Credentials {

	/**
	 * Generate a stream URL to a file. 
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path. Must be an absolute path.
	 *
	 * @return string The stream url representation of the file specified by $path.
	 *
	 * @throws \DomainException When the path is not absolute or not a file under the root directory.
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
