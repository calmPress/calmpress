<?php
/**
 * Back compat functionality
 *
 * @package calmPress
 * @subpackage calm_Seventeen
 * @since calm Seventeen 1.0
 */

/**
 * Prevent switching to calm Seventeen on old versions of calmPress.
 *
 * Switches to the default theme.
 *
 * @since calm Seventeen 1.0
 */
function calmseventeen_switch_theme() {
	switch_theme( WP_DEFAULT_THEME );
	unset( $_GET['activated'] );
}
add_action( 'after_switch_theme', 'calmseventeen_switch_theme' );
