<?php
/**
 * Implementation controller for an email inviting a user to a site or network.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

/**
 * Represents an invitation completed by authenticating.
 *
 * @since 1.0.0
 */
class User_Invitation_Email {

	use Email_To_User;

	/**
	 * The name of the site or network extending the invitation.
	 *
	 * @since 1.0.0
	 */
	public readonly string $site_name;

	/**
	 * The login page at which the user can request a one-time password.
	 *
	 * @since 1.0.0
	 */
	public readonly string $login_url;

	/**
	 * Creates a user invitation email.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user      The invited user.
	 * @param string   $site_name The site or network name.
	 * @param string   $login_url The login page URL.
	 */
	public function __construct( \WP_User $user, string $site_name, string $login_url ) {
		$switched_locale = switch_to_user_locale( $user->ID );
		$site_name       = wp_specialchars_decode( $site_name, ENT_QUOTES );
		$login_url       = add_query_arg( 'wp_lang', get_user_locale( $user ), $login_url );

		/* translators: %s: Site or network name. */
		$subject = __( '[%s] User invitation' );

		/* translators: 1: Site or network name. 2: Login URL. */
		$content = __(
'You were invited to become a member of %1$s.

To accept the invitation, visit and authenticate at the login page at %2$s. You will need to request a one-time password for this email address to complete the login process.

If you were not expecting this invitation, you can ignore this email.'
		);

		$this->email = new Email(
			sprintf( $subject, $site_name ),
			sprintf( $content, $site_name, $login_url ),
			false,
			$user->email_address()
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$this->user      = $user;
		$this->site_name = $site_name;
		$this->login_url = $login_url;
	}

	/**
	 * Registers a mutator to be called before the email is sent.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Invitation_Email_Mutator|Email_Send_Abort_Mutator $mutator The mutation observer.
	 */
	public static function register_mutator(
		User_Invitation_Email_Mutator|Email_Send_Abort_Mutator $mutator
	): void {
		self::add_observer( $mutator );
	}
}
