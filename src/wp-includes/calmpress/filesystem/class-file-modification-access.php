<?php
/**
 * Utilities for checking whether PHP can modify files and directories.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\filesystem;

/**
 * Utilities for checking whether PHP can modify files and directories.
 *
 * Accounts for paths whose permissions PHP can change because it owns them.
 *
 * @since 1.0.0
 */
class File_Modification_Access {

	/**
	 * Check whether PHP can modify a file or directory.
	 *
	 * A Unix process can change permissions on a path it owns, even when that
	 * path is not currently writable.
	 *
	 * On Windows, wp_is_writable() tests whether PHP can open the file for writing
	 * or create a file in the directory.
	 *
	 * Ownership is not checked there because Windows access is governed by ACLs,
	 * which PHP cannot reliably check here without attempting to write to the file
	 * or directory.
	 *
	 * This is a best-effort check: false means no ability to modify the path was
	 * detected, not that every possible way to modify it has been ruled out.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path Path to check.
	 *
	 * @return bool Whether PHP can write to the path or owns it.
	 */
	public static function can_modify_path( string $path ): bool {
		if ( wp_is_writable( $path ) ) {
			return true;
		}

		// The write test failed. On Windows, ownership cannot tell us whether PHP
		// could change the path's ACL to make it writable.
		if ( 'Windows' === PHP_OS_FAMILY ) {
			return false;
		}

		// Cache the process user ID, including a failed ownership probe.
		static $process_uid = null;

		if ( false === $process_uid ) {
			return false;
		}

		if ( null === $process_uid ) {
			// Create a file so its ownership information reveals PHP's process user ID.
			$process_uid = false;
			$temp_file = @tempnam( get_temp_dir(), 'calmpress-owner-' );
			if ( false === $temp_file ) {
				return false;
			}

			$stat = @stat( $temp_file );
			@unlink( $temp_file );
			if ( false === $stat ) {
				return false;
			}

			$process_uid = $stat['uid'];
		}

		$owner_uid = @fileowner( $path );

		// The path may have disappeared since it was listed.
		if ( false === $owner_uid ) {
			return false;
		}

		return $owner_uid === $process_uid;
	}

	/**
	 * Whether a file should be checked for modification access.
	 *
	 * @since 1.0.0
	 *
	 * @param \SplFileInfo $file File to check.
	 *
	 * @return bool Whether the file is PHP, JavaScript, or .htaccess.
	 */
	private static function is_checked_file( \SplFileInfo $file ): bool {
		if ( ! $file->isFile() ) {
			return false;
		}

		return in_array( strtolower( $file->getExtension() ), [ 'php', 'js' ], true )
			|| '.htaccess' === $file->getFilename();
	}

	/**
	 * Check whether PHP can modify installed application code.
	 *
	 * Checks code directories and PHP, JavaScript, and .htaccess files in the
	 * installation root, core, plugins, mu-plugins, and themes.
	 *
	 * Those file types directly under wp-content also count, as do writable
	 * parent directories.
	 *
	 * Symbolic links are checked but their targets are not traversed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $root_directory Absolute path to the installation root.
	 *
	 * @return bool Whether an installed code path is writable or owned by PHP.
	 *
	 * @throws \UnexpectedValueException If a code directory cannot be read.
	 */
	public static function can_modify_installed_code( string $root_directory ): bool {
		$root_directory = rtrim( $root_directory, '/\\' );

		// A writable parent allows the entire installation directory to be replaced.
		if ( self::can_modify_path( dirname( $root_directory ) ) ) {
			return true;
		}

		if ( self::can_modify_path( $root_directory ) ) {
			return true;
		}

		// Check files directly in the installation root.
		foreach ( new \DirectoryIterator( $root_directory ) as $entry ) {
			if ( self::is_checked_file( $entry ) && self::can_modify_path( $entry->getPathname() ) ) {
				return true;
			}
		}

		// Check wp-content and files directly inside it.
		if ( is_dir( WP_CONTENT_DIR ) ) {
			if ( self::can_modify_path( WP_CONTENT_DIR ) ) {
				return true;
			}

			foreach ( new \DirectoryIterator( WP_CONTENT_DIR ) as $entry ) {
				if ( self::is_checked_file( $entry ) && self::can_modify_path( $entry->getPathname() ) ) {
					return true;
				}
			}
		}

		$code_directories = [
			$root_directory . '/wp-admin',
			$root_directory . '/wp-includes',
		];

		// Check whether PHP can replace an existing plugin or theme directory through
		// its parent, then add the directory to the recursive scan.
		foreach ( [ WP_PLUGIN_DIR, WPMU_PLUGIN_DIR, WP_CONTENT_DIR . '/themes' ] as $path ) {
			// Only scan directories that exist; mu-plugins may be absent.
			if ( ! is_dir( $path ) ) {
				continue;
			}

			if ( self::can_modify_path( dirname( $path ) ) ) {
				return true;
			}

			$code_directories[] = $path;
		}

		foreach ( array_unique( $code_directories ) as $path ) {
			if ( self::can_modify_path( $path ) ) {
				return true;
			}

			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $path, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ( $items as $item ) {
				if ( ( $item->isDir() || self::is_checked_file( $item ) ) && self::can_modify_path( $item->getPathname() ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
