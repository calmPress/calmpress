<?php
/**
 * Implementation controller for email sent to the user which had installed calmPress
 * to verify the email given at install time.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email sent to a the user which had installed calmPress
 * to verify the email given at install time.
 * 
 * @since 1.0.0
 */
class Installer_Email_Verification_Email {

	use Email_To_User;

	/**
	 * Create an User_Activation_Verification_Email object based on the $user to
	 * which the message is being send and the new email address.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user The user to which the email is sent.
	 */
	public function __construct( \WP_User $user	) {
		/* translators: %s: Site's name. */
		$initial_subject_format = __( '[%s] Email Verification' );

		/* translators:
		 *	1: Users's display name.
		 *  2: Verification URL.
		 */
		$initial_content_format = __(
'Hi %1$s,

This is the last step to make sure that your new calmPress has all essentials configured.

Please follow the next link to verify that the email address given at install time,
to which this email is sent, is the right one
%2$s.
The link will expire in a day.

If you did not expect this notice you are welcome to ignore this email.
'
		);

		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$this->email = new Email(
			sprintf( $initial_subject_format, $blog_name ),
			sprintf( 
				$initial_content_format,
				$user->display_name,
				$user->installer_email_verification_url(),
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
	 * Installer_Email_Verification_Email |
	 * Email_Send_Abort_Mutator $mutator The object implementing the mutation observer.
	 *                                   Can either be an actual mutator or an "mutator"
	 *                                   that aborts the sending.
	 */
	public static function register_mutator(
		Installer_Email_Verification_Email_Mutator |
		Email_Send_Abort_Mutator
		$mutator ): void
	{
		self::add_observer( $mutator );
	}
}
