<?php
/**
 * Unit tests covering Email_Attachment_File class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email_Attachment_File;

class Email_Attachment_File_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set the path and name properties
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$path_property = new ReflectionProperty( 'calmpress\email\Email_Attachment_File', 'path' );
        $path_property->setAccessible(true);

		$title_property = new ReflectionProperty( 'calmpress\email\Email_Attachment_File', 'title' );
        $title_property->setAccessible(true);

		// common use.
		$t = new Email_Attachment_File( __FILE__ );
		$this->assertSame( __FILE__, $path_property->getValue( $t ) );
		$this->assertSame( '', $title_property->getValue( $t ) );

		// Non existing file throws exception.
		$thrown = false;
		try {
			$t = new Email_Attachment_File( 'bad.name.of.file' );
		} catch ( \Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// Name sanitization.
		$t = new Email_Attachment_File( __FILE__, " \r\n o\tp\r\nsi tester \r\n" );
		$this->assertSame( "o\tp si tester", $title_property->getValue( $t ) );

	}

	/**
	 * Test the path method.
	 *
	 * @since 1.0.0
	 */
	public function test_path() {
		$t = new Email_Attachment_File( __FILE__ );
		$this->assertSame( __FILE__, $t->path() );
	}

	/**
	 * Test the title method.
	 *
	 * @since 1.0.0
	 */
	public function test_title() {
		$t = new Email_Attachment_File( __FILE__, ' attachment title ' );
		$this->assertSame( 'attachment title', $t->title() );
	}
}