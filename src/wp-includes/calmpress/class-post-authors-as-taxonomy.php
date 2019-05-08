<?php
/**
 * Implementation saving post authors in a dedicated taxonomy.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\post_authors;

/**
 * Utility class to register, enable the admin and extract author information
 * from an authors taxonomy.
 *
 * @since 1.0.0
 */
class Post_Authors_As_Taxonomy {

	// Taxonomy related constants.
	const TAXONOMY_NAME = 'calm_authors';
	const TAXONOMY_SLUG = 'author';

	// Constants used to indicate the required sorting in get_authors.
	const SORT_TYPE_NUMBER_POSTS_ASC  = 1;
	const SORT_TYPE_NUMBER_POSTS_DESC = 2;
	const SORT_TYPE_NONE              = 3;
	const SORT_TYPE_NAME_ASC          = 4;
	const SORT_TYPE_NAME_DESC         = 5;

	/**
	 * Perform required initializations in boot time.
	 *
	 * Needs to be called before the init action is triggered.
	 *
	 * @since 1.0.0
	 */
	public static function init() {

		// Create the taxonomy on init action.
		add_action(
			'init', function () {
				$labels = [
					'name'                       => __( 'Authors' ),
					'singular_name'              => __( 'Author' ),
					'separate_items_with_commas' => __( 'Separate authors with commas' ),
					'choose_from_most_used'      => __( 'Choose from the most used authors' ),
					'not_found'                  => __( 'No authors found.' ),
					'add_new_item'               => __( 'Add New Author' ),
					'edit_item'                  => __( 'Edit Author' ),
					'search_items'               => __( 'Search Authors' ),
					'update_item'                => __( 'Update Author' ),
					'back_to_items'              => __( '&larr; Back to Authors' ),
					'view_item'                  => __( 'View Author' ),
				];

				$args = [
					'labels'            => $labels,
					'public'            => true,
					'hierarchical'      => false,
					'show_in_rest'      => true,
					'rewrite'           => [
						'slug' => self::TAXONOMY_SLUG,
					],
					'show_admin_column' => true,
					'show_in_menu'      => false,
				];

				// Do not associate with any CPT right now as it will be done
				// on a later hook.
				register_taxonomy( self::TAXONOMY_NAME, [], $args );
			}
		);

		// Associate the taxonomy with CPTs that support authors. Done after
		// all the post type are supposed to be registered.
		add_action(
			'init', function () {
				$post_types = get_post_types();
				foreach ( $post_types as $key => $post_type ) {
					if ( post_type_supports( $post_type, 'author' ) ) {
						register_taxonomy_for_object_type( self::TAXONOMY_NAME, $post_type );
					}
				}
			}, PHP_INT_MAX
		);

		// Add the admin menu.
		add_action(
			'admin_menu', function () {
				$tax = get_taxonomy( self::TAXONOMY_NAME );
				add_menu_page( __( 'Authors' ), __( 'Authors' ), $tax->cap->manage_terms, 'edit-tags.php?taxonomy=' . $tax->name, '', 'dashicons-admin-users', 69 );
			}
		);
	}

	/**
	 * Add author management as an admin menu.
	 *
	 * @since 1.0.0
	 */
	public static function admin_menu() {
		$tax = get_taxonomy( internal\TAXONOMY_NAME );
		add_menu_page( __( 'Authors', 'authors_as_taxonomy' ), __( 'Authors', 'authors_as_taxonomy' ), $tax->cap->manage_terms, 'edit-tags.php?taxonomy=' . $tax->name, '', 'dashicons-admin-users', 69 );
	}

	/**
	 * Get the authors of a post.
	 *
	 * Returns an array of authors if there are any tht are associated with
	 * the post via the authors taxonomy.
	 * The array might be empty if there are no authors or an error occurred.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post the post for which to retrieve authors.
	 *
	 * @return calmpress\post_authors\Post_Author[] The post authors.
	 */
	public static function post_authors( \WP_Post $post ) : array {
		// Get the author terms associated with the current post.
		$authors = get_the_terms( $post, self::TAXONOMY_NAME );

		// If the call errored, output the error message.
		if ( is_wp_error( $authors ) ) {
			trigger_error( $authors->get_error_message() );
			return [];
		}

		// If there are no authors we are likely to get a false value.
		if ( ! is_array( $authors ) ) {
			return [];
		}

		return array_map(
			function ( $term ) {
					return new Taxonomy_Based_Post_Author( $term );
			}, $authors
		);
	}

