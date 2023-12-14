<?php
/**
 * Implementation controller for email sent to a user being regitering
 * or being added to the site to confirm the operation
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email sent to a user when his email address was used to
 * register as a user on the site.
 * 
 * @since 1.0.0
 */
class User_Activation_Verification_Email {

	use Email_To_User;

	/**
	 * Create an Email_Address_Change_Notification_Email object based on the $user to
	 * which the message is being send and the new email address.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user The user to which the email is sent.
	 */
	public function __construct( \WP_User $user	) {
		/* translators: %s: Site's name. */
		$initial_subject_format = __( '[%s] User Activation' );

		/* translators: 1: Users's display name. */
		$initial_content_format = __(
'Hi %1$s

There was a user registered for you at "%2$s" under your email address %3$s.

Please follow the next link to finish the activation
%4$s.
The link will present you a form to change your password. Once changed you will be
directed to the login page.

If you did not expect this registration notice you are welcome to ignore this email.

Regards,
All at %2$s
%5$s'
		);

		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$this->email = new Email(
			sprintf( $initial_subject_format, $blog_name ),
			sprintf( 
				$initial_content_format,
				$user->display_name,
				$blog_name,
				$user->user_email,
				$user->activation_url(),
				home_url()
			),
			false,
			$user->email_address()
		);

		$this->user = $user;
	}

	/**
	 * Register a mutator to be called before an email is sent.
	 *
	 * @since 1.0.0
	 *
	 * User_Activation_Verification |
	 * Email_Send_Abort_Mutator $mutator The object implementing the mutation observer.
	 *                                   Can either be an actual mutator or an "mutator"
	 *                                   that aborts the sending.
	 */
	public static function register_mutator(
		User_Activation_Verification_Email_Mutator |
		Email_Send_Abort_Mutator
		$mutator ): void
	{
		self::add_observer( $mutator );
	}
}
