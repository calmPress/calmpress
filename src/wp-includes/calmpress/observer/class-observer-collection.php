<?php
/**
 * Declaration and implementation of a class to manage observers.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * Maintain a collection of observers that can be iterated upon (most likely
 * to notify them).
 * 
 * This mimics to a degree wordpress's hook functionality with the main
 * difference that it avoids the pitfuls of trying to assign priority value
 * to an action/filter. Instead of priorities a dependency system is used.
 * The disadvantage of a dependency system is that cyclic dependencies are
 * possible. In case of such a situation happening the behaviour of which observer
 * of those involved in that dependency will be suggested to be handled first
 * is not defined
 * 
 * Implementation detail - Insertion of an observer needs to be fast as this is a
 * common operation, and figuring out the dependencies is delayed until there
 * is a request to get the observers in the proper order.
 *
 * @since calmPress 1.0.0
 */
class Observer_Collection {

	/**
	 * Collection of observers for which notification order depends on dependencies.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @var Observer[]
	 */
	private array $collection = [];

	/**
	 * Collection of observers that still need to be iterated over.
	 *
	 * Kept in the objects "global" scope to be able to add and remove observers
	 * during iteration.
	 * When empty it indicates that no iteration over the observers is done at
	 * that point.
	 *
	 * @since calmPress 1.0.0
	 * 
	 * @var Observer[]
	 */
	private array $processing = [];

	/**
	 * Add an observer for which order of execution depends on its dependencies
	 * if there are any. If there are no dependencies the order of executation
	 * is undefined.
	 *
	 * An observer can be added during the observer iteration process, in which case
	 * the observer will be iterated upon. Its order will calculated relative to
	 * the observers which weren't iterated yet.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param Observer $observer The observer to add.
	 */
	public function add_observer( Observer $observer ) : void {
		$this->collection[ spl_object_id( $observer ) ] = $observer;
		if ( ! empty( $this->processing ) ) {
			// Observer iteration in progress, need to add the new observer
			// to it and resort.
			$this->processing[ spl_object_id( $observer ) ] = $observer;
			$this->sort_processing();
		}
	}

	/**
	 * Remove an observer.
	 * 
	 * Nothing happens with the observer was not added before.
	 * 
	 * Removal while observer iteration is in progress will cause the observer to not be
	 * iterated upon if it was not iterated yet.
	 * 
	 * @since calmPress 1.0.0
	 */
	public function remove_observer( Observer $observer ) : void {
		unset( $this->collection[ spl_object_id( $observer ) ] );
		unset( $this->processing[ spl_object_id( $observer ) ] );
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
	 * @since calmPress 1.0.0
	 */
	public function remove_observers_of_class( string $class ) : void {
		foreach ( $this->collection as $observer ) {
			if ( get_class( $observer ) === $class ) {
				unset( $this->collection[ spl_object_id( $observer ) ] );
				unset( $this->processing[ spl_object_id( $observer ) ] );
			}
		}
	}

	/**
	 * Compare two observers to decide which one should be notified first.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param Observer $a The first observer.
	 * @param Observer $b The second observer.
	 * 
	 * @return int 
	 *         -1 if $a should be notified before $b,
	 *         1 if $b should be notified before $a
	 *         0 if $a and $b can be notified in any order.
	 */
	private static function compare_observers( Observer $a, Observer $b ): int {

		$a_to_b_dep = $a->notification_dependency_with( $b );
		if ( $a_to_b_dep !== observer_priority::NONE ) {
			return $a_to_b_dep->value;
		}

		// The reverse of what notification_dependency_with returns
		// as we are realy interest in the relationship from $a "POV"
		// so if we get that $b should be after $a it means that $a
		// should be before $b
		return - $b->notification_dependency_with( $a )->value;
	}

	/**
	 * Sort the processing array to make it order base on "first" and "last"
	 * observer priorities and dependencies.
	 *
	 * @since calmPress 1.0.0
	 */
	private function sort_processing(): void {
		uasort(
			$this->processing,
			static function ( Observer $a, Observer $b ) {
				return self::compare_observers( $a, $b );
			}
		);
	}

	/**
	 * Provide an iterator over the observers in the collection which
	 * returns observers in the order of requested priority while allowing
	 * to add and remove observers to the collection that will be iterated
	 * upon or ignored while iterating.
	 * 
	 * @since calmPress 1.0.0
	 * 
	 * @return Iterator<Observer> Observers in proper notification order.
	 */
	public function observers() : \Iterator {
		if ( empty( $this->processing ) ) {
			$this->processing = $this->collection;
			$this->sort_processing();
		}

		foreach ( $this->processing as $key => $observer ) {
			unset( $this->processing[ $key ] );
			yield $observer;
		}
	}
}