<?php
/**
 * Unit tests covering Email_Attachment_Attachment class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

require_once ABSPATH . '/wp-admin/includes/image.php';

use calmpress\email\Email_Attachment_Attachment;

class Email_Attachment_Attachment_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set attachment property and throws
	 * exception for non attachment posts and when the file in them do not
	 * exist.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$attachment_property = new ReflectionProperty( 'calmpress\email\Email_Attachment_Attachment', 'attachment' );
        $attachment_property->setAccessible(true);

		$title_property = new ReflectionProperty( 'calmpress\email\Email_Attachment_Attachment', 'title' );
        $title_property->setAccessible(true);

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$attachment = get_post( $attachment_id );

		// common use.
		$t = new Email_Attachment_Attachment( $attachment, " \r\n o\tp\r\nsi tester \r\n" );
		$this->assertSame( $attachment, $attachment_property->getValue( $t ) );
		$this->assertSame( "o\tp si tester", $title_property->getValue( $t ) );

		// Non attachment post throws exception.
		$thrown = false;
		$post_id = $this->factory->post->create();

		try {
			$t = new Email_Attachment_Attachment( get_post( $post_id ) );
		} catch ( \Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// Attachment for which file do not exists throws exception.
		$thrown = false;
		unlink( get_attached_file( $attachment_id ) );

		try {
			$t = new Email_Attachment_Attachment( $attachment );
		} catch ( \Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// cleanup 
		wp_delete_post( $attachment_id, true );
	}

	/**
	 * Test the path method
	 *
	 * @since 1.0.0
	 */
	public function test_path() {
		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$attachment = get_post( $attachment_id );

		$t = new Email_Attachment_Attachment( $attachment );
		$this->assertSame( get_attached_file( $attachment_id ), $t->path() );

		wp_delete_post( $attachment_id, true );
	}

	/**
	 * Test the attachment method
	 *
	 * @since 1.0.0
	 */
	public function test_attachment() {
		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$attachment = get_post( $attachment_id );

		$t = new Email_Attachment_Attachment( $attachment );
		$this->assertSame( $attachment_id, $t->attachment()->ID );

		wp_delete_post( $attachment_id, true );
	}

	/**
	 * Test the title method.
	 *
	 * @since 1.0.0
	 */
	public function test_title() {
		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		$attachment = get_post( $attachment_id );

		$t = new Email_Attachment_Attachment( $attachment );
		$this->assertSame( $attachment->post_title, $t->title() );

		$t = new Email_Attachment_Attachment( $attachment, 'plain' );
		$this->assertSame( 'plain', $t->title() );

		wp_delete_post( $attachment_id, true );
	}
}