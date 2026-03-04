<?php

/**
 * @group formatting
 * @group emoji
 */
class Tests_Formatting_Emoji extends WP_UnitTestCase {

	/**
	 * @ticket 41501
	 *
	 * @covers ::_wp_emoji_list
	 */
	public function test_wp_emoji_list_returns_data() {
		$default = _wp_emoji_list();
		$this->assertNotEmpty( $default, 'Default should not be empty' );

		$entities = _wp_emoji_list( 'entities' );
		$this->assertNotEmpty( $entities, 'Entities should not be empty' );
		$this->assertIsArray( $entities, 'Entities should be an array' );
		// Emoji 17 contains 4007 entities, this number will only increase.
		$this->assertGreaterThanOrEqual( 4007, count( $entities ), 'Entities should contain at least 4007 items' );
		$this->assertSame( $default, $entities, 'Entities should be returned by default' );

		$partials = _wp_emoji_list( 'partials' );
		$this->assertNotEmpty( $partials, 'Partials should not be empty' );
		$this->assertIsArray( $partials, 'Partials should be an array' );
		// Emoji 17 contains 1438 partials, this number will only increase.
		$this->assertGreaterThanOrEqual( 1438, count( $partials ), 'Partials should contain at least 1438 items' );

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
				// Bird, ZWJ, black large square, emoji selector.
				'🐦‍⬛',
				'&#x1f426;&#x200d;&#x2b1b;',
			),
			array(
				// Unicode 10.
				'🧚',
				'&#x1f9da;',
			),
			array(
				// Hairy creature (Unicode 17).
				'🫈',
				'&#x1fac8;',
			),
		);
	}

	/**
	 * @ticket 35293
	 * @dataProvider data_wp_encode_emoji
	 *
	 * @covers ::wp_encode_emoji
	 */
	public function test_wp_encode_emoji( $emoji, $expected ) {
		$this->assertSame( $expected, wp_encode_emoji( $emoji ) );
	}
}