	/**
	 * Get the number of post published by the authors of a post.
	 *
	 * For multiple author a heuristic is being used which can produce slightly
	 * inaccurate number if the authors publish together many posts.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post the post for which to retrieve the count.
	 *
	 * @return int The number of posts.
	 */
	public static function authors_post_count( \WP_Post $post ) : int {
		$authors = self::post_authors( $post );

		if ( empty( $authors ) ) {
			return 0;
		}

		// For the sake of avoiding DB queries a heuristic is being used that
		// if there are more than one authors, they are not sharing other posts and
		// therefor it is good enough to just avoid counting this post multiple
		// times.
		$count = 1 - count( $authors );
		foreach ( $authors as $author ) {
			$count += $author->posts_count();
		}

		return $count;
	}

	/**
	 * Get the URL of an archive page which includes posts from all the authors
	 * of a specific post.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post $post the post for which to retrieve authors.
	 *
	 * @return string The URL, or empty string if there are no authors.
	 */
	public static function combined_authors_url( \WP_Post $post ) : string {
		$authors = self::post_authors( $post );

		if ( empty( $authors ) ) {
			return '';
		}

		if ( 1 === count( $authors ) ) {
			return $authors[0]->posts_url();
		}

		/*
		 * More than one author therefor need to have the slugs separated by ",".
		 */

		$url = $authors[0]->posts_url();

		// Need to get rid of possible slash before appending other slugs.
		$url = rtrim( $url, '/' );

		// Append the other slugs.
		for ( $i = 1; $i < count( $authors ); $i++ ) {
			$url .= ',' . $authors[ $i ]->slug();
		}

		// Add slash in the end if needed.
		return user_trailingslashit( $url, 'category' );
	}

	/**
	 * Get all of the authors that match a criteria.
	 *
	 * Returns an array of authors
	 *
	 * @since 1.0.0
	 *
	 * @param int $number The maximal number of authors to return. A special
	 *                    value of 0 indicates that all authors should be returned.
	 *
	 * @param int $sort_type Indicates in which order the authors should be ordered
	 *                       in the returned array, and more importantly,
	 *                       implicitly indicates which authors have a preference
	 *                       to be returned if the are more authors then the
	 *                       limit specified in the number parameter.
	 *                       possible values:
	 *                       SORT_TYPE_NONE : no explicit sort order.
	 *                       SORT_TYPE_NUMBER_POSTS_ASC : Ascending by number of posts.
	 *                       SORT_TYPE_NUMBER_POSTS_DESC : Descending by number of posts.
	 *                       SORT_TYPE_NAME_ASC : Ascending by author name.
	 *                       SORT_TYPE_NAME_DESC : Descending by author name.
	 *
	 * @param bool $include_empty Indicates if authors with no posts should be returned.
	 * @param calmpress\post_authors\Post_Author[] $exclude Authors to always exclude.
	 *
	 * @return calmpress\post_authors\Post_Author[] The authors.
	 */
	public static function get_authors( int $number,
									int $sort_type,
									bool $include_empty,
									array $exclude ) {

		$args['number'] = $number;

		switch ( $sort_type ) {
			case ( self::SORT_TYPE_NAME_ASC ):
				$args['orderby'] = 'name';
				$args['order']   = 'ASC';
				break;
			case ( self::SORT_TYPE_NAME_DESC ):
				$args['orderby'] = 'name';
				$args['order']   = 'DESC';
				break;
			case ( self::SORT_TYPE_NUMBER_POSTS_ASC ):
				$args['orderby'] = 'count';
				$args['order']   = 'ASC';
				break;
			case ( self::SORT_TYPE_NUMBER_POSTS_DESC ):
				$args['orderby'] = 'count';
				$args['order']   = 'DESC';
				break;
			case ( self::SORT_TYPE_NONE ):
				// This case is here just to be able to issue an error for illegal values.
				break;
			default:
				trigger_error( 'Unknown sort type: ' . $sort_type );
				break;
		}

		if ( $include_empty ) {
			$args['hide_empty'] = false;
		} else {
			$args['hide_empty'] = true;
		}

		$args['exclude'] = [];
		foreach ( $exclude as $author ) {
			$args['exclude'][] = $author->term_id();
		}

		$authors = get_terms( self::TAXONOMY_NAME, $args );

		return array_map(
			function ( $term ) {
					return new Taxonomy_Based_Post_Author( $term );
			}, $authors
		);
	}
}
