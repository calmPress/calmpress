<?php
/**
 * Unit tests covering the Email_To_User trait.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email_To_User;
use calmpress\email\Email;
use calmpress\email\Email_Address;
use calmpress\observer\Observer_Priority;
use calmpress\observer\Observer;
use calmpress\email\Abort_Send_Exception;

require_once __DIR__ . '/../../../includes/dummy-phpmailer.php';

class Mock_Email_To_user {
	use Email_To_User;

	public function __construct( \WP_User $user ) {
		$this->user  = $user;
		$this->email = new Email( 'subject', '', false, $user->email_address() );
	}

	public static function register_observer( Mock_Email_To_User_Mutator $observer ) {
		self::add_observer( $observer );
	}
}

/**
 * An implementation of an Email_Address_Change_Notification_Email_Mutator interface to use in testing.
 */
class Mock_Email_To_User_Mutator implements \calmpress\observer\Observer {

	public string $value;

	public function __construct( string $value ) {
		$this->value = $value; 
	}

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( Mock_Email_To_user &$email ):void {
		$email->email->set_subject( $this->value );
	}
}

class Mock_Mutatot_With_Abort_Send extends Mock_Email_To_User_Mutator {
	public function mutate_by_ref( Mock_Email_To_user &$email ):void {
		throw new Abort_Send_Exception();
	}
}

class Email_To_user_Test extends WP_UnitTestCase {

	/**
	 * Test the send method
	 *
	 * @since 1.0.0
	 */
	public function test_send() {
		global $phpmailer;
		$phpmailer = new dummy_PHPMailer();

		$user_id = $this->factory->user->create();
		$user = get_user_by( 'id', $user_id );
		$user->user_email = 'test@example.com';

		$email = new Mock_Email_To_user( $user );

		$email->send();

		// Test mail is sent to the user's address
		$tos = $phpmailer->getToAddresses();
		$this->assertSame( 1, count( $tos ) );
		$this->assertSame( 'test@example.com', $tos[0][0] );

		// Test mutators activated.
		Mock_Email_To_user::register_observer( new Mock_Email_To_User_Mutator( 'test subject' ) );
		$email->send();

		$this->assertSame( 'test subject', $phpmailer->Subject );

		// Test raising an abort exception prevent send of the mail.
		$phpmailer = new dummy_PHPMailer();
		Mock_Email_To_user::register_observer( new Mock_Mutatot_With_Abort_Send( 'exception' ) );
		$email->send();
		// Empty string indicates that send was not completed.
		$this->assertSame( '', $phpmailer->Subject );

		unset( $phpmailer );
	}

}