<?php
/**
 * Implementation of a mutator that abort email sending.
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;

/**
 * Implementation ofnull mutator that aborts sending the email.
 *
 * @since 1.0.0
 */
class Email_Send_Abort_Mutator implements Email_Mutator {

	/**
	 * Implement the mutate interface to just throw the abort exception.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $email The email object to mutate, ignored.
	 */
	public function mutate_by_ref( mixed &$email ): void {
		throw new Abort_Send_Exception();
	}

	/**
	 * Indicate this observer should run before any other.
	 * 
	 * Mainly to save execution time but also to reduce the chance of side effects.
	 *
	 * @since 1.0.0
	 * 
	 * @param Observer $observer The observer to determin the dependency against,
	 *                           ignored.
	 * 
	 * @return Observer_Priority The relative dependency between this observer and 
	 *                           $observer, always before it.
	 */
	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::BEFORE;
	}

}