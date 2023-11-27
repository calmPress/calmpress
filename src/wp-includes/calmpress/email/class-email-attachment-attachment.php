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
class Email_Attachment_Attachment implements Email_Attachment {

	/**
	 * The attachment holding the file to attach.
	 * 
	 * @since 1.0.0
	 */
	public readonly \WP_Post $attachment;

	/**
	 * The string to use as the title of the attachment. A value of empty
	 * string indicates that the title of the attachment should be used.
	 *
	 * @since 1.0.0
	 */
	private string $title = '';

	/**
	 * The attachment holding the file to attach.
	 * 
	 * since 1.0.0
	 *
	 * @throws RuntimeException if the file given is not readable. 
	 */
	public function __construct( \WP_Post $attachment, string $title = '' ) {
		if ( $attachment->post_type !== 'attachment' ) {
			throw new \RuntimeException( 'Attachment is expected but ' . $attachment->post_type . ' is given' );
		}

		if ( ! is_readable( get_attached_file( $attachment->ID ) ) ) {
			throw new \RuntimeException( $path . ' is not readable' );
		}

		$this->attachment = $attachment;
		$this->title = trim( str_replace( "\r\n", ' ', $title ) );
	}

	/**
	 * The path to the file of the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path to the file. If the attachment do not a file
	 *                attached to it (or other kind of failue to find it)
	 *                returns empty string.
	 */
	public function path(): string {
		$path = get_attached_file( $this->attachment->ID );
		return ! $path ? '' : $path;
	}

	/**
	 * The title to to use for the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return string The title.
	 */
	public function title(): string {
		if ( $this->title !== '' ) {
			return $this->title;
		}

		return $this->attachment->post_title;
	}
}
