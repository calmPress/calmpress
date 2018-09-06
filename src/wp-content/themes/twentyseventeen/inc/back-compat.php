<?php
/**
 * Twenty Seventeen back compat functionality
 *
 * Prevents Twenty Seventeen from running on WordPress versions prior to 4.7,
 * since this theme is not meant to be backward compatible beyond that and
 * relies on many newer functions and markup changes introduced in 4.7.
 *
 * @package WordPress
 * @subpackage Twenty_Seventeen
 * @since Twenty Seventeen 1.0
 */

/**
 * Prevent switching to Twenty Seventeen on old versions of WordPress.
 *
 * Switches to the default theme.
 *
 * @since Twenty Seventeen 1.0
 */
function twentyseventeen_switch_theme() {
	switch_theme( WP_DEFAULT_THEME );
	unset( $_GET['activated'] );
}
add_action( 'after_switch_theme', 'twentyseventeen_switch_theme' );
