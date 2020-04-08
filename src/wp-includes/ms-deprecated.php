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

/**
 * Store basic site info in the blogs table.
 *
 * This function creates a row in the wp_blogs table and returns
 * the new blog's ID. It is the first step in creating a new blog.
 *
 * @since MU (3.0.0)
 * @deprecated 5.1.0 Use `wp_insert_site()`
 * @see wp_insert_site()
 *
 * @param string $domain  The domain of the new site.
 * @param string $path    The path of the new site.
 * @param int    $site_id Unless you're running a multi-network install, be sure to set this value to 1.
 * @return int|false The ID of the new row
 */
function insert_blog($domain, $path, $site_id) {
	_deprecated_function( __FUNCTION__, '5.1.0', 'wp_insert_site()' );

	$data = array(
		'domain'  => $domain,
		'path'    => $path,
		'site_id' => $site_id,
	);

	$site_id = wp_insert_site( $data );
	if ( is_wp_error( $site_id ) ) {
		return false;
	}

	clean_blog_cache( $site_id );

	return $site_id;
}

/**
 * Install an empty blog.
 *
 * Creates the new blog tables and options. If calling this function
 * directly, be sure to use switch_to_blog() first, so that $wpdb
 * points to the new blog.
 *
 * @since MU (3.0.0)
 * @deprecated 5.1.0
 *
 * @global wpdb     $wpdb     WordPress database abstraction object.
 * @global WP_Roles $wp_roles WordPress role management object.
 *
 * @param int    $blog_id    The value returned by wp_insert_site().
 * @param string $blog_title The title of the new site.
 */
function install_blog( $blog_id, $blog_title = '' ) {
	global $wpdb, $wp_roles;

	_deprecated_function( __FUNCTION__, '5.1.0' );

	// Cast for security
	$blog_id = (int) $blog_id;

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$suppress = $wpdb->suppress_errors();
	if ( $wpdb->get_results( "DESCRIBE {$wpdb->posts}" ) ) {
		die( '<h1>' . __( 'Already Installed' ) . '</h1><p>' . __( 'You appear to have already installed WordPress. To reinstall please clear your old database tables first.' ) . '</p></body></html>' );
	}
	$wpdb->suppress_errors( $suppress );

	$url = get_blogaddress_by_id( $blog_id );

	// Set everything up
	make_db_current_silent( 'blog' );
	populate_options();
	populate_roles();

	// populate_roles() clears previous role definitions so we start over.
	$wp_roles = new WP_Roles();

	$siteurl = $home = untrailingslashit( $url );

	if ( ! is_subdomain_install() ) {

		if ( 'https' === parse_url( get_site_option( 'siteurl' ), PHP_URL_SCHEME ) ) {
			$siteurl = set_url_scheme( $siteurl, 'https' );
		}
		if ( 'https' === parse_url( get_home_url( get_network()->site_id ), PHP_URL_SCHEME ) ) {
			$home = set_url_scheme( $home, 'https' );
		}
	}

	update_option( 'siteurl', $siteurl );
	update_option( 'home', $home );

	if ( get_site_option( 'ms_files_rewriting' ) ) {
		update_option( 'upload_path', UPLOADBLOGSDIR . "/$blog_id/files" );
	} else {
		update_option( 'upload_path', get_blog_option( get_network()->site_id, 'upload_path' ) );
	}

	update_option( 'blogname', wp_unslash( $blog_title ) );
	update_option( 'admin_email', '' );

	// remove all perms
	$table_prefix = $wpdb->get_blog_prefix();
	delete_metadata( 'user', 0, $table_prefix . 'user_level', null, true ); // delete all
	delete_metadata( 'user', 0, $table_prefix . 'capabilities', null, true ); // delete all
}

/**
 * Set blog defaults.
 *
 * This function creates a row in the wp_blogs table.
 *
 * @since MU (3.0.0)
 * @deprecated MU
 * @deprecated Use wp_install_defaults()
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int $blog_id Ignored in this function.
 * @param int $user_id
 */
function install_blog_defaults( $blog_id, $user_id ) {
	global $wpdb;

	_deprecated_function( __FUNCTION__, 'MU' );

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

	$suppress = $wpdb->suppress_errors();

	wp_install_defaults( $user_id );

	$wpdb->suppress_errors( $suppress );
}

/**
 * Update the status of a user in the database.
 *
 * Previously used in core to mark a user as spam or "ham" (not spam) in Multisite.
 *
 * @since 3.0.0
 * @deprecated 5.3.0 Use wp_update_user()
 * @see wp_update_user()
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $id         The user ID.
 * @param string $pref       The column in the wp_users table to update the user's status
 *                           in (presumably user_status, spam, or deleted).
 * @param int    $value      The new status for the user.
 * @param null   $deprecated Deprecated as of 3.0.2 and should not be used.
 * @return int   The initially passed $value.
 */
function update_user_status( $id, $pref, $value, $deprecated = null ) {
	global $wpdb;

	_deprecated_function( __FUNCTION__, '5.3.0', 'wp_update_user()' );

	if ( null !== $deprecated ) {
		_deprecated_argument( __FUNCTION__, '3.0.2' );
	}

	$wpdb->update( $wpdb->users, array( sanitize_key( $pref ) => $value ), array( 'ID' => $id ) );

	$user = new WP_User( $id );
	clean_user_cache( $user );

	if ( $pref == 'spam' ) {
		if ( $value == 1 ) {
			/** This filter is documented in wp-includes/user.php */
			do_action( 'make_spam_user', $id );
		} else {
			/** This filter is documented in wp-includes/user.php */
			do_action( 'make_ham_user', $id );
		}
	}

	return $value;
}
