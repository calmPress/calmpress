<?php
/**
 * Declaration of a interface that mutators for email change notification
 * has to implement
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Decleration of a "by ref" mutator observer that can mutate Email_Address_Change_Notification_Email objects.
 *
 * @since 1.0.0
 */
interface Email_Address_Change_Notification_Email_Mutator extends \calmpress\observer\Observer {

	/**
	 * Adjust an Email_Address_Change_Notification_Email object.
	 *
	 * @since 1.0.0
	 *
	 * @param Email_Address_Change_Notification_Email $email The email object to mutate.
	 */
	public function mutate_by_ref( Email_Address_Change_Notification_Email &$email ): void;
}