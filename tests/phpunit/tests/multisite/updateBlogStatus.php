<?php

/**
 * @group ms-required
 * @group ms-site
 * @group multisite
 */
class Tests_Multisite_UpdateBlogStatus extends WP_UnitTestCase {

	/**
	 * Updating an invalid field returns the same value that was passed.
	 */
	public function test_update_blog_status_invalid_status() {
		$result = update_blog_status( 1, 'doesnotexist', 'invalid' );
		$this->assertSame( 'invalid', $result );
	}
}
