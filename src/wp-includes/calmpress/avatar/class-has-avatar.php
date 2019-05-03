<?php
/**
 * Interface specification for objects which have avatars.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\avatar;

/**
 * Interface specification for objects which have avatars.
 *
 * It is used to make the detections of objects with avatars more robust.
 *
 * @since 1.0.0
 */
interface Has_Avatar {

	/**
	 * The avatar associated with the object.
	 *
	 * @since 1.0.0
	 *
	 * @return \calmpress\avatar\Avatar The avatar.
	 */
	public function avatar() : Avatar;
}
