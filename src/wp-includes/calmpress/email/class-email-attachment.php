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
interface Email_Attachment {

	/**
	 * The path to the file of the attachment.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path to the file.
	 */
	public function path(): string;
}
