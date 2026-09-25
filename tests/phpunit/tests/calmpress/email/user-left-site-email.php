<?php
/**
 * Unit tests covering User_Left_Site_Email.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\User_Left_Site_Email;
use calmpress\site\Site;

/**
 * Tests the user-left-site email type.
 */
class User_Left_Site_Email_Test extends WP_UnitTestCase {

	/**
	 * Tests that the constructor represents the user, recipient, and site involved.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_constructor(): void {
		$recipient = self::factory()->user->create_and_get(
			[
				'user_email' => 'notification-recipient@example.com',
			]
		);
		$departed_user = self::factory()->user->create_and_get(
			[
				'display_name' => 'Departing User',
				'user_email'   => 'departing-user@example.com',
			]
		);
		$site = Site::current();

		$email = new User_Left_Site_Email( $recipient, $departed_user, $site );

		$this->assertSame( $recipient, $email->user );
		$this->assertSame( $departed_user, $email->departed_user );
		$this->assertSame( $site, $email->site );
		$this->assertStringContainsString( $departed_user->display_name, $email->email->content() );
		$this->assertStringContainsString( $departed_user->user_email, $email->email->content() );
		$this->assertStringContainsString( $site->name(), $email->email->content() );
	}
}
