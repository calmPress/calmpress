<?php

/**
 * @group date
 * @group datetime
 * @covers ::wp_date
 */
class Tests_Date_wpDate extends WP_UnitTestCase {

	/** @var WP_Locale */
	private $wp_locale_original;

	public function set_up() {
		global $wp_locale;

		parent::set_up();

		$this->wp_locale_original = clone $wp_locale;
	}

	public function tear_down() {
		global $wp_locale;

		$wp_locale = $this->wp_locale_original;

		parent::tear_down();
	}

	/**
	 * @ticket 28636
	 */
	public function test_should_return_false_on_invalid_timestamp() {
		$this->assertFalse( wp_date( DATE_RFC3339, 'invalid' ) );
	}
}
