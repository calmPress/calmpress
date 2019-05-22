<?php
/**
 * WordPress Feed API
 *
 * Many of the functions used in here belong in The Loop, or The Loop for the
 * Feeds.
 *
 * @package WordPress
 * @subpackage Feed
 * @since 2.1.0
 */

/**
 * RSS container for the bloginfo function.
 *
 * You can retrieve anything that you can using the get_bloginfo() function.
 * Everything will be stripped of tags and characters converted, when the values
 * are retrieved for use in the feeds.
 *
 * @since 1.5.1
 * @see get_bloginfo() For the list of possible values to display.
 *
 * @param string $show See get_bloginfo() for possible values.
 * @return string
 */
function get_bloginfo_rss( $show = '' ) {
	$info = strip_tags( get_bloginfo( $show ) );
	/**
	 * Filters the bloginfo for use in RSS feeds.
	 *
	 * @since 2.2.0
	 *
	 * @see convert_chars()
	 * @see get_bloginfo()
	 *
	 * @param string $info Converted string value of the blog information.
	 * @param string $show The type of blog information to retrieve.
	 */
	return apply_filters( 'get_bloginfo_rss', convert_chars( $info ), $show );
}

/**
 * Display RSS container for the bloginfo function.
 *
 * You can retrieve anything that you can using the get_bloginfo() function.
 * Everything will be stripped of tags and characters converted, when the values
 * are retrieved for use in the feeds.
 *
 * @since 0.71
 * @see get_bloginfo() For the list of possible values to display.
 *
 * @param string $show See get_bloginfo() for possible values.
 */
function bloginfo_rss( $show = '' ) {
	/**
	 * Filters the bloginfo for display in RSS feeds.
	 *
	 * @since 2.1.0
	 *
	 * @see get_bloginfo()
	 *
	 * @param string $rss_container RSS container for the blog information.
	 * @param string $show          The type of blog information to retrieve.
	 */
	echo apply_filters( 'bloginfo_rss', get_bloginfo_rss( $show ), $show );
}

/**
 * Retrieve the default feed.
 *
 * The default feed is 'rss2', unless a plugin changes it through the
 * {@see 'default_feed'} filter.
 *
 * @since 2.5.0
 *
 * @return string Default feed, or for example 'rss2', etc.
 */
function get_default_feed() {
	/**
	 * Filters the default feed type.
	 *
	 * @since 2.5.0
	 *
	 * @param string $feed_type Type of default feed.
	 */
	$default_feed = apply_filters( 'default_feed', 'rss2' );
	return $default_feed;
}

/**
 * Retrieve the blog title for the feed title.
 *
 * @since 2.2.0
 * @since 4.4.0 The optional `$sep` parameter was deprecated and renamed to `$deprecated`.
 *
 * @return string The document title.
 */
function get_wp_title_rss() {

	/**
	 * Filters the blog title for use as the feed title.
	 *
	 * @since 2.2.0
	 * @since 4.4.0 The `$sep` parameter was deprecated and renamed to `$deprecated`.
	 *
	 * @param string $title      The current blog title.
	 * @param string $deprecated Unused.
	 */
	return apply_filters( 'get_wp_title_rss', wp_get_document_title(), '&#8211;' );
}

/**
 * Display the blog title for display of the feed title.
 *
 * @since 2.2.0
 * @since 4.4.0 The optional `$sep` parameter was deprecated and renamed to `$deprecated`.
 */
function wp_title_rss() {

	/**
	 * Filters the blog title for display of the feed title.
	 *
	 * @since 2.2.0
	 * @since 4.4.0 The `$sep` parameter was deprecated and renamed to `$deprecated`.
	 *
	 * @see get_wp_title_rss()
	 *
	 * @param string $wp_title_rss The current blog title.
	 * @param string $deprecated   Unused.
	 */
	echo apply_filters( 'wp_title_rss', get_wp_title_rss(), '&#8211;' );
}

/**
 * Retrieve the current post title for the feed.
 *
 * @since 2.0.0
 *
 * @return string Current post title.
 */
function get_the_title_rss() {
	$title = get_the_title();

	/**
	 * Filters the post title for use in a feed.
	 *
	 * @since 1.2.0
	 *
	 * @param string $title The current post title.
	 */
	$title = apply_filters( 'the_title_rss', $title );
	return $title;
}

