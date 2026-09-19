<?php
/**
 * Mutator interface for declined site invitation emails.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Mutates a site invitation decline email before it is sent.
 *
 * @since 1.0.0
 */
interface Site_Invitation_Declined_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjusts a site invitation decline email.
	 *
	 * @since 1.0.0
	 *
	 * @param Site_Invitation_Declined_Email $email The email object to mutate.
	 *
	 * @throws Abort_Send_Exception If the email should not be sent.
	 */
	public function mutate_by_ref( Site_Invitation_Declined_Email &$email ): void;
}
