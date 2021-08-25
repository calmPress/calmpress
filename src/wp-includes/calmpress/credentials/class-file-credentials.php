<?php
/**
 * Definition of the direct file access credentials.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\credentials;

/**
 * direct file access credentials object definition, basically a stub to be used where a credentials
 * based interface object is required.
 *
 * @since 1.0.0
 */
class File_Credentials implements Credentials {

	/**
	 * Generate a stream URL to a file. At this case just the path to the file.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The file path.
	 *
	 * @return string The same path given as parameter.
	 */
	public function stream_url_from_path( string $path ) {
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