/**
 * Display the post title in the feed.
 *
 * @since 0.71
 */
function the_title_rss() {
	echo get_the_title_rss();
}

/**
 * Retrieve the post content for feeds.
 *
 * @since 2.9.0
 * @see get_the_content()
 *
 * @param string $feed_type The type of feed.
 * @return string The filtered content.
 */
function get_the_content_feed( $feed_type = null ) {
	if ( ! $feed_type ) {
		$feed_type = get_default_feed();
	}

	/** This filter is documented in wp-includes/post-template.php */
	$content = apply_filters( 'the_content', get_the_content() );
	$content = str_replace( ']]>', ']]&gt;', $content );
	/**
	 * Filters the post content for use in feeds.
	 *
	 * @since 2.9.0
	 *
	 * @param string $content   The current post content.
	 * @param string $feed_type Type of feed.
	 */
	return apply_filters( 'the_content_feed', $content, $feed_type );
}

/**
 * Display the post content for feeds.
 *
 * @since 2.9.0
 *
 * @param string $feed_type The type of feed.
 */
function the_content_feed( $feed_type = null ) {
	echo get_the_content_feed( $feed_type );
}

/**
 * Display the post excerpt for the feed.
 *
 * @since 0.71
 */
function the_excerpt_rss() {
	$output = get_the_excerpt();
	/**
	 * Filters the post excerpt for a feed.
	 *
	 * @since 1.2.0
	 *
	 * @param string $output The current post excerpt.
	 */
	echo apply_filters( 'the_excerpt_rss', $output );
}

/**
 * Display the permalink to the post for use in feeds.
 *
 * @since 2.3.0
 */
function the_permalink_rss() {
	/**
	 * Filters the permalink to the post for use in feeds.
	 *
	 * @since 2.3.0
	 *
	 * @param string $post_permalink The current post permalink.
	 */
	echo esc_url( apply_filters( 'the_permalink_rss', get_permalink() ) );
}

/**
 * Outputs the link to the comments for the current post in an xml safe way
 *
 * @since 3.0.0
 * @return none
 */
function comments_link_feed() {
	/**
	 * Filters the comments permalink for the current post.
	 *
	 * @since 3.6.0
	 *
	 * @param string $comment_permalink The current comment permalink with
	 *                                  '#comments' appended.
	 */
	echo esc_url( apply_filters( 'comments_link_feed', get_comments_link() ) );
}

/**
 * Display the feed GUID for the current comment.
 *
 * @since 2.5.0
 *
 * @param int|WP_Comment $comment_id Optional comment object or id. Defaults to global comment object.
 */
function comment_guid( $comment_id = null ) {
	echo esc_url( get_comment_guid( $comment_id ) );
}

/**
 * Retrieve the feed GUID for the current comment.
 *
 * @since 2.5.0
 *
 * @param int|WP_Comment $comment_id Optional comment object or id. Defaults to global comment object.
 * @return false|string false on failure or guid for comment on success.
 */
function get_comment_guid( $comment_id = null ) {
	$comment = get_comment( $comment_id );

	if ( ! is_object( $comment ) ) {
		return false;
	}

	return get_the_guid( $comment->comment_post_ID ) . '#comment-' . $comment->comment_ID;
}

/**
 * Display the link to the comments.
 *
 * @since 1.5.0
 * @since 4.4.0 Introduced the `$comment` argument.
 *
 * @param int|WP_Comment $comment Optional. Comment object or id. Defaults to global comment object.
 */
function comment_link( $comment = null ) {
	/**
	 * Filters the current comment's permalink.
	 *
	 * @since 3.6.0
	 *
	 * @see get_comment_link()
	 *
	 * @param string $comment_permalink The current comment permalink.
	 */
	echo esc_url( apply_filters( 'comment_link', get_comment_link( $comment ) ) );
}

/**
 * Retrieve all of the post categories, formatted for use in feeds.
 *
 * All of the categories for the current post in the feed loop, will be
 * retrieved and have feed markup added, so that they can easily be added to the
 * feeds.
 *
 * @since 2.1.0
 *
 * @param string $type Optional, default is the type returned by get_default_feed().
 * @return string All of the post categories for displaying in the feed.
 */
