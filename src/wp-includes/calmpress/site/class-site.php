<?php
/**
 * Site abstraction.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\site;

use RuntimeException;
use UnexpectedValueException;
use WP_User;

/**
 * Provides an anchor for site-wide information and behavior, whether the site
 * is standalone or part of a network.
 *
 * @since 1.0.0
 */
class Site {

	/**
	 * Site ID.
	 *
	 * Stored as a numeric string for compatibility with WP_Site.
	 *
	 * Standalone installations use the conventional site ID of `1`.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $blog_id = '1';

	/**
	 * Retrieves the site's administrators ordered by user ID.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_User[] The site administrators.
	 *
	 * @throws RuntimeException If the site has no administrators.
	 */
	public function administrators(): array {
		$query = [
			'blog_id' => (int) $this->blog_id,
			'role'    => 'administrator',
			'orderby' => 'ID',
		];

		$administrators = get_users( $query );

		if ( [] === $administrators ) {
			throw new RuntimeException( 'No administrators found on this site. Please configure at least one administrator.' );
		}

		return $administrators;
	}

	/**
	 * Resolves the email address that receives system notifications for the site.
	 *
	 * The configured email must belong to a site administrator. The first site
	 * administrator is used when the configured email is invalid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $email_address Configured email address.
	 *
	 * @return string The notification recipient's email address.
	 */
	public function admin_email( string $email_address ): string {
		// At install time there are no users, so trust the configured address.
		if ( wp_installing() ) {
			return $email_address;
		}

		$administrators = $this->administrators();

		foreach ( $administrators as $administrator ) {
			if ( $administrator->user_email === $email_address ) {
				return $email_address;
			}
		}

		return $administrators[0]->user_email;
	}

	/**
	 * Retrieves the default recipient of comment moderation notifications.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_User The configured user, or the default administrator.
	 *
	 * @throws RuntimeException If the recipient user cannot be found.
	 */
	public function default_comment_moderator_user(): WP_User {
		$user = get_user_by( 'email', $this->default_comment_moderator_email() );

		if ( ! $user instanceof WP_User ) {
			throw new RuntimeException( 'Site default comment moderation recipient user could not be found.' );
		}

		return $user;
	}

	/**
	 * Retrieves the email address that receives comment moderation notifications.
	 *
	 * The configured user must be an administrator or editor of the site. The
	 * system notification recipient is used when the configured user is invalid.
	 *
	 * @since 1.0.0
	 *
	 * @return string The recipient's email address.
	 */
	public function default_comment_moderator_email(): string {
		$configured_user_id = $this->option( 'comment_moderator_user' );
		$users              = [];

		if ( is_numeric( $configured_user_id ) && 0 < (int) $configured_user_id ) {
			$users = get_users(
				[
					'blog_id'  => (int) $this->blog_id,
					'include'  => [ (int) $configured_user_id ],
					'role__in' => [ 'administrator', 'editor' ],
					'number'   => 1,
				]
			);
		}

		if ( [] !== $users ) {
			return $users[0]->user_email;
		}

		return $this->admin_email( (string) $this->option( 'admin_email' ) );
	}

	/**
	 * Retrieves an option for the site.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Option name.
	 *
	 * @return mixed The option value, or false when the option does not exist.
	 */
	protected function option( string $name ): mixed {
		return get_option( $name );
	}

	/**
	 * Retrieves the site associated with the current execution context.
	 *
	 * @since 1.0.0
	 *
	 * @return Site The current site.
	 *
	 * @throws UnexpectedValueException If the current multisite site cannot be resolved.
	 */
	public static function current(): Site {
		if ( ! is_multisite() ) {
			return new Site();
		}

		$site = get_site();

		if ( null === $site ) {
			throw new UnexpectedValueException( 'The current site cannot be resolved.' );
		}

		return $site;
	}
}
