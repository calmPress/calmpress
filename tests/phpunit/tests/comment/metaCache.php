<?php

class Tests_Comment_Meta_Cache extends WP_UnitTestCase {
	protected $i       = 0;
	protected $queries = 0;

	/**
	 * @ticket 16894
	 */
	public function test_update_comment_meta_cache_should_default_to_true() {
		global $wpdb;

		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		// Clear comment cache, just in case.
		clean_comment_cache( $comment_ids );

		$q = new WP_Comment_Query(
			array(
				'post_ID' => $p,
			)
		);

		$num_queries = $wpdb->num_queries;
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 16894
	 */
	public function test_update_comment_meta_cache_true() {
		global $wpdb;

		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		// Clear comment cache, just in case.
		clean_comment_cache( $comment_ids );

		$q = new WP_Comment_Query(
			array(
				'post_ID'                   => $p,
				'update_comment_meta_cache' => true,
			)
		);

		$num_queries = $wpdb->num_queries;
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		$this->assertSame( $num_queries, $wpdb->num_queries );
	}

	/**
	 * @ticket 16894
	 */
	public function test_update_comment_meta_cache_false() {
		global $wpdb;

		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'foo', 'bar' );
		}

		$q = new WP_Comment_Query(
			array(
				'post_ID'                   => $p,
				'update_comment_meta_cache' => false,
			)
		);

		$num_queries = $wpdb->num_queries;
		foreach ( $comment_ids as $cid ) {
			get_comment_meta( $cid, 'foo', 'bar' );
		}

		$this->assertSame( $num_queries + 3, $wpdb->num_queries );
	}

	/**
	 * @ticket 16894
	 */
	public function test_comment_meta_should_be_lazy_loaded_for_all_comments_in_comments_template() {
		global $wpdb;

		$p           = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$comment_ids = self::factory()->comment->create_post_comments( $p, 3 );

		foreach ( $comment_ids as $cid ) {
			update_comment_meta( $cid, 'sauce', 'fire' );
		}

		$this->go_to( get_permalink( $p ) );

		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();

				// Load comments with `comments_template()`.
				$cform = get_echo( 'comments_template' );

				// First request will hit the database.
				$num_queries = $wpdb->num_queries;
				get_comment_meta( $comment_ids[0], 'sauce' );
				$this->assertSame( $num_queries + 1, $wpdb->num_queries );

				// Second and third requests should be in cache.
				get_comment_meta( $comment_ids[1], 'sauce' );
				get_comment_meta( $comment_ids[2], 'sauce' );
				$this->assertSame( $num_queries + 1, $wpdb->num_queries );
			}
		}
	}

	/**
	 * @ticket 44467
	 */
	public function test_add_metadata_sets_comments_last_changed() {
		$comment_id = self::factory()->comment->create();

		wp_cache_delete( 'last_changed', 'comment' );

		$this->assertIsInt( add_metadata( 'comment', $comment_id, 'foo', 'bar' ) );
		$this->assertNotFalse( wp_cache_get_last_changed( 'comment' ) );
	}

	/**
	 * @ticket 44467
	 */
	public function test_update_metadata_sets_comments_last_changed() {
		$comment_id = self::factory()->comment->create();

		wp_cache_delete( 'last_changed', 'comment' );

		$this->assertIsInt( update_metadata( 'comment', $comment_id, 'foo', 'bar' ) );
		$this->assertNotFalse( wp_cache_get_last_changed( 'comment' ) );
	}

	/**
	 * @ticket 44467
	 */
	public function test_delete_metadata_sets_comments_last_changed() {
		$comment_id = self::factory()->comment->create();

		update_metadata( 'comment', $comment_id, 'foo', 'bar' );
		wp_cache_delete( 'last_changed', 'comment' );

		$this->assertTrue( delete_metadata( 'comment', $comment_id, 'foo' ) );
		$this->assertNotFalse( wp_cache_get_last_changed( 'comment' ) );
	}
}
