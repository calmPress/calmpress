<?php
/**
 * Author Template functions for use in themes.
 *
 * These functions must be used within the WordPress Loop.
 *
 * @link https://codex.wordpress.org/Author_Templates
 *
 * @package WordPress
 * @subpackage Template
 */

use calmpress\post_authors;

/**
 * Retrieve the author of the current post.
 *
 * @since 1.5.0
 *
 * @global object $authordata The current author's DB object.
 *
 * @return string|null The author's display name.
 */
function get_the_author() {
	global $post;

	$display_name = null;

	// make sure we are in the post loop context.
	if ( isset( $post ) ) {
		 $authors = post_authors\Post_Authors_As_Taxonomy::post_authors( $post );

		 // For backward compatibility reasons, prefer to return a null over empty string
		 // when there is no associated author.
		 if ( ! empty( $authors) ) {
			 $name_array = array_map(function ( $author ) {
	 			return $author->name();
	 		}, $authors );

			$display_name = join( ', ', $name_array );
		} else {
			$display_name = '';
		}
	}

	/**
	 * Filters the display name of the current post's author.
	 *
	 * @since 2.9.0
	 *
	 * @param string $authordata->display_name The author's display name.
	 */
	return apply_filters( 'the_author', $display_name );
}

/**
 * Display the name of the author of the current post.
 *
 * The behavior of this function is based off of old functionality predating
 * get_the_author(). This function is not deprecated, but is designed to echo
 * the value from get_the_author() and as an result of any old theme that might
 * still use the old behavior will also pass the value from get_the_author().
 *
 * The normal, expected behavior of this function is to echo the author and not
 * return it. However, backward compatibility has to be maintained.
 *
 * @since 0.71
 * @see get_the_author()
 * @link https://codex.wordpress.org/Template_Tags/the_author
 *
 * @return string|null The author's display name, from get_the_author().
 */
function the_author() {

	echo get_the_author();

	return get_the_author();
}

/**
 * Retrieve the editor who last edited the current post.
 *
 * In WordPress this function used to be called get_the_modified_author
 *
 * @since calmPress 1.0.0
 *
 * @return string The editor's display name.
 */
function get_the_modified_editor() {
	$last_id = get_post_meta( get_post()->ID, '_edit_last', true );

	if ( $last_id ) {
		$last_user = get_userdata( $last_id );

		/**
		 * Filters the display name of the author who last edited the current post.
		 *
		 * @since 2.8.0
		 *
		 * @param string $last_user->display_name The author's display name.
		 */
		return apply_filters( 'the_modified_editor', $last_user->display_name );
	}

	return '';
}

/**
 * Display the name of the editor who last edited the current post,
 * if the editors's ID is available.
 *
 * In WordPress this function used to be called the_modified_author
 *
 * @since calmPress 1.0.0
 *
 * @see get_the_editor()
 */
function the_modified_editor() {
	echo get_the_modified_author();
}

/**
 * Retrieves the requested data of the author of the current post.
 *
 * Valid values for the `$field` parameter include:
 *
 * - admin_color
 * - comment_shortcuts
 * - description
 * - display_name
 * - first_name
 * - ID
 * - last_name
 * - nickname
 * - plugins_last_view
 * - plugins_per_page
 * - syntax_highlighting
 * - user_activation_key
 * - user_description
 * - user_email
 * - user_firstname
 * - user_lastname
 * - user_level
 * - user_login
 * - user_nicename
 * - user_pass
 * - user_registered
 * - user_status
 *
 * @since 2.8.0
 *
 * @global object $authordata The current author's DB object.
 *
 * @param string    $field   Optional. The user field to retrieve. Default empty.
 * @param int|false $user_id Optional. User ID.
 * @return string The author's field from the current author's DB object, otherwise an empty string.
 */
