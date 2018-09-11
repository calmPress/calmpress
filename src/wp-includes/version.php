<?php

/**
 * Holds the TinyMCE version
 *
 * @global string $tinymce_version
 */
$tinymce_version = '4800-20180716';

/**
 * Holds the required PHP version
 *
 * @global string $required_php_version
 */
$required_php_version = '5.2.4';

/**
 * Holds the required MySQL version
 *
 * @global string $required_mysql_version
 */
$required_mysql_version = '5.0';

/*
 * WordPress has a bad habit of including this file multiple times therefor some
 * protection is needed around function definitions.
 */
if ( ! function_exists( 'calmpress_version' ) ) {
	/**
	 * The version of the current calmPress code.
	 *
	 * @since calmPress 0.9.9
	 *
	 * @return string The version string.
	 */
	function calmpress_version() {
		return '0.9.9-dev';
	}
}

/*
 * WordPress has a bad habit of including this file multiple times therefor some
 * protection is needed around function definitions.
 */
if ( ! function_exists( 'wordpress_core_version' ) ) {
	/**
	 * The version of the current WordPress code.
	 *
	 * @since calmPress 0.9.9
	 *
	 * @return string The version string.
	 */
	function wordpress_core_version() {
		return '4.9.8';
	}
}
