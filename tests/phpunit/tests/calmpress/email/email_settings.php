<?php
/**
 * Unit tests covering Email_Settings class.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

class Test_Email_Settings extends WP_UnitTestCase {

	/**
	 * Helper to get current option value from DB based on if its
	 * a network wide default, or a per site configuration.
	 * 
	 * @since 1.0.0
	 * 
	 * @return array the value of the option in the DB.
	 */
	private function get_option(): array {
		if ( is_multisite() ) {
			$override_opt = get_option( 'calm_network_override' );
			if ( ! array_key_exists( 'email_delivery', $override_opt ) ) {
				return get_site_option( 'calm_email_delivery' );
			}
		}

		return get_option( 'calm_email_delivery' );
	}

	/**
	 * Helper to update current option value in the DB based on if its
	 * a network wide default, or a per site configuration.
	 * 
	 * @since 1.0.0
	 * 
	 * @param array $opt the value to update the option with.
	 */
	private function update_option( $opt ) {
		if ( is_multisite() ) {
			$override_opt = get_option( 'calm_network_override' );
			if ( ! array_key_exists( 'email_delivery', $override_opt ) ) {
				update_site_option( 'calm_email_delivery', $opt );
				return;
			}
		}

		update_option( 'calm_email_delivery', $opt );
	}

	/**
	 * Perform tests for is_local in enviroment neutral way
	 */
	private function is_local_tests() {
		$opt = $this->get_option();
		$opt['type'] = 'local';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertTrue( $settings->is_local() );

		$opt['type'] = 'smtp';
		$opt['host'] = 'a.com';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertFalse( $settings->is_local() );
	}

	/**
	 * test is_local in all enviroments
	 * 
	 * @since 1.0.0
	 */
	public function test_is_local() {
		if ( is_multisite() ) {
			// Test with network defaults.
			update_option( 'calm_network_override', [] );
			$this->is_local_tests();
			// Test with a site override.
			update_option( 'calm_network_override', ['email_delivery' => 1] );
			$this->is_local_tests();
		} else {
			$this->is_local_tests();	
		}
	}

	/**
	 * test is_smtp
	 * 
	 * @since 1.0.0
	 */
	public function test_is_smtp() {
		$opt = $this->get_option();
		$opt['type'] = 'local';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertFalse( $settings->is_smtp() );

		$opt['type'] = 'smtp';
		$opt['host'] = 'a.com';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertTrue( $settings->is_smtp() );
	}

	/**
	 * test log_succesful_email
	 * 
	 * @since 1.0.0
	 */
	public function test_log_succesful_email() {
		$opt = get_option( 'calm_email_delivery' );
		$opt['verbosity'] = 'no';
		update_option( 'calm_email_delivery', $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertFalse( $settings->log_succesful_email() );

		$opt['verbosity'] = 'full';
		update_option( 'calm_email_delivery', $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertTrue( $settings->log_succesful_email() );

		$opt['verbosity'] = 'recipients';
		update_option( 'calm_email_delivery', $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertTrue( $settings->log_succesful_email() );
	}

	/**
	 * test log_content
	 * 
	 * @since 1.0.0
	 */
	public function test_log_content() {
		$opt = get_option( 'calm_email_delivery' );
		$opt['verbosity'] = 'no';
		update_option( 'calm_email_delivery', $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertFalse( $settings->log_content() );

		$opt['verbosity'] = 'full';
		update_option( 'calm_email_delivery', $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertTrue( $settings->log_content() );

		$opt['verbosity'] = 'recipients';
		update_option( 'calm_email_delivery', $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertFalse( $settings->log_content() );
	}

	/**
	 * Test smtp_host.
	 * 
	 * @since 1.0.0
	 */
	public function test_smtp_host() {
		$opt = $this->get_option();
		$opt['type'] = 'smtp';
		$opt['host'] = 'test.com';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertSame( 'test.com', $settings->smtp_host() );

		// Exception when requested for local gateway.
		$opt['type'] = 'local';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		try {
			$settings->smtp_host();
			$this->fail( 'Exception not thrown' );
		} catch ( \LogicException $e ) {}
	}

	/**
	 * Test smtp_user.
	 * 
	 * @since 1.0.0
	 */
	public function test_smtp_user() {
		$opt = $this->get_option();
		$opt['type'] = 'smtp';
		$opt['host'] = 'a.com';
		$opt['user'] = 'test';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertSame( 'test', $settings->smtp_user() );

		// Exception when requested for local gateway.
		$opt['type'] = 'local';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		try {
			$settings->smtp_user();
			$this->fail( 'Exception not thrown' );
		} catch ( \LogicException $e ) {}
	}

	/**
	 * Test smtp_password.
	 * 
	 * @since 1.0.0
	 */
	public function test_smtp_password() {
		$opt = $this->get_option();
		$opt['type']     = 'smtp';
		$opt['host']     = 'a.com';
		$opt['password'] = 'pass';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		$this->assertSame( 'pass', $settings->smtp_password() );

		// Exception when requested for local gateway.
		$opt['type'] = 'local';
		$this->update_option( $opt );
		$settings = new calmpress\email\Email_Settings();
		try {
			$settings->smtp_password();
			$this->fail( 'Exception not thrown' );
		} catch ( \LogicException $e ) {}
	}

	/**
	 * Test validate_option_value function detects bad values.
	 * 
	 * @since 1.0.0
	 */
	public function test_validate_option_value() {
		$good_value = [
			'type'       => 'smtp',
			'host'       => 'a.b',
			'user'       => 'uu',
			'password'   => 'pp',
			'from_name'  => 'tester',
			'from_email' => 'tester@a.b',
			'verbosity'  => 'no'
		];

		$keys = ['type', 'host', 'user', 'password', 'from_name', 'from_email', 'verbosity'];

		// check a known good value validates.
		$this->assertEquals( $good_value, calmpress\email\Email_Settings::validate_option_value( $good_value ) );

		// Check normalization, trimming of spaces from fields.
		foreach ( $keys as $key ) {
			$t = $good_value;
			$t[ $key ] = ' ' . $t[ $key ] . ' ';
			$this->assertEquals( $good_value, calmpress\email\Email_Settings::validate_option_value( $t ) );
		}

		// Exception thrown on non array.
		try {
			calmpress\email\Email_Settings::validate_option_value( 'boo' );
			$this->fail( 'Exception not thrown' );
		} catch ( \LogicException $e ) {}

		// Exceptions thrown for missing keys
		foreach ( $keys as $key ) {
			$t = $good_value;
			unset( $t[ $key ] );
			try {
				calmpress\email\Email_Settings::validate_option_value( $t );
				$this->fail( 'Exception not thrown when key ' . $key . ' is missing' );
			} catch ( \LogicException $e ) {}
		}

		// Validates on all types.
		foreach ( ['local', 'smtp'] as $type ) {
			$t = $good_value;
			$t['type'] = $type;
			$this->assertEquals( $t, calmpress\email\Email_Settings::validate_option_value( $t ) );
		}

		// Exception thrown on bad type.
		$t = $good_value;
		$t['type'] = 'meh';
		try {
			calmpress\email\Email_Settings::validate_option_value( $t );
			$this->fail( 'Exception not thrown on bad type' );
		} catch ( \LogicException $e ) {}

		// Validates on all verbosity.
		foreach ( ['no', 'recipients', 'full'] as $value ) {
			$t = $good_value;
			$t['verbosity'] = $value;
			$this->assertEquals( $t, calmpress\email\Email_Settings::validate_option_value( $t ) );
		}

		// Exception thrown on bad verbosity.
		$t = $good_value;
		$t['verbosity'] = 'wow';
		try {
			calmpress\email\Email_Settings::validate_option_value( $t );
			$this->fail( 'Exception not thrown on bad verbosity' );
		} catch ( \LogicException $e ) {}

		// Exception thrown on illegal sender email address.
		$t = $good_value;
		$t['from_email'] = 'me';
		try {
			calmpress\email\Email_Settings::validate_option_value( $t );
			$this->fail( 'Exception not thrown on bad from_email' );
		} catch ( \LogicException $e ) {}

		// Exception thrown when host not given for SMTP.
		$t = $good_value;
		$t['type'] = 'smtp';
		$t['host'] = '';
		try {
			calmpress\email\Email_Settings::validate_option_value( $t );
			$this->fail( 'Exception not thrown on empty SMTP host' );
		} catch ( \LogicException $e ) {}

	}
}