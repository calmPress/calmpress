<?php
/**
 * Implementation of a blan avatar.
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
interface Blank_Avatar {

	/**
	 * Implementation of the html method of the Avatar interface which returns
	 * an empty HTML inorder for an echo of it to have no real impact.
	 *
	 * @since 1.0.0
	 *
	 * @return string An empty string.
	 */
	public function html() : string {
		return '';
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
