<?php
/**
 * Unit tests covering Installer_Email_Verification_Email class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email;
use calmpress\email\Email_Address;
use calmpress\email\Installer_Email_Verification_Email_Mutator;
use calmpress\email\Installer_Email_Verification_Email;
use calmpress\email\Email_Mutator;
use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;

require_once __DIR__ . '/../../../includes/dummy-phpmailer.php';

/**
 * An implementation of an Email_Address_Change_Notification_Email_Mutator interface to use in testing.
 */
class Mock_Installer_Verification_Observer implements Installer_Email_Verification_Email_Mutator {

	public string $value;

	public function __construct( string $value ) {
		$this->value = $value; 
	}

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( Installer_Email_Verification_Email &$email ):void {
		$email->email->set_subject( $this->value );
	}
}

class Installer_Email_Verification_Email_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set the properties
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$user_id = $this->factory->user->create();
		$user = get_user_by( 'id', $user_id );

		$email = new Installer_Email_Verification_Email( $user );
		$this->assertSame( $user, $email->user );
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
		$user->user_email = 'test@example.com';

		$email = new Installer_Email_Verification_Email( $user );

		$email->send();

		// Test mail is sent to the old address
		$tos = $phpmailer->getToAddresses();
		$this->assertSame( 1, count( $tos ) );
		$this->assertSame( 'test@example.com', $tos[0][0] );

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
	
		$email = new Installer_Email_Verification_Email( $user );

		// Test the specific notification mutators.
		$mutate_notification = new Mock_Installer_Verification_Observer( 'tasti' );
		Installer_Email_Verification_Email::register_mutator( $mutate_notification );
		$email->send();
		$this->assertSame( 'tasti', $phpmailer->Subject );

		unset( $phpmailer );
	}
}