<?php
/**
 * This file contains some WordPress and calmPress "constat" definitions related
 * to current versions and minimal required versions. It is used by both core
 * code itself and calmpress.org build process.
 */

/**
 * Holds the TinyMCE version
 *
 * @global string $tinymce_version
 */
$tinymce_version = '4920-20181217';

/**
 * Holds the required PHP version
 *
 * @global string $required_php_version
 */
$required_php_version = '7.0';

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
		return '1.0.0-alpha13';
	}
}

/*
 * WordPress has a bad habit of including this file multiple times therefor some
 * protection is needed around function definitions.
 */
if ( ! function_exists( 'calmpress_db_version_compatibility' ) ) {
	/**
	 * The version of the earliest calmPress version with compatible DB schema.
	 *
	 * @since calmPress 0.9.9
	 *
	 * @return string
	 */
	function calmpress_db_version_compatibility() {
		return '1.0.0-alpha9';
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
		return '5.1.1';
	}
}

// WordPress version as global for backward compatibility.
$wp_version = wordpress_core_version();
