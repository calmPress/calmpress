<?php
/**
 * Mutator interface for user-left-site emails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Mutates a user-left-site email before it is sent.
 *
 * @since 1.0.0
 */
interface User_Left_Site_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjusts a user-left-site email.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Left_Site_Email $email The email object to mutate.
	 *
	 * @throws Abort_Send_Exception If the email should not be sent.
	 */
	public function mutate_by_ref( User_Left_Site_Email &$email ): void;
}
