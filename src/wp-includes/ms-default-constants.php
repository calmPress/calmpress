<?php
/**
 * Defines constants and global variables that can be overridden, generally in wp-config.php.
 *
 * @package WordPress
 * @subpackage Multisite
 * @since 3.0.0
 */

/**
 * Defines Multisite upload constants.
 *
 * Exists for backward compatibility with legacy file-serving through
 * wp-includes/ms-files.php (wp-content/blogs.php in MU).
 *
 * @since 3.0.0
 */
function ms_upload_constants() {
	// This filter is attached in ms-default-filters.php but that file is not included during SHORTINIT.
	add_filter( 'default_site_option_ms_files_rewriting', '__return_true' );

	if ( ! get_site_option( 'ms_files_rewriting' ) ) {
		return;
	}

	// Base uploads dir relative to ABSPATH.
	if ( ! defined( 'UPLOADBLOGSDIR' ) ) {
		define( 'UPLOADBLOGSDIR', 'wp-content/blogs.dir' );
	}

	// Note, the main site in a post-MU network uses wp-content/uploads.
	// This is handled in wp_upload_dir() by ignoring UPLOADS for this case.
	if ( ! defined( 'UPLOADS' ) ) {
		$site_id = get_current_blog_id();

		define( 'UPLOADS', UPLOADBLOGSDIR . '/' . $site_id . '/files/' );

		// Uploads dir relative to ABSPATH.
		if ( 'wp-content/blogs.dir' === UPLOADBLOGSDIR && ! defined( 'BLOGUPLOADDIR' ) ) {
			define( 'BLOGUPLOADDIR', WP_CONTENT_DIR . '/blogs.dir/' . $site_id . '/files/' );
		}
	}
}

/**
 * Defines Multisite cookie constants.
 *
 * @since 3.0.0
 */
function ms_cookie_constants() {
	$current_network = get_network();

	/**
	 * @since 1.2.0
	 */
	if ( ! defined( 'COOKIEPATH' ) ) {
		define( 'COOKIEPATH', $current_network->path );
	}

	/**
	 * @since 1.5.0
	 */
	if ( ! defined( 'SITECOOKIEPATH' ) ) {
		define( 'SITECOOKIEPATH', $current_network->path );
	}

	/**
	 * @since 2.6.0
	 */
	if ( ! defined( 'ADMIN_COOKIE_PATH' ) ) {
		if ( ! is_subdomain_install() || trim( parse_url( get_option( 'siteurl' ), PHP_URL_PATH ), '/' ) ) {
			define( 'ADMIN_COOKIE_PATH', SITECOOKIEPATH );
		} else {
			define( 'ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin' );
		}
	}

	/**
	 * @since 2.0.0
	 */
	if ( ! defined( 'COOKIE_DOMAIN' ) && is_subdomain_install() ) {
		if ( ! empty( $current_network->cookie_domain ) ) {
			define( 'COOKIE_DOMAIN', '.' . $current_network->cookie_domain );
		} else {
			define( 'COOKIE_DOMAIN', '.' . $current_network->domain );
		}
	}
}

/**
 * Defines Multisite file constants.
 *
 * Exists for backward compatibility with legacy file-serving through
 * wp-includes/ms-files.php (wp-content/blogs.php in MU).
 *
 * @since 3.0.0
 */
function ms_file_constants() {
	/**
	 * Optional support for X-Sendfile header
	 *
	 * @since 3.0.0
	 */
	if ( ! defined( 'WPMU_SENDFILE' ) ) {
		define( 'WPMU_SENDFILE', false );
	}

	/**
	 * Optional support for X-Accel-Redirect header
	 *
	 * @since 3.0.0
	 */
	if ( ! defined( 'WPMU_ACCEL_REDIRECT' ) ) {
		define( 'WPMU_ACCEL_REDIRECT', false );
	}
}

/**
 * Defines Multisite subdomain constants.
 *
 * @since 3.0.0
 */
function ms_subdomain_constants() {

	if ( ! defined( 'SUBDOMAIN_INSTALL' ) ) {
		define( 'SUBDOMAIN_INSTALL', false );
	}
}
