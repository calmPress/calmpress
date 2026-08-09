<?php

/**
 * @group post
 * @group media
 * @group upload
 *
 * @covers ::wp_count_attachments
 */
class Tests_Post_wpCountAttachments extends WP_UnitTestCase {

	/**
	 * Tests that network attachments are not included in Media Library counts.
	 *
	 * @since calmPress 1.0.0
	 */
	public function test_network_attachments_are_not_counted() {
		$mime_type = 'image/jpeg';
		$cache_key = 'attachments:image_jpeg';

		wp_cache_delete( $cache_key, 'counts' );
		$before = wp_count_attachments( $mime_type );

		self::factory()->attachment->create(
			[
				'post_mime_type' => $mime_type,
				'post_status'    => 'network',
			]
		);

		wp_cache_delete( $cache_key, 'counts' );
		$after = wp_count_attachments( $mime_type );

		$this->assertEquals( $before, $after );
	}

	/**
	 * Tests that the result is cached.
	 *
	 * @ticket 55227
	 */
	public function test_wp_count_attachments_should_cache_the_result() {
		$mime_type = 'image/jpeg';
		$cache_key = 'attachments:image_jpeg';

		self::factory()->post->create_many(
			3,
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => $mime_type,
			)
		);
		$expected = wp_count_attachments( $mime_type );
		$actual   = wp_cache_get( $cache_key, 'counts' );

		$this->assertEquals( $expected, $actual );
	}
}
