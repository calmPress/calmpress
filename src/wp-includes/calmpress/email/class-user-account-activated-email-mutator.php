<?php
/**
 * Mutator interface for user account activation emails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Mutates a user account activation email before it is sent.
 *
 * @since 1.0.0
 */
interface User_Account_Activated_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjusts a user account activation email.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Account_Activated_Email $email The account activation email.
	 *
	 * @throws Abort_Send_Exception If the email should not be sent.
	 */
	public function mutate_by_ref( User_Account_Activated_Email &$email ): void;
}
