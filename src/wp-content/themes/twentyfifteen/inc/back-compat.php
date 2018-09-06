<?php
/**
 * Twenty Fifteen back compat functionality
 *
 * Prevents Twenty Fifteen from running on WordPress versions prior to 4.1,
 * since this theme is not meant to be backward compatible beyond that and
 * relies on many newer functions and markup changes introduced in 4.1.
 *
 * @package WordPress
 * @subpackage Twenty_Fifteen
 * @since Twenty Fifteen 1.0
 */

/**
 * Prevent switching to Twenty Fifteen on old versions of WordPress.
 *
 * Switches to the default theme.
 *
 * @since Twenty Fifteen 1.0
 */
function twentyfifteen_switch_theme() {
	switch_theme( WP_DEFAULT_THEME, WP_DEFAULT_THEME );
	unset( $_GET['activated'] );
}
add_action( 'after_switch_theme', 'twentyfifteen_switch_theme' );
