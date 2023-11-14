<?php
/**
 * Declaration of an interface of observer.
 *
 * @since calmPress 1.0.0
 */

declare(strict_types=1);

namespace calmpress\observer;

/**
 * Declares the values that can indicate what is the relative priority between observers.
 *
 * Values of enum made to match return values expected by a uasort callback.
 * @since calmPress 1.0.0
 */
enum Observer_Priority: int {

	/**
	 * indicates that an observer should be notified before another.
	 *
	 * @since calmPress 1.0.0
	 */
	case BEFORE = -1;

	/**
	 * indicates that an observer should be notified after another.
	 *
	 * @since calmPress 1.0.0
	 */
	case AFTER = 1;

	/** 
	 * Indicates that the there is no defined difference in priority.
	 *
	 * @since calmPress 1.0.0
	 */
	case NONE = 0;
}

/**
 * Interface that an observer should implement.
 *
 * @since calmPress 1.0.0
 */
interface Observer {

	/**
	 * Indicates whether the observer should be notified before or after another.
	 * or if there are nor concrete priority relationships.
	 * 
	 * The strategy to employ to determine order is up to the implemantation.
	 * For example to get the observer to be notified ASAP it is possible to always return
	 * Observer_Priority::BEFORE.
	 * To notify as late as possible can just always return Observer_Priority::AFTER.
	 * And if there is no dependency at all just always return Observer_Priority::NONE.
	 *
	 * For a more granular logic it might be easier to inspect the class of the observer
	 * over its actual value as observers are likely to be private and therefor hard
	 * to get access to, while class name can be derived from the code or documentation.
	 * 
	 * @since calmPress 1.0.0
	 *
	 * @param Observer $observer The observer to determin the dependency against.
	 * @return Observer_Priority The relative dependency between this observer and 
	 *                           $observer.
	 */
	public function notification_dependency_with( Observer $observer ) : Observer_Priority;
}