<?php
/**
 * Unit tests covering User_Magic_Login_Email class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\User_One_Time_Password_Email_Mutator;
use calmpress\email\User_One_Time_Password_Email;
use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;
use calmpress\utils\One_Time_Password;

require_once __DIR__ . '/../../../includes/dummy-phpmailer.php';

/**
 * An implementation of an Email_Address_Change_Notification_Email_Mutator interface to use in testing.
 */
class Mock_OTP_Observer implements User_One_Time_Password_Email_Mutator {

	public string $value;

	public function __construct( string $value ) {
		$this->value = $value; 
	}

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( User_One_Time_Password_Email &$email ):void {
		$email->email->set_subject( $this->value );
	}
}

class User_One_Time_Password_Email_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set the properties
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$user_id = $this->factory->user->create();
		$user = get_user_by( 'id', $user_id );

		$password = One_Time_Password::new( 60 );
		$email = new User_One_Time_Password_Email( $user, $password );
		$this->assertSame( $user, $email->user );
		$this->assertSame( $password, $email->password );
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

		$password = One_Time_Password::new( 60 );
		$email = new User_One_Time_Password_Email( $user, $password );

		$email->send();

		// Test mail is sent to the user's address
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
	
		$password = One_Time_Password::new( 60 );
		$email = new User_One_Time_Password_Email( $user, $password );

		// Test the specific notification mutators.
		$mutate_notification = new Mock_OTP_Observer( 'tasti' );
		User_One_Time_Password_Email::register_mutator( $mutate_notification );
		$email->send();
		$this->assertSame( 'tasti', $phpmailer->Subject );

		unset( $phpmailer );
	}
}