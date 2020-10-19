<?php
/**
 * Deprecated functions from past WordPress versions. You shouldn't use these
 * functions and look for the alternatives instead. The functions will be
 * removed in a later version.
 *
 * @package WordPress
 * @subpackage Deprecated
 */

/*
 * Deprecated functions come here to die.
 */

/**
 * Retrieve path of comment popup template in current or parent template.
 *
 * @since 1.5.0
 * @deprecated 4.5.0
 *
 * @return string Full path to comments popup template file.
 */
function get_comments_popup_template() {
	_deprecated_function( __FUNCTION__, '4.5.0' );

	return '';
}

/**
 * Determines whether the current URL is within the comments popup window.
 *
 * For more information on this and similar theme functions, check out
 * the {@link https://developer.wordpress.org/themes/basics/conditional-tags/
 * Conditional Tags} article in the Theme Developer Handbook.
 *
 * @since 1.5.0
 * @deprecated 4.5.0
 *
 * @return false Always returns false.
 */
function is_comments_popup() {
	_deprecated_function( __FUNCTION__, '4.5.0' );

	return false;
}

/**
 * Display the JS popup script to show a comment.
 *
 * @since 0.71
 * @deprecated 4.5.0
 */
function comments_popup_script() {
	_deprecated_function( __FUNCTION__, '4.5.0' );
}

/**
 * Adds element attributes to open links in new tabs.
 *
 * @since 0.71
 * @deprecated 4.5.0
 *
 * @param string $text Content to replace links to open in a new tab.
 * @return string Content that has filtered links.
 */
function popuplinks( $text ) {
	_deprecated_function( __FUNCTION__, '4.5.0' );
	$text = preg_replace('/<a (.+?)>/i', "<a $1 target='_blank' rel='external'>", $text);
	return $text;
}

/**
 * The Google Video embed handler callback.
 *
 * Deprecated function that previously assisted in turning Google Video URLs
 * into embeds but that service has since been shut down.
 *
 * @since 2.9.0
 * @deprecated 4.6.0
 *
 * @return string An empty string.
 */
function wp_embed_handler_googlevideo( $matches, $attr, $url, $rawattr ) {
	_deprecated_function( __FUNCTION__, '4.6.0' );

	return '';
}

/**
 * Retrieve path of paged template in current or parent template.
 *
 * @since 1.5.0
 * @deprecated 4.7.0 The paged.php template is no longer part of the theme template hierarchy.
 *
 * @return string Full path to paged template file.
 */
function get_paged_template() {
	_deprecated_function( __FUNCTION__, '4.7.0' );

	return get_query_template( 'paged' );
}

/**
 * Removes the HTML JavaScript entities found in early versions of Netscape 4.
 *
 * Previously, this function was pulled in from the original
 * import of kses and removed a specific vulnerability only
 * existent in early version of Netscape 4. However, this
 * vulnerability never affected any other browsers and can
 * be considered safe for the modern web.
 *
 * The regular expression which sanitized this vulnerability
 * has been removed in consideration of the performance and
 * energy demands it placed, now merely passing through its
 * input to the return.
 *
 * @since 1.0.0
 * @deprecated 4.7.0 Officially dropped security support for Netscape 4.
 *
 * @param string $string
 * @return string
 */
function wp_kses_js_entities( $string ) {
	_deprecated_function( __FUNCTION__, '4.7.0' );

	return preg_replace( '%&\s*\{[^}]*(\}\s*;?|$)%', '', $string );
}

/**
 * Sort categories by ID.
 *
 * Used by usort() as a callback, should not be used directly. Can actually be
 * used to sort any term object.
 *
 * @since 2.3.0
 * @deprecated 4.7.0 Use wp_list_sort()
 * @access private
 *
 * @param object $a
 * @param object $b
 * @return int
 */
function _usort_terms_by_ID( $a, $b ) {
	_deprecated_function( __FUNCTION__, '4.7.0', 'wp_list_sort()' );

	if ( $a->term_id > $b->term_id )
		return 1;
	elseif ( $a->term_id < $b->term_id )
		return -1;
	else
		return 0;
}

/**
 * Sort categories by name.
 *
 * Used by usort() as a callback, should not be used directly. Can actually be
 * used to sort any term object.
 *
 * @since 2.3.0
 * @deprecated 4.7.0 Use wp_list_sort()
 * @access private
 *
 * @param object $a
 * @param object $b
 * @return int
 */
function _usort_terms_by_name( $a, $b ) {
	_deprecated_function( __FUNCTION__, '4.7.0', 'wp_list_sort()' );

	return strcmp( $a->name, $b->name );
}

/**
 * Sort menu items by the desired key.
 *
 * @since 3.0.0
 * @deprecated 4.7.0 Use wp_list_sort()
 * @access private
 *
 * @global string $_menu_item_sort_prop
 *
 * @param object $a The first object to compare
 * @param object $b The second object to compare
 * @return int -1, 0, or 1 if $a is considered to be respectively less than, equal to, or greater than $b.
 */
function _sort_nav_menu_items( $a, $b ) {
	global $_menu_item_sort_prop;

	_deprecated_function( __FUNCTION__, '4.7.0', 'wp_list_sort()' );

	if ( empty( $_menu_item_sort_prop ) )
		return 0;

	if ( ! isset( $a->$_menu_item_sort_prop ) || ! isset( $b->$_menu_item_sort_prop ) )
		return 0;

	$_a = (int) $a->$_menu_item_sort_prop;
	$_b = (int) $b->$_menu_item_sort_prop;

	if ( $a->$_menu_item_sort_prop == $b->$_menu_item_sort_prop )
		return 0;
	elseif ( $_a == $a->$_menu_item_sort_prop && $_b == $b->$_menu_item_sort_prop )
		return $_a < $_b ? -1 : 1;
	else
		return strcmp( $a->$_menu_item_sort_prop, $b->$_menu_item_sort_prop );
}

/**
 * Retrieves the Press This bookmarklet link.
 *
 * @since 2.6.0
 * @deprecated 4.9.0
 *
 */
function get_shortcut_link() {
	_deprecated_function( __FUNCTION__, '4.9.0' );

	$link = '';

	/**
	 * Filters the Press This bookmarklet link.
	 *
	 * @since 2.6.0
	 * @deprecated 4.9.0
	 *
	 * @param string $link The Press This bookmarklet link.
	 */
	return apply_filters( 'shortcut_link', $link );
}
