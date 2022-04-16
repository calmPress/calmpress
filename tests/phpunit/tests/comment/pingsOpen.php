<?php

/**
 * @group  comment
 * @covers ::pings_open
 */
class Tests_Comment_PingsOpen extends WP_UnitTestCase {

	/**
	 * @ticket 54159
	 */
	public function test_post_does_not_exist() {
		$this->assertFalse( pings_open( 99999 ) );
	}

	/**
	 * @ticket 54159
	 */
	public function test_post_exist_status_open() {
		$post = $this->factory->post->create_and_get();
		$this->assertFalse( pings_open( $post ) );
	}
}
