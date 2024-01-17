<?php
/**
 * Unit tests calmPress code in the WP_User class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email_Address;
use calmpress\email\User_Email_Change_Verification_Email_Mutator;
use calmpress\email\User_Email_Change_Verification_Email;
use calmpress\email\User_Email_Change_Undo_Email_Mutator;
use calmpress\email\User_Email_Change_Undo_Email;
use calmpress\email\User_Activation_Verification_Email_Mutator;
use calmpress\email\User_Activation_Verification_Email;
use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;
use calmpress\email\Abort_Send_Exception;

/**
 * An implementation of an User_Activation_Verification_Email_Mutator interface to use in testing.
 */
class Mock_Activation_Mutator implements User_Activation_Verification_Email_Mutator {

	public User_Activation_Verification_Email $email;

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( User_Activation_Verification_Email &$email ):void {
		$this->email = $email;
		throw new Abort_Send_Exception();
	}
}

/**
 * An implementation of an User_Email_Change_Verification_Email_Mutator interface to use in testing.
 */
class Mock_Verifiction_Mutator implements User_Email_Change_Verification_Email_Mutator {

	public User_Email_Change_Verification_Email $email;

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( User_Email_Change_Verification_Email &$email ):void {
		$this->email = $email;
		throw new Abort_Send_Exception();
	}
}

/**
 * An implementation of an User_Email_Change_Undo_Email_Mutator interface to use in testing.
 */
class Mock_Undo_Mutator implements User_Email_Change_Undo_Email_Mutator {

	public User_Email_Change_Undo_Email $email;

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( User_Email_Change_Undo_Email &$email ):void {
		$this->email = $email;
		throw new Abort_Send_Exception();
	}
}

/**
 * Tests for the code in WP_User class.
 * 
 * @since 1.0.0
 */
class WP_User_Test extends WP_UnitTestCase {

	/**
	 * Test email_address.
	 *
	 * @since 1.0.0
	 */
	public function test_email_address() {

		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );
		$user->display_name = 'miki';
		$user->user_email   = 'jack@example.com';

		$email = $user->email_address();

