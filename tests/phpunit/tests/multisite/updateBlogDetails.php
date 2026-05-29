<?php

/**
 * @group ms-required
 * @group ms-site
 * @group multisite
 */
class Tests_Multisite_UpdateBlogDetails extends WP_UnitTestCase {

	/**
	 * If `update_blog_details()` is called with any kind of empty arguments, it
	 * should return false.
	 */
	public function test_update_blog_details_with_empty_args() {
		$result = update_blog_details( 1, array() );
		$this->assertFalse( $result );
	}

	/**
	 * If the ID passed is not that of a current site, we should expect false.
	 */
	public function test_update_blog_details_invalid_blog_id() {
		$result = update_blog_details( 999, array( 'domain' => 'example.com' ) );
		$this->assertFalse( $result );
	}

	public function test_update_blog_details() {
		$blog_id = self::factory()->blog->create();

		$result = update_blog_details(
			$blog_id,
			array(
				'domain' => 'example.com',
				'path'   => 'my_path/',
			)
		);

		$this->assertTrue( $result );

		$blog = get_site( $blog_id );

		$this->assertSame( 'example.com', $blog->domain );
		$this->assertSame( '/my_path/', $blog->path );
	}

	/**
	 * When the path for a site is updated with update_blog_details(), the final path
	 * should have a leading and trailing slash.
	 *
	 * @dataProvider data_single_directory_path
	 */
	public function test_update_blog_details_single_directory_path( $path, $expected ) {
		update_blog_details( 1, array( 'path' => $path ) );
		$site = get_site( 1 );

		$this->assertSame( $expected, $site->path );
	}

	public function data_single_directory_path() {
		return array(
			array( 'my_path', '/my_path/' ),
			array( 'my_path//', '/my_path/' ),
			array( '//my_path', '/my_path/' ),
			array( 'my_path/', '/my_path/' ),
			array( '/my_path', '/my_path/' ),
			array( '/my_path/', '/my_path/' ),

			array( 'multiple/dirs', '/multiple/dirs/' ),
			array( '/multiple/dirs', '/multiple/dirs/' ),
			array( 'multiple/dirs/', '/multiple/dirs/' ),
			array( '/multiple/dirs/', '/multiple/dirs/' ),

			// update_blog_details() does not resolve multiple slashes in the middle of a path string.
			array( 'multiple///dirs', '/multiple///dirs/' ),
		);
	}
}
