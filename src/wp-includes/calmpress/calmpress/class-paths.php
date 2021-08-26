<?php
/**
 * Implementation of camlpress paths.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\calmpress;

/**
 * A mostly utility class to abstract access to various locations of calmPress files
 * and directories.
 *
 * @since 1.0.0
 */
class Paths {

	/**
	 * The path of root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function root_directory() : string {

		return ABSPATH;
	}

	/**
	 * The path of the admin directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function wp_admin_directory() : string {
		return ABSPATH . 'wp-admin/';
	}

	/**
	 * The path to the includes directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function wp_includes_directory() :string {
		return ABSPATH . 'wp-includes/';
	}

	/**
	 * The path to the content root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function wp_content_directory() : string {
		return WP_CONTENT_DIR . '/';
	}

	/**
	 * The path to the plugins root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function plugins_directory() : string {
		return WP_PLUGIN_DIR . '/';
	}	

	/**
	 * The path to the mu plugins root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function mu_plugins_directory() : string {
		return WPMU_PLUGIN_DIR . '/';
	}	

	/**
	 * The path to the themes root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function themes_directory() : string {
		return WP_CONTENT_DIR . '/themes/';
	}

	/**
	 * The path to the languages root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 */
	public function languages_directory() : string {
		return WP_LANG_DIR . '/';
	}

	/**
	 * An array containing the names of the core files located at the root directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] The file names.
	 */
	public function core_root_file_names() : array{
		return [
			'index.php',
			'wp-activate.php',
			'wp-blog-header.php',
			'wp-comments-post.php',
			'wp-cron.php',
			'wp-load.php',
			'wp-login.php',
			'wp-settings.php',
			'wp-signup.php',
		];
	}

	/**
	 * An array containing the names of the possible dropin files located at the root content directory.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] The file names.
	 */
	public function dropin_files_name() : array{
		return [
			'advanced-cache.php',
			'db.php',
			'db-error.php',
			'install.php',
			'maintenance.php',
			'object-cache.php',
			'php-error.php',
			'fatal-error-handler.php',
			'sunrise.php',
			'blog-deleted.php',
			'blog-inactive.php',
			'blog-suspended.php',	
		];
	}

	/**
	 * The path to the wp-config.php file.
	 *
	 * The file can be located either at the root of the install, or one directory higher.
	 *
	 * @since 1.0.0
	 *
	 * @return string The path.
	 *
	 * @throws RuntimeException if file was not found at both possible locations.
	 */
	public function wp_config_file() : string {
		if ( is_file( ABSPATH . 'wp-config.php' ) ) {
			return ABSPATH . 'wp-config.php';
		}
		if ( is_file( dirname( ABSPATH ) . '/wp-config.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}

		throw new RuntimeException( 'Can not find wp-config.php file');
	}
}
