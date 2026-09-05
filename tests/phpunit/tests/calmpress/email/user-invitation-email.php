<?php
/**
 * Unit tests covering User_Invitation_Email.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\User_Invitation_Email;

require_once __DIR__ . '/../../../includes/dummy-phpmailer.php';

/**
 * Tests the user invitation email type.
 */
class User_Invitation_Email_Test extends WP_UnitTestCase {

	/**
	 * Tests that the constructor retains its invitation context.
	 */
	public function test_constructor(): void {
		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'invitee@example.com',
			]
		);
		$email = new User_Invitation_Email( $user, 'Example Network', 'https://example.com/login' );

		$this->assertSame( $user, $email->user );
		$this->assertSame( 'Example Network', $email->site_name );
		$this->assertSame( 'https://example.com/login', $email->login_url );
		$this->assertStringContainsString( 'You were invited', $email->email->content() );
		$this->assertStringContainsString( 'https://example.com/login', $email->email->content() );
	}

	/**
	 * Tests that the invitation is generated in the invited user's preferred language.
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
			if ( '[%s] User invitation' === $text ) {
				$locale_during_translation = get_locale();
			}

			return $translation;
		};

		$user = self::factory()->user->create_and_get(
			[
				'user_email' => 'localized-invitee@example.com',
				'locale'     => 'de_DE',
			]
		);
		$original_locale = get_locale();

		add_filter( 'gettext', $record_locale, 10, 2 );
		new User_Invitation_Email( $user, 'Example Network', 'https://example.com/login' );
		remove_filter( 'gettext', $record_locale, 10 );

		$this->assertSame( 'de_DE', $locale_during_translation );
		$this->assertSame( $original_locale, get_locale() );
	}

	/**
	 * Tests that the invitation is sent through the common Email implementation.
	 */
	public function test_send(): void {
		global $phpmailer;

		$phpmailer = new dummy_PHPMailer();
		$user       = self::factory()->user->create_and_get(
			[
				'user_email' => 'invitee@example.com',
			]
		);
		$email      = new User_Invitation_Email( $user, 'Example Site', wp_login_url() );

		$email->send();

		$to_addresses = $phpmailer->getToAddresses();
		$this->assertCount( 1, $to_addresses );
		$this->assertSame( 'invitee@example.com', $to_addresses[0][0] );
		$this->assertStringContainsString( wp_login_url(), $phpmailer->Body );

		unset( $phpmailer );
	}

	/**
	 * Tests that standalone user creation sends the shared user invitation.
	 */
	public function test_wp_new_user_notification_sends_user_invitation(): void {
		$mail = [];

		/**
		 * Captures and suppresses the outgoing message.
		 *
		 * @param null|bool $return     Whether to short-circuit sending.
		 * @param array     $attributes The wp_mail() arguments.
		 *
		 * @return false Prevents delivery by the test mailer.
		 */
		$capture_mail = static function ( $return, $attributes ) use ( &$mail ): false {
			$mail = $attributes;

			return false;
		};

		$user_id = self::factory()->user->create(
			[
				'user_email' => 'standalone-invitee@example.com',
			]
		);

		add_filter( 'pre_wp_mail', $capture_mail, 10, 2 );
		wp_new_user_notification( $user_id, null, 'user' );
		remove_filter( 'pre_wp_mail', $capture_mail, 10 );

		$this->assertStringContainsString( 'User invitation', $mail['subject'] );
		$this->assertStringContainsString( wp_login_url(), $mail['message'] );
		$this->assertStringNotContainsString( 'action=rp', $mail['message'] );
	}

}
