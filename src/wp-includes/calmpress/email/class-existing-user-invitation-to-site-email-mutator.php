<?php
/**
 * Mutator interface for site invitation emails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Mutates a site invitation email before it is sent.
 *
 * @since 1.0.0
 */
interface Existing_User_Invitation_To_Site_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjusts a site invitation email.
	 *
	 * @since 1.0.0
	 *
	 * @param Existing_User_Invitation_To_Site_Email $email The email object to mutate.
	 *
	 * @throws Abort_Send_Exception If the email should not be sent.
	 */
	public function mutate_by_ref( Existing_User_Invitation_To_Site_Email &$email ): void;
}
