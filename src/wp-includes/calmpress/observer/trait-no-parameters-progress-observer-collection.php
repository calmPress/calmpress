<?php
/**
 * Declaration and implementation of a trait that implements
 * progress observer collection as a static property.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * Maintain a collection of progress observers to notify
 * when exection reaches a certain point as a static property where
 * the notification do not require any additional data (parameters).
 * 
 * @see Observer_Collection for a more complete description of
 * how collection are managed.
 *
 * @since calmPress 1.0.0
 */
trait No_Parameters_Progress_Observer_Collection {

	use Static_Progress_Observer_Collection {
		remove_observer as remove_progress_observer;
		remove_observers_of_class as remove_progress_observers_of_class;
		remove_all_observers as remove_all_progress_observers;
	}

	/**
	 * Add a progress observer to be notified.
	 * 
	 * A thin wrapper over Observer_Collection::add_observer to
	 * provide type checking to make sure only progress observers which
	 * require no parameters are in the collection.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param No_Parameters_Progress_Observer $observer The observer to add.
	 */
	public static function add_progress_observer( No_Parameters_Progress_Observer $observer ) : void {
		self::add_observer( $observer );
	}
}