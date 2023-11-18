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
 * with to mutated the generate HTML.
 *
 * @since calmPress 1.0.0
 */
interface Blank_Avatar_HTML_Mutator extends \calmpress\observer\Observer {

	/**
	 * Generate (override or leave) the HTML that is generated for a blank avatar.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string $html   The HTML that is about to be used for the blank avatar.
	 * @param int    $width  The width in pixels of the blank avatar to generate.
	 * @param int    $height The height in pixels of the blank avatar to generate.
	 *
	 * @return string The HTML to use for the blank avatar.
	 */
	public function mutate( string $html, int $width, int $height ): string;
}