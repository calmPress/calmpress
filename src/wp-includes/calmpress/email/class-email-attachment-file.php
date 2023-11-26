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
	 */
	private string $path;

	/**
	 * The string to use as the title of the attachment.
	 *
	 * @since 1.0.0
	 */
	private string $title = '';

	/**
	 * Construct the attachment based on file.
	 * 
	 * @since 1.0.0
	 *
	 * @param string $path The path to the attachment file.
	 * @param string $title The title to use for the attachment.
	 *  
	 * @throws RuntimeException if the file given is not readable. 
	 */
	public function __construct( string $path, string $title = '' ) {
		if ( ! is_readable( $path ) ) {
			throw new \RuntimeException( $path . ' is not readable' );
		}

		$this->path = $path;
		$this->title = trim( str_replace( "\r\n", ' ', $title ) );
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

	/**
	 * The title to to use for the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return string The title.
	 */
	public function title(): string {
		return $this->title;
	}
}
