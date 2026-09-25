<?php
/**
 * Email reporting that a user left a site.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\email;

use calmpress\site\Site;

/**
 * Represents an email sent to a site's system notification recipient after a user leaves.
 *
 * @since 1.0.0
 */
class User_Left_Site_Email {

	use Email_To_User;

	/**
	 * The user who left the site.
	 *
	 * @since 1.0.0
	 */
	public readonly \WP_User $departed_user;

	/**
	 * The site the user left.
	 *
	 * @since 1.0.0
	 */
	public readonly Site $site;

	/**
	 * Creates a user-left-site email.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $recipient     The administrator to notify.
	 * @param \WP_User $departed_user The user who left the site.
	 * @param Site     $site          The site the user left.
	 */
	public function __construct( \WP_User $recipient, \WP_User $departed_user, Site $site ) {
		$switched_locale = switch_to_user_locale( $recipient->ID );
		$site_name       = wp_specialchars_decode( $site->name(), ENT_QUOTES );

		/* translators: %s: Site name. */
		$subject = __( '[%s] User left the site' );

		/* translators: 1: User's display name. 2: User's email address. 3: Site name. */
		$content = __( '%1$s (%2$s) left "%3$s". Their user privileges were removed, and their existing content remains attributed to an anonymized user.' );

		$this->email = new Email(
			sprintf( $subject, $site_name ),
			sprintf( $content, $departed_user->display_name, $departed_user->user_email, $site_name ),
			false,
			$recipient->email_address()
		);

		if ( $switched_locale ) {
			restore_previous_locale();
		}

		$this->user          = $recipient;
		$this->departed_user = $departed_user;
		$this->site          = $site;
	}

	/**
	 * Registers a mutator to be called before the email is sent.
	 *
	 * @since 1.0.0
	 *
	 * @param User_Left_Site_Email_Mutator|Email_Send_Abort_Mutator $mutator The mutation observer.
	 */
	public static function register_mutator(
		User_Left_Site_Email_Mutator|Email_Send_Abort_Mutator $mutator
	): void {
		self::add_observer( $mutator );
	}
}
