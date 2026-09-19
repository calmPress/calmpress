<?php
/**
 * Email reporting that a user account was activated.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

use calmpress\site\Site;

/**
 * Represents an account activation email sent to a site's or network's
 * configured system notification recipient.
 *
 * @since 1.0.0
 */
class User_Account_Activated_Email {

	use Email_To_User;

	/**
	 * The user who activated the account.
	 *
	 * @since 1.0.0
	 */
	public readonly \WP_User $activated_user;

	/**
	 * The site or network for which the account was activated.
	 *
	 * @since 1.0.0
	 */
	public readonly Site|\WP_Network $context;

	/**
	 * Creates a user account activation email.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User        $recipient      The administrator to notify.
	 * @param \WP_User        $activated_user The user who activated the account.
	 * @param Site|\WP_Network $context        The site or network for which the account was activated.
	 */
	public function __construct( \WP_User $recipient, \WP_User $activated_user, Site|\WP_Network $context ) {
		$switched_locale = switch_to_user_locale( $recipient->ID );
		$context_name     = $context instanceof \WP_Network ? $context->site_name : $context->name();
		$context_name     = wp_specialchars_decode( $context_name, ENT_QUOTES );

		/* translators: %s: Site or network name. */
		$subject = __( '[%s] User account activated' );

		/* translators: 1: Activated user's display name. 2: Their email address. 3: Site or network name. */
		$content = __( 'The account for %1$s (%2$s) was activated for "%3$s".' );

		$this->email = new Email(
			sprintf( $subject, $context_name ),
			sprintf( $content, $activated_user->display_name, $activated_user->user_email, $context_name ),
			false,
			$recipient->email_address()
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$this->user           = $recipient;
		$this->activated_user = $activated_user;
		$this->context        = $context;
	}

	/**
	 * Registers a mutator to be called before the email is sent.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Account_Activated_Email_Mutator|Email_Send_Abort_Mutator $mutator The mutation observer.
	 */
	public static function register_mutator(
		User_Account_Activated_Email_Mutator|Email_Send_Abort_Mutator $mutator
	): void {
		self::add_observer( $mutator );
	}
}
