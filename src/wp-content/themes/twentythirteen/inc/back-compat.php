<?php
/**
 * Twenty Thirteen back compat functionality
 *
 * Prevents Twenty Thirteen from running on WordPress versions prior to 3.6,
 * since this theme is not meant to be backward compatible and relies on
 * many new functions and markup changes introduced in 3.6.
 *
 * @package WordPress
 * @subpackage Twenty_Thirteen
 * @since Twenty Thirteen 1.0
 */

/**
 * Prevent switching to Twenty Thirteen on old versions of WordPress.
 *
 * Switches to the default theme.
 *
 * @since Twenty Thirteen 1.0
 */
function twentythirteen_switch_theme() {
	switch_theme( WP_DEFAULT_THEME, WP_DEFAULT_THEME );
	unset( $_GET['activated'] );
}
add_action( 'after_switch_theme', 'twentythirteen_switch_theme' );
