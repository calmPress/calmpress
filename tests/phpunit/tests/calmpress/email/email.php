<?php
/**
 * Unit tests covering Email class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email;
use calmpress\email\Email_Address;
use calmpress\email\Email_Attachment_File;
use calmpress\email\Email_Mutator;
use calmpress\observer\Observer;
use calmpress\observer\Observer_Priority;

require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
require_once ABSPATH . WPINC . '/PHPMailer/SMTP.php';
require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';

/**
 * An implementation of an No_Parameters_Progress_Observer interface to use in testing.
 */
class Mock_Email_Mutation_Observer implements Email_Mutator {

	public int    $priority = 0;
	public string $value;

	public function __construct( int $priority, string $value ) {
		$this->priority = $priority;
		$this->value    = $value; 
	}

	public function notification_dependency_with( Observer $observer ) : Observer_Priority {
		if ( $this->priority < $observer->priority ) {
			return Observer_Priority::BEFORE;
		}

		if ( $observer->priority < $this->priority ) {
			return Observer_Priority::AFTER;
		}

		return Observer_Priority::NONE;
	}

	public function mutate_by_ref( Email &$email ):void {
		$email->set_subject( $email->subject() . $this->value );
	}
}

/**
 * Used to create "PHPMailer" objects which do not actually try to send
 * mail.
 */
class dummy_PHPMailer extends \PHPMailer\PHPMailer\PHPMailer {

	public $Mailer = 'dummy';

	/**
	 * hold the extracted subject out of the headers.
	 */
//	public string $Subject;

	/**
	 * Use the constructor to change validator to the one
	 * set by wp_mail.
	 */
	public function __construct() {
		self::$validator = static function ( $email ) {
			return (bool) is_email( $email );
		};

		parent::__construct( true );
	}

	/**
	 * Need to override this to avoid setting mailer back to mail.
	 */
	public function isMail() {}

	/**
	 * just don't send anything.
	 */
	public function dummySend( $headers_str, $content ) {
	}
}

