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
	 * since 1.0.0
	 *
	 * @throws RuntimeException if the file given is not readable. 
	 */
	private \WP_Post $attachment;

	/**
	 * The attachment holding the file to attach.
	 * 
	 * since 1.0.0
	 *
	 * @throws RuntimeException if the file given is not readable. 
	 */
	public function __construct( \WP_Post $attachment ) {
		if ( $attachment->post_type !== 'attachment' ) {
			throw new \RuntimeException( 'Attachment is expected but ' . $attachment->post_type . ' is given' );
		}

		if ( ! is_readable( get_attached_file( $attachment->ID ) ) ) {
			throw new \RuntimeException( $path . ' is not readable' );
		}

		$this->attachment = $attachment;
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
	 * The attachment post associated with this email attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Post The attachment post.
	 */
	public function attachment(): \WP_Post {
		return $this->attachment;
	}
}
