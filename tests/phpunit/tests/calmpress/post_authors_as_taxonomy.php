<?php
/**
 * Unit tests covering Post_Authors_As_Taxonomy functionality
 *
 * @package calmPress
 * @since 1.0.0
 */

use calmpress\post_authors;

class WP_Test_Post_Authors_As_Taxonomy extends WP_UnitTestCase {

	/**
	 * Test the state after boot.
	 *
	 * @since 1.0.0
	 */
	function test_taxonomy_available_after_init() {

		// Test that the taxonomy is registered.
		$this->assertTrue( taxonomy_exists( \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );

		// Test if it is associated with post, page, and attachment post types.
		$this->assertTrue( is_object_in_taxonomy( 'post', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
		$this->assertTrue( is_object_in_taxonomy( 'page', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
		$this->assertTrue( is_object_in_taxonomy( 'attachment', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test adding post type with author.
	 *
	 * @since 1.0.0
	 */
	function test_taxonomy_register_post_type_with_author() {
		register_post_type( 'with_author', [
			'label' => 'with author',
			'supports' => [ 'title', 'editor', 'author' ],
		] );

		$this->assertTrue( is_object_in_taxonomy( 'with_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test adding post type with author.
	 *
	 * @since 1.0.0
	 */
	function test_taxonomy_register_post_type_without_author() {
		register_post_type( 'without_author', [
			'label' => 'with author',
			'supports' => [ 'title', 'editor' ],
		] );

		$this->assertFalse( is_object_in_taxonomy( 'without_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test register_taxonomy_for_post_type.
	 *
	 * @since 1.0.0
	 */
	function test_taxonomy_register_post_type() {
		register_post_type( 'without_author', [
			'label' => 'with author',
			'supports' => [ 'title', 'editor' ],
		] );

		\calmpress\post_authors\Post_Authors_As_Taxonomy::register_taxonomy_for_post_type( 'without_author' );

		$this->assertTrue( is_object_in_taxonomy( 'without_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test register_taxonomy_for_post_type.
	 *
	 * @since 1.0.0
	 */
	function test_taxonomy_unregister_post_type() {
		register_post_type( 'with_author', [
			'label' => 'with author',
			'supports' => [ 'title', 'editor', 'author' ],
		] );

		\calmpress\post_authors\Post_Authors_As_Taxonomy::unregister_taxonomy_for_post_type( 'with_author' );

		$this->assertFalse( is_object_in_taxonomy( 'with_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test register_taxonomy_for_post_type.
	 *
	 * @since 1.0.0
	 */
	function test_add_post_type_support() {
		register_post_type( 'without_author', [
			'label' => 'with author',
			'supports' => [ 'title' ],
		] );

		add_post_type_support( 'without_author', 'editor' );

		$this->assertFalse( is_object_in_taxonomy( 'without_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );

		add_post_type_support( 'without_author', 'author' );

		$this->assertTrue( is_object_in_taxonomy( 'without_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test register_taxonomy_for_post_type.
	 *
	 * @since 1.0.0
	 */
	function test_remove_post_type_support() {
		register_post_type( 'with_author', [
			'label' => 'with author',
			'supports' => [ 'title', 'editor', 'author' ],
		] );

		remove_post_type_support( 'with_author', 'editor' );

		$this->assertTrue( is_object_in_taxonomy( 'with_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );

		remove_post_type_support( 'with_author', 'author' );

		$this->assertFalse( is_object_in_taxonomy( 'with_author', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME ) );
	}

	/**
	 * Test the post_authors method.
	 *
	 * @since 1.0.0
	 */
	function test_post_authors() {
		$user = $this->factory->user->create( [ 'name' => 'test', 'display_name' => 'display name', 'description' => 'test description' ] );

		$post1 = $this->factory->post->create( [
			'post_title' => 'test1',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$post = get_post( $post1 );

		// Test no authors.
		$this->assertCount( 0, post_authors\Post_Authors_As_Taxonomy::post_authors( $post ) );

		// Test one author.
		$author1 = wp_insert_term( 'author1', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( $post1, $author1['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$authors = post_authors\Post_Authors_As_Taxonomy::post_authors( $post );
		$this->assertCount( 1, $authors );
		$this->assertEquals( $author1['term_id'], $authors[0]->term_id() );

		// Two authors.
		$author2 = wp_insert_term( 'author2', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( $post1, $author2['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$authors = post_authors\Post_Authors_As_Taxonomy::post_authors( $post );
		$this->assertCount( 2, $authors );
		$this->assertEquals( $author1['term_id'], $authors[0]->term_id() );
		$this->assertEquals( $author2['term_id'], $authors[1]->term_id() );
	}

	/**
	 * Test the authors_post_count method.
	 *
	 * @since 1.0.0
	 */
	function test_authors_post_count() {
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

		$post = get_post( $post1 );

		// Test no authors.
		$this->assertEquals( 0, post_authors\Post_Authors_As_Taxonomy::authors_post_count( $post ) );

		// Test one author, one post.
		$author1 = wp_insert_term( 'author1', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( $post1, $author1['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$this->assertEquals( 1, post_authors\Post_Authors_As_Taxonomy::authors_post_count( $post ) );

		// Two authors, one post. Make sure the overlap is taken into account.
		$author2 = wp_insert_term( 'author2', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( $post1, $author2['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$this->assertEquals( 1, post_authors\Post_Authors_As_Taxonomy::authors_post_count( $post ) );

		// Two authors, two posts.
		wp_set_object_terms( $post2, $author2['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$this->assertEquals( 2, post_authors\Post_Authors_As_Taxonomy::authors_post_count( $post ) );
	}

	/**
	 * Test the combined_authors_url method.
	 *
	 * @since 1.0.0
	 */
	function test_combined_authors_url() {
		$user = $this->factory->user->create( [ 'name' => 'test', 'display_name' => 'display name', 'description' => 'test description' ] );

		$post1 = $this->factory->post->create( [
			'post_title' => 'test1',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		$post = get_post( $post1 );

		// Test no authors.
		$this->assertEquals( '', post_authors\Post_Authors_As_Taxonomy::combined_authors_url( $post ) );

		// Test one author.
		$author1 = wp_insert_term( 'author1', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( $post1, $author1['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$url = post_authors\Post_Authors_As_Taxonomy::combined_authors_url( $post );
		$this->assertContains( 'author1', $url );

		// Two authors.
		$author2 = wp_insert_term( 'author2', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		wp_set_object_terms( $post1, $author2['term_id'], \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		$url = post_authors\Post_Authors_As_Taxonomy::combined_authors_url( $post );
		$this->assertContains( 'author1', $url );
		$this->assertContains( 'author2', $url );
	}

	/**
	 * Test the get_authors method.
	 *
	 * @since 1.0.0
	 */
	function test_get_authors() {
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

		$post3 = $this->factory->post->create( [
			'post_title' => 'test3',
			'post_author' => $user,
			'post_status' => 'publish',
		] );

		// Test no authors.
		$this->assertCount( 0, post_authors\Post_Authors_As_Taxonomy::get_authors(
			10,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NONE,
			true,
			[],
			[]
			) );

		// Create authors and associate with posts.
		$author1 = wp_insert_term( 'author1', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$author1 = $author1['term_id'];

		wp_set_object_terms( $post1, $author1, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		wp_set_object_terms( $post2, $author1, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );

		$author2 = wp_insert_term( 'author2', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$author2 = $author2['term_id'];

		wp_set_object_terms( $post1, $author2, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		wp_set_object_terms( $post2, $author2, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
		wp_set_object_terms( $post3, $author2, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );

		$author3 = wp_insert_term( 'author3', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$author3 = $author3['term_id'];

		$author4 = wp_insert_term( 'author4', \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
		$author4 = $author4['term_id'];

		wp_set_object_terms( $post3, $author4, \calmpress\post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );

		// Get all, no specific order.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			10,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NONE,
			true,
			[],
			[]
			);

		$this->assertEquals(
			[ $author1, $author2, $author3, $author4 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Get all except for empty, no specific order.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			10,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NONE,
			false,
			[],
			[]
			);

		$this->assertEquals(
			[ $author1, $author2, $author4 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Test limited number, no order.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			2,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NONE,
			true,
			[],
			[]
			);

		$this->assertEquals(
			[ $author1, $author2 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Test limited number, and sort by name asc.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			2,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NAME_ASC,
			true,
			[],
			[]
			);

		$this->assertEquals(
			[ $author1, $author2 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Test limited number, and sort by name desc.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			2,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NAME_DESC,
			true,
			[],
			[]
			);

		$this->assertEquals(
			[ $author4, $author3 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Test limited number, and sort by post count asc.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			2,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NUMBER_POSTS_ASC,
			true,
			[],
			[]
			);

		$this->assertEquals(
			[ $author3, $author4 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Test limited number, and sort by post count desc.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			2,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NUMBER_POSTS_DESC,
			true,
			[],
			[]
			);

		$this->assertEquals(
			[ $author2, $author1 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);

		// Test exclude.
		$authors = post_authors\Post_Authors_As_Taxonomy::get_authors(
			2,
			post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NUMBER_POSTS_DESC,
			true,
			[new post_authors\Taxonomy_Based_Post_Author( get_term( $author2 ) ) ],
			[]
			);

		$this->assertEquals(
			[ $author1, $author4 ],
			array_map( function ( $author ) { return $author->term_id(); }, $authors )
			);
	}
}
