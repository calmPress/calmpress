<?php
/**
 * Unit tests covering Email_Address class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

use calmpress\email\Email_Address;

class Email_Address_Test extends WP_UnitTestCase {

	/**
	 * Test that the constructor set name and address properties and throws
	 * exception for invalid address. Test name sanitation as well.
	 *
	 * @since 1.0.0
	 */
	public function test_constructor() {
		$name_property = new ReflectionProperty( 'calmpress\email\Email_Address', 'name' );
        $name_property->setAccessible(true);
		$address_property = new ReflectionProperty( 'calmpress\email\Email_Address', 'address' );
        $address_property->setAccessible(true);

		// common use.
		$t = new Email_Address( 'a@b.com', 'testo' );
		$this->assertSame( 'testo', $name_property->getValue( $t ) );
		$this->assertSame( 'a@b.com', $address_property->getValue( $t ) );

		// Invalid email address throws exception.
		$thrown = false;
		try {
			$t = new Email_Address( 'bad', 'testo' );
		} catch ( \Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );

		// Name sanitization.
		$t = new Email_Address( 'a@b.com', " \r\n o\tp\r\nsi tester \r\n" );
		$this->assertSame( "o\tp si tester", $name_property->getValue( $t ) );

		// Address sanitization.
		$t = new Email_Address( "  a @ b.\rc\nom  ", "tester" );
		$this->assertSame( "a@b.com", $address_property->getValue( $t ) );
	}

	/**
	 * Test the name method
	 *
	 * @since 1.0.0
	 */
	public function test_name() {
		$t = new Email_Address( 'a@b.com', "  Operator Mark\r\n " );
		$this->assertSame( 'Operator Mark', $t->name() );
	}

	/**
	 * Test the address method
	 *
	 * @since 1.0.0
	 */
	public function test_address() {
		$t = new Email_Address( 'someone @example.org', "  Operator Mark\r\n " );
		$this->assertSame( 'someone@example.org', $t->address() );
	}

	/**
	 * Test the full_address method
	 *
	 * @since 1.0.0
	 */
	public function test_full_address() {
		$t = new Email_Address( 'calm@ calmpress.org', "  calm\tPre\"ss\r\n " );
		$this->assertSame( 'calm'. "\t" . 'Pre"ss <calm@calmpress.org>', $t->full_address() );
	}

}