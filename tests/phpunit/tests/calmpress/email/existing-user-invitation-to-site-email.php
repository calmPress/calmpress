<?php
/**
 * Unit tests covering Existing_User_Invitation_To_Site_Email.
 *
 * @package calmPress
 * @since 1.0.0
 *
 * @group ms-required
 * @group multisite
 */

declare(strict_types=1);

use calmpress\email\Existing_User_Invitation_To_Site_Email;
use calmpress\site\Site;

require_once __DIR__ . '/../../../includes/dummy-phpmailer.php';

/**
 * Tests the site invitation email type.
 */
class Existing_User_Invitation_To_Site_Email_Test extends WP_UnitTestCase {

	/**
	 * Tests that the constructor generates an invitation for the specified site.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_constructor(): void {
		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'site-invitee@example.com',
			]
		);
		$site = Site::current();
		$url  = 'https://example.com/wp-admin/user/sites.php';

		$email = new Existing_User_Invitation_To_Site_Email( $user, $site, $url );

		$this->assertSame( $user, $email->user );
		$this->assertSame( $site, $email->site );
		$this->assertSame( $url, $email->invitations_url );
		$this->assertStringContainsString( $site->name(), $email->email->content() );
		$this->assertStringContainsString( $url, $email->email->content() );
	}

	/**
	 * Tests that the constructor uses the invited user's preferred language.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_constructor_uses_user_locale(): void {
		$locale_during_translation = '';

		/**
		 * Records the locale used to translate the invitation subject.
		 *
		 * @param string $translation Translated text.
		 * @param string $text        Original text.
		 *
		 * @return string The translated text.
		 */
		$record_locale = static function ( string $translation, string $text ) use ( &$locale_during_translation ): string {
			if ( '[%s] Site invitation' === $text ) {
				$locale_during_translation = get_locale();
			}

			return $translation;
		};

		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'localized-site-invitee@example.com',
				'locale'     => 'de_DE',
			]
		);
		$original_locale = get_locale();

		add_filter( 'gettext', $record_locale, 10, 2 );
		new Existing_User_Invitation_To_Site_Email( $user, Site::current(), 'https://example.com/wp-admin/user/sites.php' );
		remove_filter( 'gettext', $record_locale, 10 );

		$this->assertSame( 'de_DE', $locale_during_translation );
		$this->assertSame( $original_locale, get_locale() );
	}

	/**
	 * Tests that send() uses the common Email implementation.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_send(): void {
		global $phpmailer;

		$phpmailer = new dummy_PHPMailer();
		$user       = self::factory()->user->create_and_get(
			[
				'user_email' => 'sent-site-invitee@example.com',
			]
		);
		$url        = 'https://example.com/wp-admin/user/sites.php';
		$email      = new Existing_User_Invitation_To_Site_Email( $user, Site::current(), $url );

		$email->send();

		$to_addresses = $phpmailer->getToAddresses();
		$this->assertCount( 1, $to_addresses );
		$this->assertSame( 'sent-site-invitee@example.com', $to_addresses[0][0] );
		$this->assertStringContainsString( $url, $phpmailer->Body );

		unset( $phpmailer );
	}
}
