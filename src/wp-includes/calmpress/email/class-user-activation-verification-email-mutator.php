<?php
/**
 * Declaration of a interface that mutators for user activation verification emails
 * has to implement
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Decleration of a "by ref" mutator observer that can mutate User_Activation_Verification_Email objects.
 *
 * @since 1.0.0
 */
interface User_Activation_Verification_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjust an User_Activation_Verification_Email object.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Activation_Verification_Email $email The email object to mutate.
	 *
	 * @throws Abort_Send_Exception If the mail should not be sent at all.
	 */
	public function mutate_by_ref( User_Activation_Verification_Email &$email ): void;
}