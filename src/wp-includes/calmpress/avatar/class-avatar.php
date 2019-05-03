<?php
/**
 * Interface specification of the avatar class
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * An abstract representation of an avatar.
 *
 * @since 1.0.0
 */
interface Avatar {

	/**
	 * Provides the HTML required to display the avatar. The HTML assumed to be
	 * with an inline display property, either by using inline elements like IMG
	 * and SPAN or adding relevant CSS styling.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width  The width of the avatar image.
	 * @param int $hieght The height of the avatar image.
	 *
	 * @return string The escaped HTML needed to display the avatar.
	 */
	public function html( int $width, int $height ) : string;

	/**
	 * Provide the attachment image associated with the avatar if one exists.
	 *
	 * This is envisioned to be an helper function in situations in which there
	 * is a need to detect if the avatar is an image uploaded by a human.
	 *
	 * @since 1.0.0
	 *
	 * @return \WP_Post|null The WP_Post object for the image attachment or null if
	 *                       no attachment is associated with the avatar.
	 */
	public function attachment();
}
