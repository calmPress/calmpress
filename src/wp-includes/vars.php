<?php
/**
 * Creates common globals for the rest of WordPress
 *
 * Sets $pagenow global which is the filename of the current screen.
 * Checks for the browser to set which one is currently being used.
 *
 * @package WordPress
 */

global $pagenow;

// On which page are we?
if ( is_admin() ) {
	// wp-admin pages are checked more carefully.
	if ( is_network_admin() ) {
		preg_match( '#/wp-admin/network/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
	} elseif ( is_user_admin() ) {
		preg_match( '#/wp-admin/user/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
	} else {
		preg_match( '#/wp-admin/?(.*?)$#i', $_SERVER['PHP_SELF'], $self_matches );
	}

	$pagenow = ! empty( $self_matches[1] ) ? $self_matches[1] : '';
	$pagenow = trim( $pagenow, '/' );
	$pagenow = preg_replace( '#\?.*?$#', '', $pagenow );

	if ( '' === $pagenow || 'index' === $pagenow || 'index.php' === $pagenow ) {
		$pagenow = 'index.php';
	} else {
		preg_match( '#(.*?)(/|$)#', $pagenow, $self_matches );
		$pagenow = strtolower( $self_matches[1] );
		if ( ! str_ends_with( $pagenow, '.php' ) ) {
			$pagenow .= '.php'; // For `Options +Multiviews`: /wp-admin/themes/index.php (themes.php is queried).
		}
	}
} else {
	if ( preg_match( '#([^/]+\.php)([?/].*?)?$#i', $_SERVER['PHP_SELF'], $self_matches ) ) {
		$pagenow = strtolower( $self_matches[1] );
	} else {
		$pagenow = 'index.php';
	}
}
unset( $self_matches );

/**
 * Test if the current browser runs on a mobile device (smart phone, tablet, etc.).
 *
 * @since 3.4.0
 * @since 6.4.0 Added checking for the Sec-CH-UA-Mobile request header.
 *
 * @return bool
 */
function wp_is_mobile() {
	if ( isset( $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ) ) {
		// This is the `Sec-CH-UA-Mobile` user agent client hint HTTP request header.
		// See <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Sec-CH-UA-Mobile>.
		$is_mobile = ( '?1' === $_SERVER['HTTP_SEC_CH_UA_MOBILE'] );
	} elseif ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$is_mobile = false;
	} elseif ( str_contains( $_SERVER['HTTP_USER_AGENT'], 'Mobile' ) // Many mobile devices (all iPhone, iPad, etc.)
		|| str_contains( $_SERVER['HTTP_USER_AGENT'], 'Android' )
		|| str_contains( $_SERVER['HTTP_USER_AGENT'], 'Silk/' )
		|| str_contains( $_SERVER['HTTP_USER_AGENT'], 'Kindle' )
		|| str_contains( $_SERVER['HTTP_USER_AGENT'], 'BlackBerry' )
		|| str_contains( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' )
		|| str_contains( $_SERVER['HTTP_USER_AGENT'], 'Opera Mobi' ) ) {
			$is_mobile = true;
	} else {
		$is_mobile = false;
	}

	/**
	 * Filters whether the request should be treated as coming from a mobile device or not.
	 *
	 * @since 4.9.0
	 *
	 * @param bool $is_mobile Whether the request is from a mobile device or not.
	 */
	return apply_filters( 'wp_is_mobile', $is_mobile );
}
