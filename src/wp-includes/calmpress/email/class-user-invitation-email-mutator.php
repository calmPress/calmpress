<?php
/**
 * Declaration of the mutator interface for user invitation emails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Mutates a user invitation email before it is sent.
 *
 * @since 1.0.0
 */
interface User_Invitation_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjusts a user invitation email.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Invitation_Email $email The email object to mutate.
	 *
	 * @throws Abort_Send_Exception If the email should not be sent.
	 */
	public function mutate_by_ref( User_Invitation_Email &$email ): void;
}
