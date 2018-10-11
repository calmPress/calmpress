<?php
/**
 * Deprecated functions from WordPress MU and the multisite feature. You shouldn't
 * use these functions and look for the alternatives instead. The functions will be
 * removed in a later version.
 *
 * @package WordPress
 * @subpackage Deprecated
 * @since 3.0.0
 */

/*
 * Deprecated functions come here to die.
 */

/**
 * Return an array of sites for a network or networks.
 *
 * @since 3.7.0
 * @deprecated 4.6.0 Use get_sites()
 * @see get_sites()
 *
 * @param array $args {
 *     Array of default arguments. Optional.
 *
 *     @type int|array $network_id A network ID or array of network IDs. Set to null to retrieve sites
 *                                 from all networks. Defaults to current network ID.
 *     @type int       $public     Retrieve public or non-public sites. Default null, for any.
 *     @type int       $archived   Retrieve archived or non-archived sites. Default null, for any.
 *     @type int       $mature     Retrieve mature or non-mature sites. Default null, for any.
 *     @type int       $spam       Retrieve spam or non-spam sites. Default null, for any.
 *     @type int       $deleted    Retrieve deleted or non-deleted sites. Default null, for any.
 *     @type int       $limit      Number of sites to limit the query to. Default 100.
 *     @type int       $offset     Exclude the first x sites. Used in combination with the $limit parameter. Default 0.
 * }
 * @return array An empty array if the installation is considered "large" via wp_is_large_network(). Otherwise,
 *               an associative array of site data arrays, each containing the site (network) ID, blog ID,
 *               site domain and path, dates registered and modified, and the language ID. Also, boolean
 *               values for whether the site is public, archived, mature, spam, and/or deleted.
 */
function wp_get_sites( $args = array() ) {
	_deprecated_function( __FUNCTION__, '4.6.0', 'get_sites()' );

	if ( wp_is_large_network() )
		return array();

	$defaults = array(
		'network_id' => get_current_network_id(),
		'public'     => null,
		'archived'   => null,
		'mature'     => null,
		'spam'       => null,
		'deleted'    => null,
		'limit'      => 100,
		'offset'     => 0,
	);

	$args = wp_parse_args( $args, $defaults );

	// Backwards compatibility
	if( is_array( $args['network_id'] ) ){
		$args['network__in'] = $args['network_id'];
		$args['network_id'] = null;
	}

	if( is_numeric( $args['limit'] ) ){
		$args['number'] = $args['limit'];
		$args['limit'] = null;
	} elseif ( ! $args['limit'] ) {
		$args['number'] = 0;
		$args['limit'] = null;
	}

	// Make sure count is disabled.
	$args['count'] = false;

	$_sites  = get_sites( $args );

	$results = array();

	foreach ( $_sites as $_site ) {
		$_site = get_site( $_site );
		$results[] = $_site->to_array();
	}

	return $results;
}

/**
 * Check whether a usermeta key has to do with the current blog.
 *
 * @since MU (3.0.0)
 * @deprecated 4.9.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param string $key
 * @param int    $user_id Optional. Defaults to current user.
 * @param int    $blog_id Optional. Defaults to current blog.
 * @return bool
 */
function is_user_option_local( $key, $user_id = 0, $blog_id = 0 ) {
	global $wpdb;

	_deprecated_function( __FUNCTION__, '4.9.0' );

	$current_user = wp_get_current_user();
	if ( $blog_id == 0 ) {
		$blog_id = get_current_blog_id();
	}
	$local_key = $wpdb->get_blog_prefix( $blog_id ) . $key;

	return isset( $current_user->$local_key );
}
