<?php

/**
 * Test the content in some root directory files.
 *
 * @group basic
 */
class Tests_Basic extends WP_UnitTestCase {

	/**
	 * Test copyright year in license.txt.
	 *
	 * @coversNothing
	 */
	public function test_license() {
		// This test is designed to only run on trunk.
		$this->skipOnAutomatedBranches();

		$license = file_get_contents( ABSPATH . 'license.txt' );
		preg_match( '#Copyright 2011-(\d+) by the contributors#', $license, $matches );
		$license_year = trim( $matches[1] );
		$this_year    = gmdate( 'Y' );

		$this->assertSame( $this_year, $license_year, "license.txt's year needs to be updated to $this_year." );
	}
}
