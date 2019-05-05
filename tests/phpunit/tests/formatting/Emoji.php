<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

	private $png_cdn = 'https://s.w.org/images/core/emoji/12.0.0-1/72x72/';
	private $svn_cdn = 'https://s.w.org/images/core/emoji/12.0.0-1/svg/';

	/**
	 * @ticket 36525
	 */
	public function test_unfiltered_emoji_cdns() {
		$output = get_echo( '_print_emoji_detection_script' );

		$this->assertContains( wp_json_encode( $this->png_cdn ), $output );
		$this->assertContains( wp_json_encode( $this->svn_cdn ), $output );
	}

	public function _filtered_emoji_svn_cdn( $cdn = '' ) {
		return 'https://s.wordpress.org/images/core/emoji/svg/';
	}

	public function _filtered_emoji_png_cdn( $cdn = '' ) {
		return 'https://s.wordpress.org/images/core/emoji/png_cdn/';
	}

	/**
	 * @ticket 41501
	 */
	public function test_wp_emoji_list_returns_data() {
		$default = _wp_emoji_list();
		$this->assertNotEmpty( $default );

		$entities = _wp_emoji_list( 'entities' );
		$this->assertNotEmpty( $entities );

		$this->assertSame( $default, $entities );

		$partials = _wp_emoji_list( 'partials' );
		$this->assertNotEmpty( $partials );

		$this->assertNotSame( $default, $partials );
	}

	public function data_wp_encode_emoji() {
		return array(
			array(
				// Not emoji
				'’',
				'’',
			),
			array(
				// Simple emoji
				'🙂',
				'&#x1f642;',
			),
			array(
				// Skin tone, gender, ZWJ, emoji selector
				'👮🏼‍♀️',
				'&#x1f46e;&#x1f3fc;&#x200d;&#x2640;&#xfe0f;',
			),
			array(
				// Unicode 10
				'🧚',
				'&#x1f9da;',
			),
		);
	}

	/**
	 * @ticket 35293
	 * @dataProvider data_wp_encode_emoji
	 */
	public function test_wp_encode_emoji( $emoji, $expected ) {
		$this->assertSame( $expected, wp_encode_emoji( $emoji ) );
	}
}
