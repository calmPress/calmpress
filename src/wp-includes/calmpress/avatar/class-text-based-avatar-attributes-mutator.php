<?php
/**
 * interface that a mutation observer should implement to be able to register it
 * to mutated the generate attribute of the IMG tag for text based avatars.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * interface that a mutation observer should implement to be able to register it
 * to mutated the generate HTML.
 *
 * @since calmPress 1.0.0
 */
interface Text_Based_Avatar_Attributes_Mutator extends \calmpress\observer\Observer {

	/**
	 * Generate (override or leave) the attributes which will be included
	 * in the generated IMG tag for a text based avatar.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string $attr         The IMG tag attributes that are about to be used for the avatar.
	 * @param string $text         The text used to generate the avatar.
	 * @param string $color_factor The additional factor to apply when calculating
	 *                             the avatar's background color.
	 *                             It should be used to help visually differentiate
	 *                             between avatars with the same text that should
	 *                             represent different people.
	 * @param int    $size         The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of the attibutes to use in the IMG tag.
	 */
	public function mutate(
		array $attr,
		string $text,
		string $color_factor,
		int $size
	): array;
}