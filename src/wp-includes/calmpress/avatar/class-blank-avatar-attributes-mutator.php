<?php
/**
 * Declaration of an interface of object that will be triggered to ovveride
 * the HTML generated for the blank avatar.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * interface that a mutation observer should implement to be able to register it
 * with to mutated the generate IMG attributes.
 *
 * @since calmPress 1.0.0
 */
interface Blank_Avatar_Attributes_Mutator extends \calmpress\observer\Observer {

	/**
	 * Generate (override or leave) the attribute that is generated for a blank avatar.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string[] $attributes A map of attributes and values which will be used in
	 *                             generated img tag.
	 * @param int      $size       The width and height of the avatar image in pixels.
	 *
	 * @return string[] A map of attributes and values which will be used in
	 *                  generated img tag.
	 */
	public function mutate( array $attributes, int $size ): array;
}