<?php
/**
 * Declaration of an email attachment interface.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email attachment.
 *
 * @since 1.0.0
 */
class Email_Attachment_File implements Email_Attachment {

	/**
	 * The path to the file.
	 * 
	 * since 1.0.0
	 *
	 * @throws RuntimeException if the file given is not readable. 
	 */
	private string $path;

	/**
	 * Construct the attachment based on file path.
	 * 
	 * since 1.0.0
	 *
	 * @param string $path The path to the attachment file.
	 *  
	 * @throws RuntimeException if the file given is not readable. 
	 */
	public function __construct( string $path ) {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( $path . ' is not readable' );
		}

		$this->path = $path;
	}

	/**
	 * The path to the file of the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path to the file.
	 */
	public function path(): string {
		return $this->path;
	}
}
