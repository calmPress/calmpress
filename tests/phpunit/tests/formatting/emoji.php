<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

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
				// Not emoji.
				'’',
				'’',
			),
			array(
				// Simple emoji.
				'🙂',
				'&#x1f642;',
			),
			array(
				// Skin tone, gender, ZWJ, emoji selector.
				'👮🏼‍♀️',
				'&#x1f46e;&#x1f3fc;&#x200d;&#x2640;&#xfe0f;',
			),
			array(
				// Unicode 10.
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