function get_the_author_meta( $field = '', $user_id = false ) {
	$original_user_id = $user_id;

	if ( ! $user_id ) {
		// If user id is not given assume it is the author of current post if we are in a loop,
		// and not an actual user when trying to get a description.
		global $post, $authordata;

		if ( ! empty( $post ) && in_array( $field, [ 'description', 'user_description', 'display_name' ], true ) ) {

			$authors = calmpress\post_authors\Post_Authors_As_Taxonomy::post_authors( $post );

			if ( 'display_name' === $field ) {
				$name = join( ', ', array_map( $authors, function( $author ) {
					return $author->name();
				} ) );

				return $name;
			}

			$description = '';
			foreach ( $authors as $author ) {
				$description .= '<div>' . $author->description() . '</div>';
			}

			return $description;
		}

		$user_id = isset( $authordata->ID ) ? $authordata->ID : 0;
	} else {
		$authordata = get_userdata( $user_id );
	}

	if ( in_array( $field, array( 'login', 'pass', 'nicename', 'email', 'registered', 'activation_key', 'status' ) ) ) {
		$field = 'user_' . $field;
	}

	$value = isset( $authordata->$field ) ? $authordata->$field : '';

	/**
	 * Filters the value of the requested user metadata.
	 *
	 * The filter name is dynamic and depends on the $field parameter of the function.
	 *
	 * @since 2.8.0
	 * @since 4.3.0 The `$original_user_id` parameter was added.
	 *
	 * @param string    $value            The value of the metadata.
	 * @param int       $user_id          The user ID for the value.
	 * @param int|false $original_user_id The original user ID, as passed to the function.
	 */
	return apply_filters( "get_the_author_{$field}", $value, $user_id, $original_user_id );
}

/**
 * Outputs the field from the user's DB object. Defaults to current post's author.
 *
 * @since 2.8.0
 *
 * @param string    $field   Selects the field of the users record. See get_the_author_meta()
 *                           for the list of possible fields.
 * @param int|false $user_id Optional. User ID.
 *
 * @see get_the_author_meta()
 */
function the_author_meta( $field = '', $user_id = false ) {
	$author_meta = get_the_author_meta( $field, $user_id );

	/**
	 * The value of the requested user metadata.
	 *
	 * The filter name is dynamic and depends on the $field parameter of the function.
	 *
	 * @since 2.8.0
	 *
	 * @param string    $author_meta The value of the metadata.
	 * @param int|false $user_id     The user ID.
	 */
	echo apply_filters( "the_author_{$field}", $author_meta, $user_id );
}

/**
 * Retrieve the author's name.
 *
 * @since 3.0.0
 *
 * @return string The result of get_the_author().
 */
function get_the_author_link() {
	return get_the_author();
}

/**
 * Display the author's name.
 *
 * Echo the author's name.
 *
 * @since 2.1.0
 */
function the_author_link() {
	echo get_the_author_link();
}

/**
 * Retrieve the number of posts by the author of the current post.
 *
 * @since 1.5.0
 *
 * @return int The number of posts by the author. In case of multiple authors
 *             the number is an aggregate of all authors.
 */
function get_the_author_posts() {
	$post = get_post();
	if ( ! $post ) {
		return 0;
	}

	return post_authors\Post_Authors_As_Taxonomy::authors_post_count( $post );
}

/**
 * Display the number of posts by the author of the current post.
 *
 * @link https://codex.wordpress.org/Template_Tags/the_author_posts
 * @since 0.71
 */
function the_author_posts() {
	echo get_the_author_posts();
}

/**
 * Retrieves an HTML link to the author page of the current post's author.
 * If there are more than one author the HTML will include links to the post's
 * of each of them.
 *
 * @since 4.4.0
 * @since calmPress 1.0.0
 *
 * @global WP_Post $post The current post's DB object.
 *
 * @return string An HTML with link(s) to the author post page(s).
 */