		$this->assertSame( 'miki', $email->name );
		$this->assertSame( 'jack@example.com', $email->address );
	}

	/**
	 * Test email_change_expired
	 *
	 * @since 1.0.0
	 */
	public function test_email_change_expired() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );
        $method = new ReflectionMethod( '\WP_User', 'email_change_expired' );

		// No meta return true.
        $this->assertTrue( $method->invoke( $user ) );

		// Meta has expired time, retun false and clean db
		update_user_meta( $user_id, 'change_email_expiry', time() - 100 );
		update_user_meta( $user_id, 'original_email', 'junk' );
		update_user_meta( $user_id, 'new_email', 'test' );
        $this->assertTrue( $method->invoke( $user ) );
		$this->assertEmpty( get_user_meta( $user_id, 'change_email_expiry', true ) );
		$this->assertEmpty( get_user_meta( $user_id, 'original_email', true ) );
		$this->assertEmpty( get_user_meta( $user_id, 'new_email', true ) );

		// non expired return false.
		$user->change_email( new Email_address( 'change@example.com' ) );
        $this->assertFalse( $method->invoke( $user ) );

		// and make sure no meta is deleted.
		$this->assertNotEmpty( get_user_meta( $user_id, 'change_email_expiry', true ) );
		$this->assertNotEmpty( get_user_meta( $user_id, 'original_email', true ) );
		$this->assertNotEmpty( get_user_meta( $user_id, 'new_email', true ) );
	}

	/**
	 * Test email_from_meta.
	 *
	 * @since 1.0.0
	 */
	public function test_email_from_meta() {

		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );
		$user->display_name = 'jack';
        $method = new ReflectionMethod( '\WP_User', 'email_from_meta' );

		// normal flow.
		update_user_meta( $user_id, 'test', 'jack@example.com' );
		
		// Expiry need to be set as well.
		update_user_meta( $user_id, 'change_email_expiry', time() + 100 );

		$email = $method->invoke( $user, 'test', 'message' );

		$this->assertSame( 'jack', $email->name );
		$this->assertSame( 'jack@example.com', $email->address );

		// Exception thrown when meta do not contain value.
		$thrown = true;
		try {
			$email = $method->invoke( $user, 'nothing', 'message' );
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'message', $e->getMessage() );
		}
		$this->assertTrue( $thrown );

		// Exception thrown when change expried.
		$thrown = true;
		try {
			update_user_meta( $user_id, 'change_email_expiry', time() - 100 );
			$email = $method->invoke( $user, 'nothing', 'message' );
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'message', $e->getMessage() );
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * test user_from_encrypted_string.
	 */
	function test_user_from_encrypted_string() {
		// Test when user actually exists.
		$user_id = $this->factory->user->create();
		$str = \calmpress\utils\encrypt_int_to_base64( $user_id, time() + 100 );

		$user = WP_User::user_from_encrypted_string( $str );
		$this->assertSame( $user_id, $user->ID );

		// Bad encryption gets a null.
		$user = WP_User::user_from_encrypted_string( 'random' );
		$this->assertNull( $user );

		// Nonexisting user gets a null.
		$str = \calmpress\utils\encrypt_int_to_base64( 66666, time() + 100 );

		$user = WP_User::user_from_encrypted_string( $str );
		$this->assertNull( $user );

		// Expired nonce gets a null.
		$str = \calmpress\utils\encrypt_int_to_base64( $user_id, time() - 100 );

		$user = WP_User::user_from_encrypted_string( $str );
		$this->assertNull( $user );
	}

	/**
	 * test email_change_in_progress
	 */
	public function test_email_change_in_progress() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// For pending users always true.
		$user->set_role( 'pending_activation' );
		$this->assertFalse( $user->email_change_in_progress() );

		$user->set_role( 'subscriber' );
		$user->user_email = 'old@example.com';

		// No change in progress.
		$this->assertFalse( $user->email_change_in_progress() );

		// Now under change.
		$verification_mutator = new Mock_Verifiction_Mutator();
		calmpress\email\User_Email_Change_Verification_Email::register_mutator( $verification_mutator );
		$undo_mutator = new Mock_Undo_Mutator();
		calmpress\email\User_Email_Change_Undo_Email::register_mutator( $undo_mutator );

		$user->change_email( new Email_address( 'new@example.com' ) );
		$this->assertTrue( $user->email_change_in_progress() );

		// Under change while it is possible to undo.
		$user->approve_new_email();
		$this->assertTrue( $user->email_change_in_progress() );

		// Cleanup of global state.
		calmpress\email\User_Email_Change_Verification_Email::remove_all_mutation_observers();
		calmpress\email\User_Email_Change_Undo_Email::remove_all_mutation_observers();
	}

	/**
	 * test change_email
	 */
	function test_change_email() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// For pending users an activation should be sent to new email.
		$user->set_role( 'pending_activation' );
		$activation_mutator = new Mock_activation_Mutator();
		calmpress\email\User_Activation_Verification_Email::register_mutator( $activation_mutator );

		// need to set the email address on the object as it is assumed that at this point
		// DB changes where done.
		$user->user_email = 'new@example.com';
		$user->change_email( new Email_address( 'change@example.com' ) );
		$tos = $activation_mutator->email->email->to_addresses();
		$this->assertSame( 1, count( $tos ) );
		$this->assertSame( 'new@example.com', $tos[0]->address );

		// For active users one email (undo) should be sent to curent address
		// and another (confirmation) should be sent to new email.
		$user->set_role( 'subscriber' );
		$user->user_email = 'old@example.com';

		$verification_mutator = new Mock_Verifiction_Mutator();
		calmpress\email\User_Email_Change_Verification_Email::register_mutator( $verification_mutator );
		$undo_mutator = new Mock_Undo_Mutator();
		calmpress\email\User_Email_Change_Undo_Email::register_mutator( $undo_mutator );

		$user->change_email( new Email_address( 'new@example.com' ) );

		$tos = $verification_mutator->email->email->to_addresses();
		$this->assertSame( 1, count( $tos ) );
		$this->assertSame( 'new@example.com', $tos[0]->address );

		$tos = $undo_mutator->email->email->to_addresses();
		$this->assertSame( 1, count( $tos ) );
		$this->assertSame( 'old@example.com', $tos[0]->address );

		// Trying to change to different email before earlier change had expired
		// throws exception.
		$thrown = true;
		try {
			$user->change_email( new Email_address( 'new2@example.com' ) );
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );

		// ... but not when same email is used.
		$user->change_email( new Email_address( 'new@example.com' ) );

		// Cleanup of global state.
		calmpress\email\User_Email_Change_Verification_Email::remove_all_mutation_observers();
		calmpress\email\User_Email_Change_Undo_Email::remove_all_mutation_observers();
	}

	/**
	 * test cancel_email_change
	 */
	public function test_cancel_email_change() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		$user->user_email = 'original@a.com';
		$user->change_email( new Email_address( 'change@example.com' ) );
		$user->cancel_email_change();

		// Expect exception to be thrown as the state is no change.
		$thrown = true;
		try {
			$user->changed_email_into();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * test changed_email_into
	 */
	function test_changed_email_into() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// Exception thrown when no change.
		$thrown = true;
		try {
			$user->changed_email_into();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );

		$user->user_email = 'original@a.com';
		$user->change_email( new Email_address( 'change@example.com' ) );

		$this->assertSame( 'change@example.com', $user->changed_email_into()->address );
	}

	/**
	 * test changed_email_from
	 */
	function test_changed_email_from() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// Exception thrown when no change.
		$thrown = true;
		try {
			$user->changed_email_from();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );

		$user->user_email = 'original@a.com';
		$user->change_email( new Email_address( 'change@example.com' ) );

		$this->assertSame( 'original@a.com', $user->changed_email_from()->address );
	}

	/**
	 * test approve_new_email
	 */
	function test_approve_new_email() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// Exception thrown when no change.
		$thrown = true;
		try {
			$user->approve_new_email();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );

		$user->user_email = 'original@a.com';
		$user->change_email( new Email_address( 'change@example.com' ) );
		$user->approve_new_email();

		// Refresh user to be sure we test the value in DB.
		$user = get_user_by( 'id', $user_id );
		$this->assertSame( 'change@example.com', $user->user_email );

		// Original is saved.
		$this->assertSame( 'original@a.com', $user->changed_email_from()->address );
	
		// DB cleaned.
		$thrown = true;
		try {
			$user->changed_email_into();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * test undo_change_email
	 */
	function test_undo_change_email() {
		$user_id = $this->factory->user->create();
		$user    = get_user_by( 'id', $user_id );

		// Exception thrown when no change.
		$thrown = true;
		try {
			$user->undo_change_email();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );

		$user->user_email = 'original@a.com';
		$user->change_email( new Email_address( 'change@example.com' ) );
		$user->undo_change_email();

		// Refresh user to be sure we test the value in DB.
		$user = get_user_by( 'id', $user_id );
		$this->assertSame( 'original@a.com', $user->user_email );

		// DB cleaned.
		$thrown = true;
		try {
			$user->changed_email_into();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );

		$thrown = true;
		try {
			$user->changed_email_from();
			$thrown = false;
		} catch ( \RuntimeException $e ) {
			;
		}
		$this->assertTrue( $thrown );
	}
}