function get_the_category_rss( $type = null ) {
	if ( empty( $type ) ) {
		$type = get_default_feed();
	}
	$categories = get_the_category();
	$tags       = get_the_tags();
	$the_list   = '';
	$cat_names  = array();

	/**
	 * Filters the type of sanitization to be done to a category name.
	 *
	 * @since calmPress 1.0.0
	 *
	 * @param string $sanitization Type of sanitization.
	 * @param string $type         Type of feed.
	 */
	$filter = apply_filters( 'calm_feed_category_name_sanitization_type', 'rss', $type );

	if ( ! empty( $categories ) ) {
		foreach ( (array) $categories as $category ) {
			$cat_names[] = sanitize_term_field( 'name', $category->name, $category->term_id, 'category', $filter );
		}
	}

	if ( ! empty( $tags ) ) {
		foreach ( (array) $tags as $tag ) {
			$cat_names[] = sanitize_term_field( 'name', $tag->name, $tag->term_id, 'post_tag', $filter );
		}
	}

	$cat_names = array_unique( $cat_names );

	foreach ( $cat_names as $cat_name ) {

		$element = "\t\t<category><![CDATA[" . @html_entity_decode( $cat_name, ENT_COMPAT, get_option( 'blog_charset' ) ) . "]]></category>\n";

		/**
		 * Filters the content of the category name element.
		 *
		 * @since calmPress 1.0.0
		 *
		 * @param string $element  The element.
		 * @param string $cat_name The category name.
		 * @param string $type     Type of feed.
		 */
		$the_list .= apply_filters( 'calm_feed_category_name_element', $element, $cat_name, $type );
	}

	/**
	 * Filters all of the post categories for display in a feed.
	 *
	 * @since 1.2.0
	 *
	 * @param string $the_list All of the RSS post categories.
	 * @param string $type     Type of feed.
	 */
	return apply_filters( 'the_category_rss', $the_list, $type );
}

/**
 * Display the post categories in the feed.
 *
 * @since 0.71
 * @see get_the_category_rss() For better explanation.
 *
 * @param string $type Optional, default is the type returned by get_default_feed().
 */
function the_category_rss( $type = null ) {
	echo get_the_category_rss( $type );
}

/**
* Display the HTML type based on the blog setting.
 *
 * The two possible values are either 'xhtml' or 'html'.
 *
 * @since 2.2.0
 */
function html_type_rss() {
	$type = get_bloginfo( 'html_type' );
	if ( strpos( $type, 'xhtml' ) !== false ) {
		$type = 'xhtml';
	} else {
		$type = 'html';
	}
	echo $type;
}

/**
 * Display the rss enclosure for the current post.
 *
 * Uses the global $post to check whether the post requires a password and if
 * the user has the password for the post. If not then it will return before
 * displaying.
 *
 * Also uses the function get_post_custom() to get the post's 'enclosure'
 * metadata field and parses the value to display the enclosure(s). The
 * enclosure(s) consist of enclosure HTML tag(s) with a URI and other
 * attributes.
 *
 * @since 1.5.0
 */
function rss_enclosure() {

	foreach ( (array) get_post_custom() as $key => $val ) {
		if ( $key == 'enclosure' ) {
			foreach ( (array) $val as $enc ) {
				$enclosure = explode( "\n", $enc );

				// only get the first element, e.g. audio/mpeg from 'audio/mpeg mpga mp2 mp3'
				$t    = preg_split( '/[ \t]/', trim( $enclosure[2] ) );
				$type = $t[0];

				/**
				 * Filters the RSS enclosure HTML link tag for the current post.
				 *
				 * @since 2.2.0
				 *
				 * @param string $html_link_tag The HTML link tag with a URI and other attributes.
				 */
				echo apply_filters( 'rss_enclosure', '<enclosure url="' . esc_url( trim( $enclosure[0] ) ) . '" length="' . absint( trim( $enclosure[1] ) ) . '" type="' . esc_attr( $type ) . '" />' . "\n" );
			}
		}
	}
}

/**
 * Displays Site Icon in RSS2.
 *
 * @since 4.3.0
 */
