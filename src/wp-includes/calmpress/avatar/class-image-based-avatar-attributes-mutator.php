<?php
/**
 * Declaration of an interface of object that will be triggered to override
 * the HTML generated for the image based avatar.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * interface that a mutation observer should implement to be able to register it
 * to mutated the generate attribute of the IMG tag for image based avatars.
 *
 * @since calmPress 1.0.0
 */
interface Image_Based_Avatar_Attributes_Mutator extends \calmpress\observer\Observer {

	/**
	 * Generate (override or leave) the attributes which will be included
	 * in the generated IMG tag for an image based avatar.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string  $attr       The IMG tag attributes that are about to be used for the avatar.
	 * @param WP_Post $attachment The ID of the image attachment.
	 * @param int     $size       The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of the attibutes to use in the IMG tag.
	 */
	public function mutate(
		array $attr,
		\WP_Post $attachment,
		int $size
	): array;
}