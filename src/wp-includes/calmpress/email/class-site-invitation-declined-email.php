<?php
/**
 * Email reporting that a site invitation was declined.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

use calmpress\site\Site;

/**
 * Represents an email reporting that a site invitation was declined.
 *
 * @since 1.0.0
 */
class Site_Invitation_Declined_Email {

	use Email_To_User;

	/**
	 * The user who declined the invitation.
	 *
	 * @since 1.0.0
	 */
	public readonly \WP_User $invited_user;

	/**
	 * The site whose invitation was declined.
	 *
	 * @since 1.0.0
	 */
	public readonly Site $site;

	/**
	 * Creates a site invitation decline email.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $recipient    The administrator to notify.
	 * @param \WP_User $invited_user The user who declined the invitation.
	 * @param Site     $site         The site whose invitation was declined.
	 */
	public function __construct( \WP_User $recipient, \WP_User $invited_user, Site $site ) {
		$switched_locale = switch_to_user_locale( $recipient->ID );
		$site_name       = wp_specialchars_decode( $site->name(), ENT_QUOTES );

		/* translators: %s: Site name. */
		$subject = __( '[%s] Site invitation declined' );

		/* translators: 1: Invited user's display name. 2: Site name. */
		$content = __( '%1$s declined the invitation to become a member of "%2$s".' );

		$this->email = new Email(
			sprintf( $subject, $site_name ),
			sprintf( $content, $invited_user->display_name, $site_name ),
			false,
			$recipient->email_address()
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$this->user         = $recipient;
		$this->invited_user = $invited_user;
		$this->site         = $site;
	}

	/**
	 * Registers a mutator to be called before the email is sent.
	 *
	 * @since 1.0.0
	 *
	 * @param Site_Invitation_Declined_Email_Mutator|Email_Send_Abort_Mutator $mutator The mutation observer.
	 */
	public static function register_mutator(
		Site_Invitation_Declined_Email_Mutator|Email_Send_Abort_Mutator $mutator
	): void {
		self::add_observer( $mutator );
	}
}