class Email_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set the properties
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$subject_property = new ReflectionProperty( 'calmpress\email\Email', 'subject' );
        $subject_property->setAccessible(true);

		$internal_type_property = new ReflectionProperty( 'calmpress\email\Email', 'internal_type' );
        $internal_type_property->setAccessible(true);

		$content_property = new ReflectionProperty( 'calmpress\email\Email', 'content' );
        $content_property->setAccessible(true);

		$content_is_html_property = new ReflectionProperty( 'calmpress\email\Email', 'content_is_html' );
        $content_is_html_property->setAccessible(true);

		$to_property = new ReflectionProperty( 'calmpress\email\Email', 'to' );
        $to_property->setAccessible(true);

		// common use.
		$address = new Email_Address( 'a@b.com' );
		$t = new Email(
			" subj\r\nect ", // Make sure trimming and sanitization happen.
			'content',
			true,
			'some_type',
			$address
		);
		$this->assertSame( 'subject', $subject_property->getValue( $t ) );
		$this->assertSame( 'content', $content_property->getValue( $t ) );
		$this->assertTrue( $content_is_html_property->getValue( $t ) );
		$this->assertSame( 'some_type', $internal_type_property->getValue( $t ) );
		$this->assertSame( 1, count( $to_property->getValue( $t ) ) );
		$this->assertSame( $address, $to_property->getValue( $t )[0] );
	}

	/**
	 * Test the subject method.
	 *
	 * @since 1.0.0
	 */
	public function test_subject() {
		$t = new Email(
			" test\n subject",
			'',
			true,
			''
		);
		$this->assertSame( 'test subject', $t->subject() );
	}

	/**
	 * Test the internal_type method.
	 *
	 * @since 1.0.0
	 */
	public function test_internal_type() {
		$t = new Email(
			'',
			'testo',
			false,
			'some random type'
		);
		$this->assertSame( 'some random type', $t->internal_type() );
	}

	/**
	 * Test the set_subject method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_subject() {
		$t = new Email(
			'',
			'',
			true,
			''
		);

		$t->set_subject( 'new subject' );
		$this->assertSame( 'new subject', $t->subject() );

		// Strip \r
		$t->set_subject( "r subject\r" );
		$this->assertSame( 'r subject', $t->subject() );

		// Strip \n
		$t->set_subject( "\na subject\r" );
		$this->assertSame( 'a subject', $t->subject() );

		// Trim leading and trailing space.
		$t->set_subject( " t\n subject\r " );
		$this->assertSame( 't subject', $t->subject() );
	}

	/**
	 * Test the content method.
	 *
	 * @since 1.0.0
	 */
	public function test_content() {
		$t = new Email(
			'',
			'testo',
			true,
			''
		);
		$this->assertSame( 'testo', $t->content() );
	}

	/**
	 * Test the content_is_html method.
	 *
	 * @since 1.0.0
	 */
	public function test_content_is_html() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);
		$this->assertFalse( $t->content_is_html() );
	}

	/**
	 * Test the set_content method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_content() {
		$t = new Email(
			'',
			'',
			true,
			''
		);

		$t->set_content( 'new content', true );
		$this->assertSame( 'new content', $t->content() );
		$this->assertTrue( $t->content_is_html() );

		$t->set_content( 'new contents', false );
		$this->assertSame( 'new contents', $t->content() );
		$this->assertFalse( $t->content_is_html() );
	}

	/**
	 * Test the to_addresses method.
	 *
	 * @since 1.0.0
	 */
	public function test_to_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$this->assertSame( 0, count( $t->to_addresses() ) );

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );
		$t = new Email(
			'',
			'testo',
			false,
			'',
			$address1,
			$address2
		);

		$this->assertSame( 2, count( $t->to_addresses() ) );
		// Hmm not great way to compare arrays but for now good enough.
		$this->assertSame( $address1, $t->to_addresses()[0] );
		$this->assertSame( $address2, $t->to_addresses()[1] );
	}

	/**
	 * Test the set_to_addresses method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_to_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->set_to_addresses( $address1, $address2 );
		$this->assertSame( 2, count( $t->to_addresses() ) );
		$this->assertSame( $address1, $t->to_addresses()[0] );
		$this->assertSame( $address2, $t->to_addresses()[1] );

		$t->set_to_addresses();
		$this->assertSame( 0, count( $t->to_addresses() ) );
	}

	/**
	 * Test the add_to_address method.
	 *
	 * @since 1.0.0
	 */
	public function test_add_to_address() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->add_to_address( $address1 );
		$this->assertSame( 1, count( $t->to_addresses() ) );
		$this->assertSame( $address1, $t->to_addresses()[0] );

		$t->add_to_address( $address2 );
		$this->assertSame( 2, count( $t->to_addresses() ) );
		$this->assertSame( $address1, $t->to_addresses()[0] );
		$this->assertSame( $address2, $t->to_addresses()[1] );
	}

	/**
	 * Test the cc_addresses and cc_add_address methods
	 *
	 * @since 1.0.0
	 */
	public function test_cc_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$this->assertSame( 0, count( $t->cc_addresses() ) );

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->add_cc_address( $address1 );
		$this->assertSame( 1, count( $t->cc_addresses() ) );
		$this->assertSame( $address1, $t->cc_addresses()[0] );

		$t->add_cc_address( $address2 );
		$this->assertSame( 2, count( $t->cc_addresses() ) );
		$this->assertSame( $address1, $t->cc_addresses()[0] );
		$this->assertSame( $address2, $t->cc_addresses()[1] );
	}

	/**
	 * Test the set_cc_addresses method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_cc_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->set_cc_addresses( $address1, $address2 );
		$this->assertSame( 2, count( $t->cc_addresses() ) );
		$this->assertSame( $address1, $t->cc_addresses()[0] );
		$this->assertSame( $address2, $t->cc_addresses()[1] );

		$t->set_cc_addresses();
		$this->assertSame( 0, count( $t->cc_addresses() ) );
	}

	/**
	 * Test the bcc_addresses and bcc_add_address methods
	 *
	 * @since 1.0.0
	 */
	public function test_bcc_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$this->assertSame( 0, count( $t->bcc_addresses() ) );

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->add_bcc_address( $address1 );
		$this->assertSame( 1, count( $t->bcc_addresses() ) );
		$this->assertSame( $address1, $t->bcc_addresses()[0] );

		$t->add_bcc_address( $address2 );
		$this->assertSame( 2, count( $t->bcc_addresses() ) );
		$this->assertSame( $address1, $t->bcc_addresses()[0] );
		$this->assertSame( $address2, $t->bcc_addresses()[1] );
	}

	/**
	 * Test the set_bcc_addresses method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_bcc_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->set_bcc_addresses( $address1, $address2 );
		$this->assertSame( 2, count( $t->bcc_addresses() ) );
		$this->assertSame( $address1, $t->bcc_addresses()[0] );
		$this->assertSame( $address2, $t->bcc_addresses()[1] );

		$t->set_bcc_addresses();
		$this->assertSame( 0, count( $t->bcc_addresses() ) );
	}

	/**
	 * Test the reply_to_addresses and add_reply_to_address methods
	 *
	 * @since 1.0.0
	 */
	public function test_reply_to_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$this->assertSame( 0, count( $t->reply_to_addresses() ) );

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->add_reply_to_address( $address1 );
		$this->assertSame( 1, count( $t->reply_to_addresses() ) );
		$this->assertSame( $address1, $t->reply_to_addresses()[0] );

		$t->add_reply_to_address( $address2 );
		$this->assertSame( 2, count( $t->reply_to_addresses() ) );
		$this->assertSame( $address1, $t->reply_to_addresses()[0] );
		$this->assertSame( $address2, $t->reply_to_addresses()[1] );
	}

	/**
	 * Test the set_reply_to_addresses method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_reply_to_addresses() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$address1 = new Email_Address( 'a@b.com' );
		$address2 = new Email_Address( 'b@b.com' );

		$t->set_reply_to_addresses( $address1, $address2 );
		$this->assertSame( 2, count( $t->reply_to_addresses() ) );
		$this->assertSame( $address1, $t->reply_to_addresses()[0] );
		$this->assertSame( $address2, $t->reply_to_addresses()[1] );

		$t->set_reply_to_addresses();
		$this->assertSame( 0, count( $t->reply_to_addresses() ) );
	}

	/**
	 * Test sender and set_sender methods
	 *
	 * @since 1.0.o
	 */
	public function test_sender() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		// Empty after object creation.
		$this->assertNull( $t->sender() );

		// Configure sender successfully.
		$sender = new Email_Address( 'a@b.com' );
		$t->set_sender( $sender );
		$this->assertSame( $sender, $t->sender() );

		// Reset sender configuration.
		$t->set_sender( null );
		$this->assertNull( $t->sender() );
	}

	/**
	 * Test sender and set_sender methods
	 *
	 * @since 1.0.o
	 */
	public function test_bounce() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		// Empty after object creation.
		$this->assertSame( '', $t->bounce_address() );

		// Configure bounce successfully while trimming and sanitizing .
		$t->set_bounce_address( " bou\nnce@a.com  " );
		$this->assertSame( 'bounce@a.com', $t->bounce_address() );

		// Empty string is an exception for eemail address validation.
		$t->set_bounce_address( '' );
		$this->assertSame( '', $t->bounce_address() );

		// Exception on invalid address
		$thrown = false;
		try {
			$t->set_bounce_address( "test" );
		} catch ( \RuntimeException $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * Test the attachments and add_attachment methods
	 *
	 * @since 1.0.0
	 */
	public function test_attachments() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$this->assertSame( 0, count( $t->attachments() ) );

		$attachment1 = new Email_Attachment_File( __FILE__ );
		$attachment2 = new Email_Attachment_File( __FILE__ );

		$t->add_attachment( $attachment1 );
		$this->assertSame( 1, count( $t->attachments() ) );
		$this->assertSame( $attachment1, $t->attachments()[0] );

		$t->add_attachment( $attachment2 );
		$this->assertSame( 2, count( $t->attachments() ) );
		$this->assertSame( $attachment1, $t->attachments()[0] );
		$this->assertSame( $attachment2, $t->attachments()[1] );
	}

	/**
	 * Test the set_attachments method.
	 *
	 * @since 1.0.0
	 */
	public function test_set_attachments() {
		$t = new Email(
			'',
			'testo',
			false,
			''
		);

		$attachment1 = new Email_Attachment_File( __FILE__ );
		$attachment2 = new Email_Attachment_File( __FILE__ );

		$t->set_attachments( $attachment1, $attachment2 );
		$this->assertSame( 2, count( $t->attachments() ) );
		$this->assertSame( $attachment1, $t->attachments()[0] );
		$this->assertSame( $attachment2, $t->attachments()[1] );

		$t->set_attachments();
		$this->assertSame( 0, count( $t->attachments() ) );
	}

	/**
	 * test iterate_objects_method_into_array method
	 *
	 * @since 1.0.0
	 */
	public function test_iterate_objects_method_into_array() {
		$method = new ReflectionMethod( 'calmpress\email\Email', 'iterate_objects_method_into_array' );
        $method->setAccessible(true);
		// Could have been any kind of object but since we in email context...

		$ad1  = new Email_Address( 'a@b.com' );
		$ad2  = new Email_Address( 'c@d.org' );
		$test = $method->invoke( null, [ $ad1, $ad2 ], 'address' );
		$this->assertSame( 'a@b.com', $test[0] );
		$this->assertSame( 'c@d.org', $test[1] );
	}

	/**
	 * test iterate_objects_method_into_list method
	 *
	 * @since 1.0.0
	 */
	public function test_iterate_objects_method_into_list() {
		$method = new ReflectionMethod( 'calmpress\email\Email', 'iterate_objects_method_into_list' );
        $method->setAccessible(true);
		// Could have been any kind of object but since we in email context...

		$ad1  = new Email_Address( 'a@b.com', 'a' );
		$ad2  = new Email_Address( 'c@d.org', 'b' );
		$test = $method->invoke( null, [ $ad2, $ad1 ], 'name' );
		$this->assertSame( 'b,a', $test );
	}

	/**
	 * Test send method.
	 * 
	 * Test checks that the properties of the phpmailer being set correctly.
	 */
	public function test_send() {
		global $phpmailer;
		$phpmailer = new dummy_PHPMailer();

		$t = new Email(
			'subject',
			'testo',
			true,
			'',
			new Email_Address( 'test@test.com', 'hi"t' ),
			new Email_Address( 'second@test.com' ),
		);

		$t->send();

		// Test PHPMailer is set based on parameters in constructor.
		$this->assertSame( 'subject', $phpmailer->Subject );
		// Addresses depend on the internal structure of PHPMailer.
		$tos = $phpmailer->getToAddresses();
		$this->assertSame( 2, count( $tos ) );
		$this->assertSame( 'test@test.com', $tos[0][0] );
		$this->assertSame( 'hi"t', $tos[0][1] );
		$this->assertSame( 'second@test.com', $tos[1][0] );
		$this->assertSame( '', $tos[1][1] );

		$this->assertSame( 'text/html', $phpmailer->ContentType );

		$this->assertSame( 'testo', $phpmailer->Body );

		// Test different content type.
		$t->set_content( 'text', false );
		$t->send();
		$this->assertSame( 'text/plain', $phpmailer->ContentType );

		// Test CC.
		$this->assertSame( 0, count( $phpmailer->getCCAddresses() ) );
		$t->set_cc_addresses(
			new Email_Address( 'cc1@test.com' ),
			new Email_Address( 'cc2@test.com', 'name' ),
		);

		$t->send();
		$cc = $phpmailer->getCCAddresses();
		$this->assertSame( 2, count( $cc ) );
		$this->assertSame( 'cc1@test.com', $cc[0][0] );
		$this->assertSame( '', $cc[0][1] );
		$this->assertSame( 'cc2@test.com', $cc[1][0] );
		$this->assertSame( 'name', $cc[1][1] );

		// Test BCC.
		$this->assertSame( 0, count( $phpmailer->getBCCAddresses() ) );
		$t->set_bcc_addresses(
			new Email_Address( 'bcc1@test.com', ' some name ' ),
			new Email_Address( 'bcc2@test.com', 'name' ),
		);

		$t->send();
		$bcc = $phpmailer->getBCCAddresses();
		$this->assertSame( 2, count( $bcc ) );
		$this->assertSame( 'bcc1@test.com', $bcc[0][0] );
		$this->assertSame( 'some name', $bcc[0][1] );
		$this->assertSame( 'bcc2@test.com', $bcc[1][0] );
		$this->assertSame( 'name', $bcc[1][1] );		

		// Test Reply-To.
		$this->assertSame( 0, count( $phpmailer->getReplyToAddresses() ) );
		$t->set_reply_to_addresses(
			new Email_Address( 'rt1@test.com', ' rt name ' ),
			new Email_Address( 'rt2@test.com', 'name' ),
		);

		$t->send();
		$rt = $phpmailer->getReplyToAddresses();
		// Internal PHPMailer addresses are different here (in actually good way).
		$this->assertSame( 2, count( $rt ) );
		$this->assertSame( 'rt1@test.com', $rt['rt1@test.com'][0] );
		$this->assertSame( 'rt name', $rt['rt1@test.com'][1] );
		$this->assertSame( 'rt2@test.com', $rt['rt2@test.com'][0] );
		$this->assertSame( 'name', $rt['rt2@test.com'][1] );	

		// Test Return-Path/bounce address.
		$this->assertSame( '', $phpmailer->Sender );

		$t->set_bounce_address( 'bounce@bounce.com' );
		$t->send();
		$this->assertSame( 'bounce@bounce.com', $phpmailer->Sender );

		// Test sender AKA From.
		$t->set_sender( new Email_Address( 'admin@test.com', 'Site Admin' ) );
		$t->send();
		$this->assertSame( 'admin@test.com', $phpmailer->From );
		$this->assertSame( 'Site Admin', $phpmailer->FromName );

		// Test attachments.
		$this->assertSame( 0, count( $phpmailer->getAttachments() ) );
		$t->add_attachment( new Email_Attachment_File( __FILE__ ) );
		$t->add_attachment( new Email_Attachment_File( __FILE__, ' test title' ) );
		$t->send();
		$at = $phpmailer->getAttachments();
		$this->assertSame( 2, count( $at ) );
		$this->assertSame( __FILE__, $at[0][0] );
		// Not defined well, but what PHPMailer actually does when no title is given.
		$this->assertSame( 'email.php', $at[0][7] );
		$this->assertSame( __FILE__, $at[1][0] );
		$this->assertSame( 'test title', $at[1][7] );

		unset( $phpmailer );
	}

	/**
	 * Test mutators.
	 */
	public function test_mutators() {
		$mutate1 = new Mock_Email_Mutation_Observer( 2, ' second');
		$mutate2 = new Mock_Email_Mutation_Observer( 1, ' first');
		Email::register_mutator( $mutate1 );
		Email::register_mutator( $mutate2 );

		global $phpmailer;
		$phpmailer = new dummy_PHPMailer();

		$t = new Email(
			'subject',
			'testo',
			true,
			''
		);

		$t->send();
		$this->assertSame( 'subject first second', $t->subject() );
		unset( $phpmailer );
	}
}