<?php
/**
 * Declaration and implementation of a trait to use observer collection
 * as static property of a class.
 *
 * @package calmPress
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * This trait is intended to be used by class which need just one
 * static property of an observer collection.
 * 
 * @since 1.0.0
 */
trait Static_Observer_Collection {

	/**
	 * Collect observers and manipulate the collection.
	 * 
	 * null value indicates that an empty collection is associated with the class.
	 * This is done to save memory and initialization for something that will
	 * not be used in practice for most classes.
	 *
	 * @since 1.0.0
	 */
	private static ?Observer_Collection $collection = null;

	/**
	 * Add an observer for which order of execution depends on its dependencies
	 * if there are any. If there are no dependencies the order of executation
	 * is undefined.
	 *
	 * An observer can be added during the observer iteration process, in which case
	 * the observer will be iterated upon. Its order will calculated relative to
	 * the observers which weren't iterated yet.
	 * 
	 * Users of the trait should wrap this function to provide type checking to make
	 * sure the observer implement the relevant "reached" or "mutate" type 
	 * observer interfaces.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param Observer $observer The observer to add.
	 */
	private static function add_observer( Observer $observer ) : void {
		if ( null === self::$collection ) {
			self::$collection = new Observer_Collection();
		}
		self::$collection->add_observer( $observer );
	}

	/**
	 * Remove an observer.
	 * 
	 * Nothing happens with the observer was not added before.
	 * Unlike add_observer there is no type check expected to be done here as
	 * it is unlikely that observers of wrong type will be added to the collection
	 * and therefor an object with the wrong type is unlikely to be in the collection
	 * and removal does nothing.
	 * 
	 * Removal while observer iteration is in progress will cause the observer to not be
	 * iterated upon if it was not iterated yet.
	 * 
	 * @since 1.0.0
	 */
	public static function remove_observer( Observer $observer ) : void {
		if ( null !== self::$collection ) {
			self::$collection->remove_observer( $observer );
		}
	}

	/**
	 * Remove all observers of specific class.
	 *
	 * The class should match the observer's class, will not match inherited classes
	 * if any exists.
	 * 
	 * Removal while observer iteration is in progress will cause the relevant observers
	 * to not be iterated upon if they were not iterated yet.
	 * 
	 * @since 1.0.0
	 */
	public static function remove_observers_of_class( string $class ) : void {
		if ( null !== self::$collection ) {
			self::$collection->remove_observers_of_class( $class );
		}
	}

	/**
	 * Remove all observers.
	 *
	 * Both for completeness and as it is needed for testing as static properties
	 * are essentially global state.
	 * 
	 * @since 1.0.0
	 */
	public static function remove_all_observers() : void {
		self::$collection = null;
	}
}