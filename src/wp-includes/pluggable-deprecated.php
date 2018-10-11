<?php
/**
 * Deprecated pluggable functions from past WordPress versions. You shouldn't use these
 * functions and look for the alternatives instead. The functions will be removed in a
 * later version.
 *
 * Deprecated warnings are also thrown if one of these functions is being defined by a plugin.
 *
 * @package WordPress
 * @subpackage Deprecated
 * @see pluggable.php
 */

/*
 * Deprecated functions come here to die.
 */

if ( !function_exists('get_currentuserinfo') ) :
/**
 * Populate global variables with information about the currently logged in user.
 *
 * @since 0.71
 * @deprecated 4.5.0 Use wp_get_current_user()
 * @see wp_get_current_user()
 *
 * @return bool|WP_User False on XMLRPC Request and invalid auth cookie, WP_User instance otherwise.
 */
function get_currentuserinfo() {
	_deprecated_function( __FUNCTION__, '4.5.0', 'wp_get_current_user()' );

	return _wp_get_current_user();
}
endif;
