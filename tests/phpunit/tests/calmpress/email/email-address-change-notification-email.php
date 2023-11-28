<?php
/**
 * Unit tests covering Email_Address_Change_Notification_Email class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email;
use calmpress\email\Email_Address;
use calmpress\email\Email_Address_Change_Notification_Email_Mutator;
use calmpress\email\Email_Address_Change_Notification_Email;
use calmpress\email\Email_Mutator;
use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;

require_once __DIR__ . '/../../../includes/dummy-phpmailer.php';

/**
 * An implementation of an Email_Address_Change_Notification_Email_Mutator interface to use in testing.
 */
class Mock_Notification_Observer implements Email_Address_Change_Notification_Email_Mutator {

	public string $value;

	public function __construct( string $value ) {
		$this->value = $value; 
	}

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( Email_Address_Change_Notification_Email &$email ):void {
		$email->email->set_subject( $this->value );
	}
}

class Email_Address_Change_Notification_Email_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set the properties
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$user_id = $this->factory->user->create();
		$user = get_user_by( 'id', $user_id );

		$email = new Email_Address_Change_Notification_Email(
			$user,
			new Email_Address( 'old@example.com' ),
			new Email_Address( 'new@example.com' ),
			'http://example.com/revert'
		);

		$this->assertSame( $user, $email->user );
		$this->assertSame( 'old@example.com', $email->original_email->address );
		$this->assertSame( 'new@example.com', $email->new_email->address );
		$this->assertSame( 'http://example.com/revert', $email->revert_url );
	}

	/**
	 * Test send method.
	 * 
	 * Test checks that the properties of the phpmailer being set correctly.
	 */
	public function test_send() {
		global $phpmailer;
		$phpmailer = new dummy_PHPMailer();

		$user_id = $this->factory->user->create();
		$user = get_user_by( 'id', $user_id );

		$email = new Email_Address_Change_Notification_Email(
			$user,
			new Email_Address( 'old@example.com' ),
			new Email_Address( 'new@example.com' ),
			'http://example.com/revert'
		);

		$email->send();

		// Test mail is sent to the old address
		$tos = $phpmailer->getToAddresses();
		$this->assertSame( 1, count( $tos ) );
		$this->assertSame( 'old@example.com', $tos[0][0] );

		// Test reply-to set to admin email.
		// Test mail is sent to the old address
		$rt = $phpmailer->getReplyToAddresses();
		$this->assertSame( 1, count( $rt ) );
		$this->assertSame( get_option( 'admin_email' ), $rt[get_option( 'admin_email' )][0] );

		unset( $phpmailer );
	}

	/**
	 * Test mutators.
	 */
	public function test_mutators() {
		global $phpmailer;
		$phpmailer = new dummy_PHPMailer();

		$user_id = $this->factory->user->create();
		$user = get_user_by( 'id', $user_id );
	
		$email = new Email_Address_Change_Notification_Email(
			$user,
			new Email_Address( 'old@example.com' ),
			new Email_Address( 'new@example.com' ),
			'http://example.com/revert'
		);

		// Test the specific notification mutators.
		$mutate_notification = new Mock_Notification_Observer( 'tasti' );
		Email_Address_Change_Notification_Email::register_mutator( $mutate_notification );
		$email->send();
		$this->assertSame( 'tasti', $phpmailer->Subject );

		unset( $phpmailer );
	}
}