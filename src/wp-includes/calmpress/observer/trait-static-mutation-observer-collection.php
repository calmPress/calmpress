<?php
/**
 * Declaration and implementation of a trait to use observer collection
 * of mutatiting observers as static property of a class.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * This trait is intended to be used by class which need just one
 * static property of an mutator kind of observer collection.
 * 
 * When using the trait a wrapper over add_collection need to be added to provide
 * some type checks (via type delaration of the observer most likely) to make sure
 * the observer supports the same number of parameters and their type at the 
 * "mutate" method as it is going to be passed to it.
 * 
 * @since calmPress 1.0.0
 */
trait Static_Mutation_Observer_Collection {

	use Static_Observer_Collection {
		remove_observer as remove_mutation_observer;
		remove_observers_of_class as remove_mutation_observers_of_class;
	}

	/**
	 * "call" the registered mutator to mutate a specific value.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param $value   The value to be mutated.
	 * @param ...$args Parameters that will be passed to the "mutate"
	 *                 method of the observer.
	 *
	 * @return The result of mutations of the $value parameter.
	 */
	public static function mutate( $value, ...$args ) {
		if ( isset( self::$collection ) ) {
			foreach ( self::$collection->observers() as $observer ) {
				$value = $observer->mutate( $value, ...$args );
			}
		}
		return $value;
	}
}