function rss2_site_icon() {
	$rss_title = get_wp_title_rss();
	if ( empty( $rss_title ) ) {
		$rss_title = get_bloginfo_rss( 'name' );
	}

	$url = get_site_icon_url( 32 );
	if ( $url ) {
		echo '
<image>
	<url>' . convert_chars( $url ) . '</url>
	<title>' . $rss_title . '</title>
	<link>' . get_bloginfo_rss( 'url' ) . '</link>
	<width>32</width>
	<height>32</height>
</image> ' . "\n";
	}
}

/**
 * Display the link for the currently displayed feed in a XSS safe way.
 *
 * Generate a correct link for the atom:self element.
 *
 * @since 2.5.0
 */
function self_link() {
	$host = @parse_url( home_url() );
	/**
	 * Filters the current feed URL.
	 *
	 * @since 3.6.0
	 *
	 * @see set_url_scheme()
	 * @see wp_unslash()
	 *
	 * @param string $feed_link The link for the feed with set URL scheme.
	 */
	echo esc_url( apply_filters( 'self_link', set_url_scheme( 'http://' . $host['host'] . wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) );
}

/*
 * Get the timestamp of the most recently modified post from WP_Query.
 *
 * If viewing a comment feed, the timestamp of the most recently modified
 * comment will be returned.
 *
 * @global WP_Query  $wp_query The global WP_Query object.
 *
 * @since 5.2.0
 *
 * @param string $format Format of the timestamp to return, passed to mysql2date.
 *
 * @return string The timestamp.
 */
function get_feed_build_date( $format ) {
	global $wp_query;

	if ( empty( $wp_query ) || ! $wp_query->have_posts() ) {
		// Fallback to last time any post was modified or published.
		return get_lastpostmodified( 'GMT' );
	}

	// Extract the post modified times from the posts.
	$modified_times = wp_list_pluck( $wp_query->posts, 'post_modified_gmt' );

	// If this is a comment feed, check those objects too.
	if ( $wp_query->is_comment_feed() && $wp_query->comment_count ) {
		// Extract the comment modified times from the comments.
		$comment_times = wp_list_pluck( $wp_query->comments, 'comment_date_gmt' );

		// Add the comment times to the post times for comparison.
		$modified_times = array_merge( $modified_times, $comment_times );
	}

	// Determine the maximum modified time.
	$max_modified_time = mysql2date( $format, max( $modified_times ), false );

	/**
	 * Filters the date the last post or comment in the query was modified.
	 *
	 * @since 5.2.0
	 *
	 * @param string $max_modified_time Date the last post or comment was modified in the query.
	 * @param string $format            The date format requested in get_feed_build_date.
	 */
	return apply_filters( 'get_feed_build_date', $max_modified_time, $format );
}

/**
 * Return the content type for specified feed type.
 *
 * @since 2.8.0
 *
 * @param string $type Type of feed. Possible values include rss2' and 'atom'.
 */
function feed_content_type( $type = 'feed' ) {
	if ( 'feed' === $type ) {
		$type = get_default_feed();
	}

	$content_type = ( 'rss2' === $type ) ? 'application/rss+xml' : 'application/octet-stream';

	/**
	 * Filters the content type for a specific feed type.
	 *
	 * @since 2.8.0
	 *
	 * @param string $content_type Content type indicating the type of data that a feed contains.
	 * @param string $type         Type of feed.
	 */
	return apply_filters( 'feed_content_type', $content_type, $type );
}

/**
 * Build SimplePie object based on RSS or Atom feed from URL.
 *
 * A stub for calling the fetch_feed function of the core read feeds plugin.
 * try to notify when the function is called but the plugin is not installed.
 *
 * @since 2.8.0
 * @since calmPress 1.0.0
 *
 * @param mixed $url URL of feed to retrieve. If an array of URLs, the feeds are merged
 *                   using SimplePie's multifeed feature.
 *                   See also {@link http://simplepie.org/wiki/faq/typical_multifeed_gotchas}.
 *
 * @return WP_Error|SimplePie WP_Error object on failure or SimplePie object on success
 */
function fetch_feed( $url ) {
	if ( ! function_exists( '\calmpress\readfeeds\fetch_feed' ) ) {
		return new WP_Error( 'core-plugin-required', __( 'You have tried to fetch a feed but do not have the read-feeds core plugin installed.' ) );
	}

	return \calmpress\readfeeds\fetch_feed( $url );
}
