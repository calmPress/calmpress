<?php
/**
 * Declaration of an interface of object that can be triggered when some
 * progress point is reached.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * interface that a progress observer should implement to be able to register it
 * with the No_Parameters_Progress_Observer_Collection.
 *
 * @since calmPress 1.0.0
 */
interface No_Parameters_Progress_Observer extends Observer {

	/**
	 * Called when a point in the executation is reached.
	 *
	 * @since calmPress 1.0.0
	 */
	public function reached(): void;
}