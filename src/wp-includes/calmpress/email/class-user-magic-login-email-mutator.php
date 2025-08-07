<?php
/**
 * Declaration of a interface that mutators for magic link login emails
 * has to implement
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Decleration of a "by ref" mutator observer that can mutate User_Magic_Link_Email objects.
 *
 * @since 1.0.0
 */
interface User_Magic_Login_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjust an User_Magic_Login_Email object.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Magic_Login_Email $email The email object to mutate.
	 *
	 * @throws Abort_Send_Exception If the mail should not be sent at all.
	 */
	public function mutate_by_ref( User_Magic_Login_Email &$email ): void;
}