function get_the_author_posts_link() {
	global $post;
	if ( ! is_object( $post ) ) {
		return '';
	}

	$authors = post_authors\Post_Authors_As_Taxonomy::post_authors( $post );
	if ( empty( $authors ) ) {
		return '';
	}

	$links_array = array_map(function ( $author ) {
	   return sprintf( '<a href="%1$s" title="%2$s" rel="author">%3$s</a>',
	   		esc_url( $author->posts_url() ),
	   		/* translators: %s: author's display name */
	   		esc_attr( sprintf( __( 'Posts by %s' ), $author->name() ) ),
	   		esc_html( $author->name() )
	   	);
   }, $authors );

   return join( ', ', $links_array );
}

/**
 * Displays an HTML link to the author page of the current post's author.
 *
 * @since 1.2.0
 * @since 4.4.0 Converted into a wrapper for get_the_author_posts_link()
 */
function the_author_posts_link() {
	echo get_the_author_posts_link();
}

/**
 * Retrieve the URL to the author page for the user with the ID provided.
 *
 * For calmPress, since there is no user posts page, but we do not want to break
 * themes, we are trying to detect when the function is being called from the loop
 * and in that case return the url to the relevant author's post page.
 *
 * If the function is called out of the loop, or there are no authors it returns
 * the url of the home page.
 *
 * @since 2.1.0
 * @since calmPress 1.0.0
 *
 * @global WP_Post $post.
 *
 * @param int    $author_id       Author ID.
 * @param string $author_nicename Optional. The author's nicename (slug). Default empty.
 * @return string The URL to the author's page.
 */
function get_author_posts_url( $author_id, $author_nicename = '' ) {
	global $post;
	$auth_ID = (int) $author_id;

	// If we are not in the loop return the homepage.
	if ( ! is_object( $post ) ) {
		return home_url( '/' );
	}

	$link = '';
	// if the author id is the same as the post author we are most likely in a loop.
	if ( $post->post_author == $auth_ID ) {
		return post_authors\Post_Authors_As_Taxonomy::combined_authors_url( $post );
	}

	return home_url( '/' );
}

/**
 * List all the authors of the site, with several options available.
 *
 * @link https://codex.wordpress.org/Template_Tags/wp_list_authors
 *
 * @since 1.2.0
 * @since calmPress 1.0.0
 *
 * @param string|array $args {
 *     Optional. Array or string of default arguments.
 *
 *     @type string       $orderby       How to sort the authors. Accepts 'name',
 *                                       'display_name', 'post_count'. Default 'name'.
 *     @type string       $order         Sorting direction for $orderby. Accepts 'ASC', 'DESC'. Default 'ASC'.
 *     @type int          $number        Maximum authors to return or display. Default empty (all authors).
 *     @type bool         $optioncount   Show the count in parenthesis next to the author's name. Default false.
 *     @type bool         $hide_empty    Whether to hide any authors with no posts. Default true.
 *     @type bool         $echo          Whether to output the result or instead return it. Default true.
 *     @type string       $style         If 'list', each author is wrapped in an `<li>` element, otherwise the authors
 *                                       will be separated by commas.
 *     @type bool         $html          Whether to list the items in HTML form or plaintext. Default true.
 *     @type array|string $exclude       Array or comma/space-separated list of author IDs to exclude. Default empty.
 * }
 * @return string|void The output, if echo is set to false.
 */
