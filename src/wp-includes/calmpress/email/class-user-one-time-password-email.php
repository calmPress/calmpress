<?php
/**
 * Implementation controller for email sent to the user which requested
 * a one time password.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

use calmpress\utils\One_Time_Password;

/**
 * A representation of email sent to a the user which requested a one time password.
 * 
 * @since 1.0.0
 */
class User_One_Time_Password_Email {

	use Email_To_User;

	/**
	 * The password.
	 *
	 * since 1.0.0
	 */
	public readonly One_Time_Password $password;

	/**
	 * Create an User_One_Time_Password_Email object based on the $user to
	 * which the email is being sent.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User          $user     The user to which the email is sent.
	 * @param One_Time_Password $password The password to be sent.
	 */
	public function __construct( \WP_User $user, One_Time_Password $password ) {
		/* translators: %s: Site's name. */
		$initial_subject_format = __( '[%s] one-time password' );

		/* translators:
		 *	1: Users's display name.
		 *  2: Site name.
		 *  3: the password. 
		 */
		$initial_content_format = __(
'Hi %1$s,

You requested a one-time password.  
Your one-time password is:

%2$s

This password will expire, so please use it as soon as possible. You can always request a new one if needed.

If you did not expect this notice you are welcome to ignore this email.
'
		);

		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$this->email = new Email(
			sprintf( $initial_subject_format, $blog_name ),
			sprintf( 
				$initial_content_format,
				$user->display_name,
				$password->password,
			),
			false,
			$user->email_address()
		);

		$this->user     = $user;
		$this->password = $password;
	}

	/**
	 * Register a mutator to be called before an email is sent.
	 *
	 * @since 1.0.0
	 *
	 * User_One_Time_Password_Email_Mutator |
	 * Email_Send_Abort_Mutator $mutator The object implementing the mutation observer.
	 *                                   Can either be an actual mutator or an "mutator"
	 *                                   that aborts the sending.
	 */
	public static function register_mutator(
		User_One_Time_Password_Email_Mutator |
		Email_Send_Abort_Mutator
		$mutator ): void
	{
		self::add_observer( $mutator );
	}
}
