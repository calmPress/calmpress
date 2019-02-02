<?php
/**
 * Unit tests covering Post_Authors_As_Taxonomy_Db_Upgrade functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\post_authors;

class WP_Test_Post_Authors_As_Taxonomy_Db_Upgrade extends WP_UnitTestCase {

	/**
	 * Test the edge case of no posts at all
	 *
	 * @since 1.0.0
	 */
	function test_no_post() {
		$this->factory->user->create( ['name' => 'test'] );

		post_authors\Post_Authors_As_Taxonomy_Db_Upgrade::upgrade();

		// Make sure no terms were created
		$terms = get_terms( post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, [
    		'hide_empty' => false,
		] );

		$this->assertCount( 0, $terms );
	}

	/**
	 * Test the conversation of one author.
	 *
	 * @since 1.0.0
	 */
	function test_one_author() {
		$user = $this->factory->user->create( [ 'name' => 'test', 'display_name' => 'display name', 'description' => 'test description' ] );

		$post1 = $this->factory->post->create( [
			'post_title' => 'test1',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$post2 = $this->factory->post->create( [
			'post_title' => 'test2',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$page1 = $this->factory->post->create( [
			'post_type' => 'page',
			'post_title' => 'test3',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		wp_update_post( [
			'ID' => $attachment_id,
			'post_title' => 'test4',
			'post_author' => $user,
		] );

		// one draft to make sure drafts are not converted.
		$draft = $this->factory->post->create( [
			'post_type' => 'page',
			'post_title' => 'test4',
			'post_author' => $user,
			'post_status' => 'draft',
		] );

		post_authors\Post_Authors_As_Taxonomy_Db_Upgrade::upgrade();

		// Make sure one term was created
		$terms = get_terms( post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, [
    		'hide_empty' => false,
		] );

		$this->assertCount( 1, $terms );

		// Now make sure it actually has the right name and description
		$term = $terms[0];
		$this->assertEquals( 'display name', $term->name );
		$this->assertEquals( 'test description', $term->description );

		$posts = get_posts(
		    array(
		        'posts_per_page' => -1,
		        'post_type' => 'any',
				'post_status' => 'any',
				'fields' => 'ids',
		        'tax_query' => array(
		            array(
		                'taxonomy' => post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME,
		                'field' => 'term_id',
		                'terms' => $term->term_id,
		            )
		        )
		    )
		);

		// We count on get_posts not returning suplicates. We expect 2 posts, a page and attachment.
		$this->assertCount(4, $posts );

		// Check that the draft was ignored.
		$this->assertFalse( in_array( $draft, $posts ) );
	}

	/**
	 * Test the conversation of one author.
	 *
	 * @since 1.0.0
	 */
	function test_multiple_authors() {
		$user = $this->factory->user->create( [ 'name' => 'test', 'display_name' => 'display name', 'description' => 'test description' ] );
		$user2 = $this->factory->user->create( [ 'name' => 'test2', 'display_name' => 'display name 2', 'description' => '' ] );

		$post1 = $this->factory->post->create( [
			'post_title' => 'test1',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$post2 = $this->factory->post->create( [
			'post_title' => 'test2',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$page1 = $this->factory->post->create( [
			'post_type' => 'page',
			'post_title' => 'test3',
			'post_author' => $user2,
			'post_status' => 'publish',
		] );

		$file = DIR_TESTDATA . '/images/canola.jpg';
		$attachment_id = $this->factory->attachment->create_upload_object( $file, 0 );
		wp_update_post( [
			'ID' => $attachment_id,
			'post_title' => 'test4',
			'post_author' => $user2,
		] );

		// one draft to make sure drafts are not converted.
		$draft = $this->factory->post->create( [
			'post_type' => 'page',
			'post_title' => 'test4',
			'post_author' => $user2,
			'post_status' => 'draft',
		] );

		post_authors\Post_Authors_As_Taxonomy_Db_Upgrade::upgrade();

		// Make sure two terms were created.
		$terms = get_terms( post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, [
    		'hide_empty' => false,
		] );

		$this->assertCount( 2, $terms );

		// Now make sure it actually has the right name and description
		$term = $terms[0];
		$this->assertEquals( 'display name', $term->name );
		$this->assertEquals( 'test description', $term->description );

		$term = $terms[1];
		$this->assertEquals( 'display name 2', $term->name );
		$this->assertEquals( '', $term->description );

		$posts = get_posts(
		    array(
		        'posts_per_page' => -1,
		        'post_type' => 'any',
				'post_status' => 'any',
				'fields' => 'ids',
		        'tax_query' => array(
		            array(
		                'taxonomy' => post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME,
		                'field' => 'term_id',
		                'terms' => $terms[0]->term_id,
		            )
		        )
		    )
		);

		// We count on get_posts not returning duplicates. We expect 2 posts, a page and attachment.
		$this->assertCount(2, $posts );

		// Check that the draft was ignored.
		$this->assertFalse( in_array( $draft, $posts ) );
		$posts = get_posts(
		    array(
		        'posts_per_page' => -1,
		        'post_type' => 'any',
				'post_status' => [ 'publish', 'inherit' ],
				'fields' => 'ids',
		        'tax_query' => array(
		            array(
		                'taxonomy' => post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME,
		                'field' => 'term_id',
		                'terms' => $terms[0]->term_id,
		            )
		        )
		    )
		);

		// We count on get_posts not returning duplicates. We expect 2 posts.
		$this->assertCount(2, $posts );

		// Check that the draft was ignored.
		$this->assertFalse( in_array( $draft, $posts ) );
		$this->assertTrue( in_array( $post1, $posts ) );
		$this->assertTrue( in_array( $post2, $posts ) );

		$posts = get_posts(
		    array(
		        'posts_per_page' => -1,
		        'post_type' => 'any',
				'post_status' => [ 'publish', 'inherit', 'draft' ],
				'fields' => 'ids',
		        'tax_query' => array(
		            array(
		                'taxonomy' => post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME,
		                'field' => 'term_id',
		                'terms' => $terms[1]->term_id,
		            ),
		        ),
		    )
		);

		// We count on get_posts not returning duplicates. We expect a page and attachment.
		$this->assertCount(2, $posts );

		// Check that the draft was ignored.
		$this->assertFalse( in_array( $draft, $posts ) );
		$this->assertTrue( in_array( $page1, $posts ) );
		$this->assertTrue( in_array( $attachment_id, $posts ) );
	}
}
