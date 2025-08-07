<?php
/**
 * Implementation controller for email sent to the user which request a "magic"
 * login link.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email sent to a the user which request a magic login link.
 * 
 * @since 1.0.0
 */
class User_Magic_Login_Email {

	use Email_To_User;

	/**
	 * The URL to redirect to after login.
	 *
	 * since 1.0.0
	 */
	public readonly string $redirect_to;

	/**
	 * Create an Magic_Login_Email object based on the $user to
	 * which the message is being sent.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user        The user to which the email is sent.
	 * @param string   $redirect_to The URL to redirect to after login.
	 */
	public function __construct( \WP_User $user, string $redirect_to ) {
		/* translators: %s: Site's name. */
		$initial_subject_format = __( '[%s] Log In link' );

		/* translators:
		 *	1: Users's display name.
		 *  2: Site name.
		 *  3: the url to use for instant login. 
		 */
		$initial_content_format = __(
'Hi %1$s,

You can follow the link below to login to [%2$s].
%3$s.
The link will expire in an hour.

If you did not expect this notice you are welcome to ignore this email.
'
		);

		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$this->email = new Email(
			sprintf( $initial_subject_format, $blog_name ),
			sprintf( 
				$initial_content_format,
				$user->display_name,
				$blog_name,
				$user->magic_login_link_url( $redirect_to ),
			),
			false,
			$user->email_address()
		);

		$this->user        = $user;
		$this->redirect_to = $redirect_to;
	}

	/**
	 * Register a mutator to be called before an email is sent.
	 *
	 * @since 1.0.0
	 *
	 * User_Magic_Email_Mutator |
	 * Email_Send_Abort_Mutator $mutator The object implementing the mutation observer.
	 *                                   Can either be an actual mutator or an "mutator"
	 *                                   that aborts the sending.
	 */
	public static function register_mutator(
		User_Magic_Login_Email_Mutator |
		Email_Send_Abort_Mutator
		$mutator ): void
	{
		self::add_observer( $mutator );
	}
}
