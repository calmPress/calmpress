<?php
/**
 * Declaration and implementation of a trait to use observer collection
 * as static property of a class.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * This trait is intended to be used by class which need just one
 * static property of a progress observer collection.
 *
 * When using the trait a wrapper over add_collection need to be added to provide
 * some type checks (via type delaration of the observer most likely) to make sure
 * the observer supports the same number of parameters and their type at the 
 * "reached" method as it is going to be passed to it.
 * 
 * @since calmPress 1.0.0
 */
trait Static_Progress_Observer_Collection {

	use Static_Observer_Collection {
		remove_observer as remove_progress_observer;
		remove_observers_of_class as remove_progress_observers_of_class;
	}

	/**
	 * Notify the observers in the collection that the execution point
	 * was reached.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param ...$args Parameters that will be passed to the "reached"
	 *                 method of the observer.
	 */
	public static function notify_observers( ...$args ) : void {
		if ( isset( self::$collection ) ) {
			foreach ( self::$collection->observers() as $observer ) {
				$observer->reached( ...$args );
			}
		}
	}
}