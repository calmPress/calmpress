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

	/**
	 * The title to be used as the title of the attachment in the email, most likely if
	 * "saves/downloaded" the title will be the suggested name of the created
	 * local file.
	 * 
	 * Return empty string to let the system select one by itself, most likely it will
	 * be the name of the file.
	 *
	 * @since 1.0.0
	 *
	 * @return string The title.
	 */
	public function title(): string;

}
