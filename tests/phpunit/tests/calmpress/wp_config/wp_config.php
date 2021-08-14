<?php
/**
 * Unit tests covering wp_config functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\wp_config;

require_once ABSPATH . 'wp-admin/includes/file.php';

/**
 * Tests for the methods of wp_config class.
 *
 * @since 1.0.0
 */
class WP_Test_Post_Wp_Config extends WP_UnitTestCase {

	/**
	 * Test valid_user_setting_line.
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider line_provider
	 */
	function test_valid_user_setting_line( string $line, bool $expected ) {
		$method = new ReflectionMethod( '\calmpress\wp_config\wp_config', 'valid_user_setting_line' );
        $method->setAccessible(true);

	    $this->AssertSame( $expected, $method->invoke( null, $line ) );
    }

	/**
	 * Test data provider for single line validation.
	 *
	 * @since 1.0.0
	 */
	public function line_provider() {
        $tests = [
          ['', true],           // Empty line.
		  ['    ', true],       // Line with only spaces.
		  ['// some text   ', true],  // comment line.
		  ['  // comment', true],     // comment line leading spaces.
		  ['// define("aa",tru', true],     // comment line with bad define in it.
		  [' define("gfh_', false], // Broken define, no second quote.
		  ['define("gfh_hdh",', false], // Broken define, no second parameter
		  ['define("gfh_hdh",);', false], // Broken define, no second parameter value
		  ['define("gfh_hdh",); // com)', false], // Broken define, no second parameter value
		  ['define("gfh_hdh",;);', false], // Broken define, bad second parameter value
		  ['define("gfh_hdh",for got);', false], // Broken define, bad second parameter value
		  ['define("gfh_hdh","for got"); // something', true], // Proper define
		  [' define( "gfh_hdh" , "for got" ); // something', true], // Proper define spaced
		  ['define("gfh_hdh",true);', true], // Proper define bool true
		  ['define("gfh_hdh",false);', true], // Proper define bool false
		  ['define("gfh_hdh",42);', true], // Proper define numeric
		  ['define("gfh_hdh",\'string);', false], // broken string parameter, no quote
		  ['define("gfh_hdh",\'string");', false], // broken string parameter, wrong quote
		  ['define("gfh_hdh","string);', false], // broken string parameter, no quote
		  ['define("gfh_hdh","string\');', false], // broken string parameter, wrong quote
		  ['define("gfh_hdh",\'string\');', true], // proper string parameter single quote
		  ['define("gfh_hdh","string");', true], // proper string parameter double quote
		  ['define("gfh_hdh","str\"ing");', true], // proper string parameter quote escaped
		  ['define("gfh_hdh",\'str\\\'ing\');', true], // proper string parameter quote escaped
		  ['define("gfh_hdh",\'str\'ing\');', false], // broken string parameter quote not escaped
		  ['define("gfh_hdh","str"ing");', false], // broken string parameter quote not escaped
		];

		return $tests;
    }

    /**
	 * Test the sanitize_user_setting method.
	 * 
	 * Test focuses on esuring that multi lines are properly parsed and passed to
	 * valid_user_setting_line for actual validation.
	 *
	 * @since 1.0.0
	 * 
	 * @dataProvider multi_line_provider
	 */
	function test_sanitize_user_setting( string $setting, string $expected ) {
		$this->assertSame( $expected, \calmpress\wp_config\wp_config::sanitize_user_setting( $setting ) );
	}

	/**
	 * Test data provider for multi line validation.
	 *
	 * @since 1.0.0
	 */
	public function multi_line_provider() {
        $tests = [
          ["", ""],           // empty setting.
		  ["// comment", "// comment"],  // just one valid line.
		  ["comment", ""],  // one invalid line.
		  ["\n", "\n"],       // empty two lines.
		  ["//\n", "//\n"],   // empty second line.
		  ["def\ndefine('a',true);", "define('a',true);"],   // Invalid first line.
		  ["//\n\ndefine('a',true);", "//\n\ndefine('a',true);"],   // two content line with one empty between.
		  ["//\nwhat\ndefine('a',true);", "//\ndefine('a',true);"],   // two content line with invalid between.
		  ["//\nwhat\ndefine('a',true);", "//\ndefine('a',true);"],   // two content line with invalid between.
		  ["who\ndefine('a',true);\nwhere", "define('a',true);"],   // several invalid lines.
		];

		return $tests;
    }

	/**
	 * Test user_section_in_file.
	 *
	 * @since 1.0.0
	 */
	function test_user_section_in_file() {
		$filename = wp_tempnam();
		$config = new \calmpress\wp_config\wp_config( $filename );
		file_put_contents( $filename, "test\n// BEGIN User\ntest string\n// END User\nsomething else");

		$this->AssertSame( 'test string', $config->user_section_in_file() );
		unlink( $filename );
	}

	/**
	 * Test save_user_section_to_file.
	 *
	 * @since 1.0.0
	 */
	function test_save_user_section_to_file() {
		$filename = wp_tempnam();
		$config = new \calmpress\wp_config\wp_config( $filename );
		file_put_contents( $filename, "test\n// BEGIN User\n// END User\nsomething else");

		// Test with valid content.
		$config->save_user_section_to_file('//test');
		$s = file_get_contents( $filename );
		$this->AssertSame( "test\n// BEGIN User\n//test\n// END User\nsomething else", $s );

		// Test with partially invalid content.
		$config->save_user_section_to_file("def\n//test 2");
		$s = file_get_contents( $filename );
		$this->AssertSame( "test\n// BEGIN User\n//test 2\n// END User\nsomething else", $s );
		unlink( $filename );
	}
}