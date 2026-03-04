<?php
/**
 * This file contains some WordPress and calmPress "constant" definitions related
 * to current versions and minimal required versions. It is used by both core
 * code itself and calmpress.org build process.
 */

/**
 * Holds the TinyMCE version
 *
 * @var string $tinymce_version
 */
$tinymce_version = '49110-20250317';

/**
 * Holds the minimum required PHP version.
 *
 * @var string $required_php_version
 */
$required_php_version = '8.3';

/**
 * Holds the max minor supported PHP version including its pathches.
 *
 * @var string $upto_php_version
 */
$upto_php_version = '8.5';

/**
 * Holds the minimum required MySQL version.
 *
 * @var string $required_mysql_version
 */
$required_mysql_version = '8.0';

/**
 * Holds the max minor supported MySQL version including its pathches.
 *
 * @var string $upto_mysql_version
 */
$upto_mysql_version = '9.3';

/**
 * Holds the required MariaDB version.
 *
 * @var string $required_mariadb_version
 */
$required_mariadb_version = '10.6';

/**
 * Holds the max minor supported MariaDB version including its pathches.
 *
 * @var string $upto_mariadb_version
 */
$upto_mariadb_version = '12.3';

/**
 * Holds the required PHP extensions.
 *
 * @var string[] $required_php_extensions
 */
$required_php_extensions = [
	'json',
	'hash',
	'exif',
	'mysqli',
	'openssl',
	'gd',
	'fileinfo',
	'mbstring',
	'dom',
	'zip',
	'curl'
];

/**
 * Holds the alternative PHP extensions.
 *
 * @var string[] $alternative_php_extensions
 */
$alternative_php_extensions = [ 'gd' => ['gd2', 'imagick'] ];

/**
 * Holds the required Apache modules.
 *
 * @var string[] $required_apache_modules
 */
$required_apache_modules = [
	'mod_rewrite',
	'mod_filter',
	'mod_deflate',
	'mod_expires'
];

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
		return '1.0.0-alpha26';
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
		return '6.9.2';
	}
}

// WordPress version as global for backward compatibility.
$wp_version = '6.9.2';
