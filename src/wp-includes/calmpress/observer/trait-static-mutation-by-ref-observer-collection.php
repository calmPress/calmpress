<?php
/**
 * Declaration and implementation of a trait to use observer collection
 * of mutatiting observers which get the mutating value by reference 
 * as static property of a class.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * This trait is intended to be used by class which need just one
 * static property of an mutator kind of observer collection, where the mutator
 * mutates arrays or objects in which case the "use the returned type" way of
 * mutation can be less obvious (when mutating an object that way most likely
 * the same object that is been returned which might be weird, while with arrays
 * there is a performance penalty which is needless if all you want to do is
 * change one value for example).
 * It can be used for strings, ints etc, but probably not the right tool for that in most
 * cases.
 * 
 * When using the trait, a wrapper over add_collection need to be added to provide
 * some type checks (via type delaration of the observer most likely) to make sure
 * the observer supports the same number of parameters and their type at the 
 * "mutate" method as it is going to be passed to it.
 * 
 * @since calmPress 1.0.0
 */
trait Static_Mutation_By_Ref_Observer_Collection {

	use Static_Observer_Collection {
		remove_observer as remove_mutation_observer;
		remove_observers_of_class as remove_mutation_observers_of_class;
	}

	/**
	 * "call" the registered mutator to mutate a specific value.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param $value   Reference to the value to be mutated.
	 * @param ...$args Parameters that will be passed to the "mutate"
	 *                 method of the observer.
	 */
	public static function mutate_by_ref( &$value, ...$args ): void {
		if ( isset( self::$collection ) ) {
			foreach ( self::$collection->observers() as $observer ) {
				$observer->mutate_by_ref( $value, ...$args );
			}
		}
	}
}