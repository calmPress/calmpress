<?php
/**
 * Implementation controller for email sent to original address
 * after user's email change.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * A representation of email sent to a user when his email address is being
 * changed.
 * 
 * @since 1.0.0
 */
class Email_Address_Change_Notification_Email {

	use \calmpress\observer\Static_Mutation_By_Ref_Observer_Collection;

	/**
	 * The email to send.
	 *
	 * since 1.0.0
	 */
	public readonly Email $email;

	/**
	 * The user to which the email is sent.
	 * 
	 * @since 1.0.0
	 */
	public readonly \WP_User $user;

	/**
	 * The original email before the change.
	 * 
	 * @since 1.0.0
	 */
	public readonly Email_Address $original_email;

	/**
	 * The email after the change.
	 * 
	 * @since 1.0.0
	 */
	public readonly Email_Address $new_email;

	/**
	 * The url that the user can use to revert the change.
	 * 
	 * @since 1.0.0
	 */
	public readonly string $revert_url;

	/**
	 * Create an Email_Address_Change_Notification_Email object based on the $user to
	 * which the message is being send and the new email address.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User      $user           The user to which the email is sent.
	 * @param Email_Address $original_email The user's original email address
	 *                                      before the change.
	 * @param Email_Address $new_email      The user's email address after the change.
	 * @param string        $revert_url     The url to which the user can go to revert
	 *                                      the change.
	 */
	public function __construct(
		\WP_User      $user,
		Email_Address $original_email,
		Email_Address $new_email,
		string        $revert_url
	) {
		/* translators: %s: Site's name. */
		$initial_subject_format = __( '[%s] Email Changed' );

		/* translators: 1: Users's display name. */
		$initial_content_format = __(
			'Hi %1s,

This notice confirms that your email address on "%2$s" was changed to %3$s.

If you did not change your email, you can revert the change by following the link 
%4$s.
You can also contact the Site Administrator at %5$s or reply to this email.

This email has been sent to %6$s

Regards,
All at %2$s
%7$s'
		);

		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$this->email = new Email(
			sprintf( $initial_subject_format, $blog_name ),
			sprintf( 
				$initial_content_format,
				$user->display_name,
				$blog_name,
				$new_email->address,
				$revert_url,
				get_option( 'admin_email' ),
				$original_email->address,
				home_url()
			),
			false,
			$original_email
		);
		$this->email->set_reply_to_addresses( new Email_Address( get_option( 'admin_email' ) ) );
		$this->user           = $user;
		$this->original_email = $original_email;
		$this->new_email      = $new_email;
		$this->revert_url     = $revert_url;
	}

	/**
	 * Register a mutatur to be called before an email is sent.
	 *
	 * @since 1.0.0
	 *
	 * Email_Address_Change_Notification_Email_Mutator $mutator The object implementing the mutation observer.
	 */
	public static function register_mutator( Email_Address_Change_Notification_Email_Mutator $mutator ): void {
		self::add_observer( $mutator );
	}

	/**
	 * Send the email.
	 *
	 * Mutation done in two steps, first mutating the generated emails with the
	 * mutators registered with this class, after that using the Email class to
	 * send the email which will trigger the mutators registered at that class.
	 * 
	 * @since 1.0.0
	 */
	public function send(): void {
		// Let mutators change whatever needed.
		self::mutate_by_ref( $this );

		// And send...
		$this->email->send();
	}
}
