<?php
/**
 * Implementation of DB upgrade from user based authors to authors as taxonomy.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\post_authors;

/**
 * Utility class to gather functions which upgrade the DB from user based authors
 * to taxonomy based authors.
 *
 * @since 1.0.0
 */
class Post_Authors_As_Taxonomy_Db_Upgrade {

	/**
	 * The actual upgrade code.
	 *
	 * Run over all users, find if they have posts associated with them and if so
	 * add them as authors to the authors taxonomy using their display name as
	 * term name and bio as description, then associate the posts with the term.
	 *
	 * @since 1.0.0
	 */
	public static function upgrade() {
		// get all users with published content.
		$users = get_users(
			[
				'has_published_posts' => true,
				'count_total'         => false,
				'number'              => -1,
			]
		);

		// Per user create a term in the authors taxonomy and associate all his posts with the term.
		foreach ( $users as $user ) {
			// First check if we have an author like that and if not create it.
			$term = get_term_by( 'name', $user->display_name, Post_Authors_As_Taxonomy::TAXONOMY_NAME );
			if ( ! $term ) {
				// Create a term in the authors taxonomy for the user based on the user's info.
				$args = [ 'description' => $user->description ];
				$term = wp_insert_term( $user->display_name, Post_Authors_As_Taxonomy::TAXONOMY_NAME, $args );
				$term = get_term( $term['term_id'], Post_Authors_As_Taxonomy::TAXONOMY_NAME );
			}

			$post_ids = get_posts(
				[
					'author'              => $user->ID,
					'post_type'           => 'any',
					'posts_per_page'      => -1,
					'post_status'         => [ 'publish', 'inherit' ],
					'fields'              => 'ids',
					'ignore_sticky_posts' => true,
				]
			);

			foreach ( $post_ids as $post_id ) {
				wp_set_post_terms( $post_id, [ $term->term_id ], Post_Authors_As_Taxonomy::TAXONOMY_NAME, true );
			}
		}
	}
}
