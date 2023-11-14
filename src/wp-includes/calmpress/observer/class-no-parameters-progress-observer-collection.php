<?php
/**
 * Declaration and implementation of a class to manage
 * and notify progress observers that require no additional notification data.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * Maintain a collection of progress observers to notify
 * when exection reaches a certain point where the notification do not
 * require any additional data (parameters).
 * 
 * @see Observer_Collection for a more complete description of
 * how collection are managed.
 *
 * @since calmPress 1.0.0
 */
class No_Parameters_Progress_Observer_Collection {

	use Observer_Collection {
		add_observer as trait_add_observer;
	}

	/**
	 * Add a progress observer to be notified.
	 * 
	 * A thin wrapper over Observer_Collection::add_observer to
	 * provide type checking to make sure only progress observers which
	 * require no data are in the collection.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param No_Parameters_Progress_Observer $observer The observer to add.
	 */
	public function add_observer( No_Parameters_Progress_Observer $observer ) : void {
		$this->trait_add_observer( $observer );
	}

	/**
	 * Notify the registered observers that the point in execution was
	 * reached in the order of thier requested dependency.
	 *
	 * @since calmPress 1.0.0
	 */
	public function reached(): void {
		foreach ( $this->observers() as $observer ) {
			$observer->reached();
		}
	}
}