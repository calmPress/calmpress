<?php
/**
 * Implementation of a blank avatar.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * A representation of a blank avatar which can be used where avatar should not
 * be displayed. A nicer alternative to returning empty values when an avatar is
 * requested.
 *
 * @since 1.0.0
 */
class Blank_Avatar implements Avatar {

	/**
	 * Implementation of the html method of the Avatar interface which returns
	 * an empty HTML in order for an echo of it to have no real impact.
	 *
	 * @since 1.0.0
	 *
	 * @param int $width  The width of the avatar image.
	 * @param int $hieght The height of the avatar image.
	 *
	 * @return string An HTML which will be rendered as a blank rectangle of the
	 *                requested dimensions.
	 */
	public function html( int $width, int $height ) : string {

		$html = "<span style='display:inline-block;width:${width}px;height:${height}px'></span>";

		/**
		 * Filters the generated blank avatar.
		 *
		 * @since 1.0.0
		 *
		 * @param string The HTML of the avatar.
		 * @param int    The width of the avatar.
		 * @param int    The height of the avatar.
		 */
		return apply_filters( 'calm_blank_avatar_html', $html, $width, $height );
	}

	/**
	 * Implementation of the attachment method of the Avatar interface which
	 * returns null as the blank avatar can not be configured by user.
	 *
	 * @since 1.0.0
	 *
	 * @return null Indicates no attachment is associated with the avatar.
	 */
	public function attachment() {
		return null;
	}
}