function wp_list_authors( $args = '' ) {

	$defaults = array(
		'orderby' => 'name', 'order' => 'ASC', 'number' => '',
		'optioncount' => false,
		'hide_empty' => true,
		'echo' => true,
		'style' => 'list', 'html' => true, 'exclude' => '', 'include' => ''
	);

	$args = wp_parse_args( $args, $defaults );

	$return = '';

	// Set the sort parameter.
	$order = post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NONE;
	if ( 'name' === $args['orderby'] || 'display_name' === $args['orderby'] ) {
		if ( 'DESC' === $args['order'] ) {
			$order = post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NAME_DESC;
		} else {
			$order = post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NAME_ASC;
		}
	} elseif ( 'post_count' === $args['orderby'] ) {
		if ( 'DESC' === $args['order'] ) {
			$order = post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NUMBER_POSTS_DESC;
		} else {
			$order = post_authors\Post_Authors_As_Taxonomy::SORT_TYPE_NUMBER_POSTS_ASC;
		}
	}

	// Convert the exclude parameter to array of authors.
	$exclude_arr = [];
	if ( ! empty( $args['exclude'] ) ) {
		$exclude_arr = array_map( function ( $term_id ) {
			$term = get_term( $term_id, post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME );
			return new post_authors\Taxonomy_Based_Post_Author( $term );
			}, wp_parse_id_list( $args['exclude'] ) );
	}

	$authors = post_authors\Post_Authors_As_Taxonomy::get_authors( (int) $args['number'],
		$order,
		! $args['hide_empty'],
		$exclude_arr
	);

	foreach ( $authors as $author ) {

		$posts = $author->posts_count();

		$name = $author->name();

		if ( ! $args['html'] ) {
			$return .= $name . ', ';

			continue; // No need to go further to process HTML.
		}

		if ( 'list' == $args['style'] ) {
			$return .= '<li>';
		}

		$link = sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
			$author->posts_url(),
			/* translators: %s: author's display name */
			esc_attr( sprintf( __( 'Posts by %s' ), $name ) ),
			$name
		);

		if ( $args['optioncount'] ) {
			$link .= ' (' . $posts . ')';
		}

		$return .= $link;
		$return .= ( 'list' == $args['style'] ) ? '</li>' : ', ';
	}

	$return = rtrim( $return, ', ' );

	if ( ! $args['echo'] ) {
		return $return;
	}
	echo $return;
}

/**
 * Determines whether this site has more than one author.
 *
 * Checks to see if more than one author has published posts.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 3.2.0
 *
 * @return bool Whether or not we have more than one author
 */
function is_multi_author() {

	if ( false === ( $is_multi_author = get_transient( 'is_multi_author' ) ) ) {
		$terms = get_terms( post_authors\Post_Authors_As_Taxonomy::TAXONOMY_NAME, [
			'number' => 2,
			'fields' => 'ids',
		] );
		$is_multi_author = 1 < count( $terms ) ? 1 : 0;
		set_transient( 'is_multi_author', $is_multi_author );
	}

	/**
	 * Filters whether the site has more than one author with published posts.
	 *
	 * @since 3.2.0
	 *
	 * @param bool $is_multi_author Whether $is_multi_author should evaluate as true.
	 */
	return apply_filters( 'is_multi_author', (bool) $is_multi_author );
}

/**
 * Helper function to clear the cache for number of authors.
 *
 * @since 3.2.0
 * @access private
 */
function __clear_multi_author_cache() {
	delete_transient( 'is_multi_author' );
}

/**
 * Retrieve the editor (what in WP was author) of the current post.
 *
 * Unlike the WP original get_the_author function, this should be used only
 * from inside calmPress code.
 *
 * @since calmPress 1.0.0
 *
 * @global object $authordata The current author's DB object.
 *
 * @return string|null The editor's display name.
 */
function _get_the_editor() {
	global $authordata;

	return is_object($authordata) ? $authordata->display_name : null;
}

/**
 * calmPress alias to get_the_author_meta meant to have a better distinction
 * between frontend and backend usage.
 *
 * Valid values for the `$field` parameter include:
 *
 * - admin_color
 * - comment_shortcuts
 * - description
 * - display_name
 * - first_name
 * - ID
 * - last_name
 * - nickname
 * - plugins_last_view
 * - plugins_per_page
 * - syntax_highlighting
 * - user_activation_key
 * - user_description
 * - user_email
 * - user_firstname
 * - user_lastname
 * - user_level
 * - user_login
 * - user_nicename
 * - user_pass
 * - user_registered
 * - user_status
 *
 * @since calmPress 1.0.0
 *
 * @global object $authordata The current author's DB object.
 *
 * @param string $field   Optional. The user field to retrieve. Default empty.
 * @param int    $user_id Optional. User ID.
 * @return string The author's field from the current author's DB object, otherwise an empty string.
 */
function _get_the_editor_meta( $field = '', $user_id = false ) {
	return get_the_author_meta( $field, $user_id );
}
