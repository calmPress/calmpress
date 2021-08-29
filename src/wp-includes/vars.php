<?php
/**
 * Creates common globals for the rest of WordPress
 *
 * Sets $pagenow global which is the current page. Checks
 * for the browser to set which one is currently being used.
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
	$pagenow = $self_matches[1];
	$pagenow = trim( $pagenow, '/' );
	$pagenow = preg_replace( '#\?.*?$#', '', $pagenow );
	if ( '' === $pagenow || 'index' === $pagenow || 'index.php' === $pagenow ) {
		$pagenow = 'index.php';
	} else {
		preg_match( '#(.*?)(/|$)#', $pagenow, $self_matches );
		$pagenow = strtolower( $self_matches[1] );
		if ( '.php' !== substr( $pagenow, -4, 4 ) ) {
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
 * Test if the current browser runs on a mobile device (smart phone, tablet, etc.)
 *
 * @since 3.4.0
 *
 * @return bool
 */
function wp_is_mobile() {
	if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$is_mobile = false;
	} elseif ( strpos( $_SERVER['HTTP_USER_AGENT'], 'Mobile' ) !== false // Many mobile devices (all iPhone, iPad, etc.)
		|| strpos( $_SERVER['HTTP_USER_AGENT'], 'Android' ) !== false
		|| strpos( $_SERVER['HTTP_USER_AGENT'], 'Silk/' ) !== false
		|| strpos( $_SERVER['HTTP_USER_AGENT'], 'Kindle' ) !== false
		|| strpos( $_SERVER['HTTP_USER_AGENT'], 'BlackBerry' ) !== false
		|| strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mini' ) !== false
		|| strpos( $_SERVER['HTTP_USER_AGENT'], 'Opera Mobi' ) !== false ) {
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
