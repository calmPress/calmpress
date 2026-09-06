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
use WP_Network;
use WP_User;

/**
 * Provides an anchor for site-wide information and behavior, whether the site
 * is standalone or part of a network.
 *
 * @since 1.0.0
 */
class Site {

	/**
	 * ID of the initial site represented by base-prefixed database keys.
	 *
	 * This is independent of which site is configured as a network's main site.
	 *
	 * @since calmPress 1.0.0
	 */
	public const INITIAL_SITE_ID = 1;

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
	 * Retrieves the email address that receives system notifications for the site.
	 *
	 * @since 1.0.0
	 *
	 * @return string The notification recipient's email address.
	 */
	public function admin_email(): string {
		$user = get_userdata( (int) $this->option( 'admin_user_id' ) );

		return $user->user_email;
	}

	/**
	 * Retrieves the default recipient of comment moderation notifications.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_User The configured user, or the system notification recipient.
	 */
	public function default_comment_moderator_user(): WP_User {
		$user = new WP_User( (int) $this->option( 'comment_moderator_user' ), '', (int) $this->blog_id );

		if ( 0 !== $user->ID && array_intersect( [ 'administrator', 'editor' ], $user->roles ) ) {
			return $user;
		}

		return get_userdata( (int) $this->option( 'admin_user_id' ) );
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
		return $this->default_comment_moderator_user()->user_email;
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

	/**
	 * Retrieves the network containing the site.
	 *
	 * @since 1.0.0
	 *
	 * @return WP_Network|null The site's network, or null for a standalone site.
	 */
	public function network(): ?WP_Network {
		return null;
	}
}
