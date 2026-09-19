<?php
/**
 * Email for inviting an existing account to a site.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

use calmpress\site\Site;

/**
 * Represents an invitation to join an additional site on a network where the
 * user already has an account.
 *
 * @since 1.0.0
 */
class Existing_User_Invitation_To_Site_Email {

	use Email_To_User;

	/**
	 * The site to which the user is invited.
	 *
	 * @since 1.0.0
	 */
	public readonly Site $site;

	/**
	 * The URL of the page on which the user can respond to the invitation.
	 *
	 * @since 1.0.0
	 */
	public readonly string $invitations_url;

	/**
	 * Creates a site invitation email.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user            The invited user.
	 * @param Site     $site            The site extending the invitation.
	 * @param string   $invitations_url The page on which the user can respond.
	 */
	public function __construct( \WP_User $user, Site $site, string $invitations_url ) {
		$switched_locale = switch_to_user_locale( $user->ID );
		$site_name       = wp_specialchars_decode( $site->name(), ENT_QUOTES );

		/* translators: %s: Site name. */
		$subject = __( '[%s] Site invitation' );

		/* translators: 1: Site name. 2: Page on which the invitation can be accepted or declined. */
		$content = __(
'You were invited to become a member of "%1$s".

Please accept or decline the invitation on the following page:
%2$s

If you were not expecting this invitation, you can decline it or ignore this email.'
		);

		$this->email = new Email(
			sprintf( $subject, $site_name ),
			sprintf( $content, $site_name, $invitations_url ),
			false,
			$user->email_address()
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$this->user            = $user;
		$this->site            = $site;
		$this->invitations_url = $invitations_url;
	}

	/**
	 * Registers a mutator to be called before the email is sent.
	 *
	 * @since 1.0.0
	 *
	 * @param Existing_User_Invitation_To_Site_Email_Mutator|Email_Send_Abort_Mutator $mutator The mutation observer.
	 */
	public static function register_mutator(
		Existing_User_Invitation_To_Site_Email_Mutator|Email_Send_Abort_Mutator $mutator
	): void {
		self::add_observer( $mutator );
	}
}
