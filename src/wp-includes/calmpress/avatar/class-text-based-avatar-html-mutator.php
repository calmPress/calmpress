<?php
/**
 * Declaration of an interface of object that will be triggered to ovveride
 * the HTML generated for the text based avatar.
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
interface Text_Based_Avatar_HTML_Mutator extends \calmpress\observer\Observer {

	/**
	 * Generate (override or leave) the HTML that is generated for a text
	 * based avatar.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string $html   The HTML that is about to be used for the blank avatar.
	 * @param string $text   The text used to generate the avatar.
	 * @param string $color_factor The additional factor to apply when calculating
	 *                             the avatar's background color.
	 *                             It should be used to help visually differentiate
	 *                             between avatars with the same text that should
	 *                             represent different people.
	 * @param int    $width  The width in pixels of the blank avatar to generate.
	 * @param int    $height The height in pixels of the blank avatar to generate.
	 *
	 * @return string The HTML to use for the text based avatar.
	 */
	public function mutate(
		string $html,
		string $text,
		string $color_factor,
		int $width,
		int $height
	): string;
}