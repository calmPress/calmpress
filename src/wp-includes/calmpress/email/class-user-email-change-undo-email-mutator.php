<?php
/**
 * Declaration of a interface that mutators for email change undo
 * has to implement
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Decleration of a "by ref" mutator observer that can mutate User_Email_Change_Verification_Email objects.
 *
 * @since 1.0.0
 */
interface User_Email_Change_Undo_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjust an User_Email_Change_Undo_Email object.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Email_Change_Undo_Email $email The email object to mutate.
	 */
	public function mutate_by_ref( User_Email_Change_Undo_Email &$email ): void;
}