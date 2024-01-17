<?php
/**
 * Implementation controller for email sent to the new address
 * to very user's changed address.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email sent to a user to verify a new email adress when the
 * user's email address being changed.
 * 
 * @since 1.0.0
 */
class User_Email_Change_Verification_Email {

	use Email_To_User;

	/**
	 * Create an User_Email_Change_Verification_Email object based on the $user to
	 * which the message is being sent.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user The user to which the email is sent.
	 */
	public function __construct( \WP_User $user	) {
		/* translators: %s: Site's name. */
		$initial_subject_format = __( '[%s] Email Changed' );

		/* translators: 1: Users's display name. */
		$initial_content_format = __(
			'Hi %1s,

This notice confirms that your email address on "%2$s" is about to be changed into %3$s.

To complete the change please follow the link %4$s.
The change will take effect only after following the link above.
The link is valid for 7 days.

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
				$user->changed_email_into()->address,
				$user->email_change_verification_url(),
				home_url()
			),
			false,
			$user->changed_email_into()
		);

		$this->user = $user;
	}

	/**
	 * Register a mutator to be called before an email is sent.
	 *
	 * @since 1.0.0
	 *
	 * User_Email_Change_Verification_Email_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_mutator( User_Email_Change_Verification_Email_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}
}
