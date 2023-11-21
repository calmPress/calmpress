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
	 * Test that the constructor set the path property
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$path_property = new ReflectionProperty( 'calmpress\email\Email_Attachment_File', 'path' );
        $path_property->setAccessible(true);

		// common use.
		$t = new Email_Attachment_File( __FILE__ );
		$this->assertSame( __FILE__, $path_property->getValue( $t ) );

		// Non existing file throws exception.
		$thrown = false;
		try {
			$t = new Email_Attachment_File( 'bad.name.of.file' );
		} catch ( \Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );
	}

	/**
	 * Test the path method.
	 *
	 * @since 1.0.0
	 */
	public function test_name() {
		$t = new Email_Attachment_File( __FILE__ );
		$this->assertSame( __FILE__, $t->path() );
	}
}