<?php
/**
 * Implementation of a backup/restore engine for core data and files.
 *
 * @package calmPress
 * @since 1.0.0
 */

declare(strict_types=1);

namespace calmpress\backup;

// This is need for accessing get_plugins API.
require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * A backup/restore engine for the core parts.
 *
 * @since 1.0.0
 */
class Core_Backup_Engine implements Engine_Specific_Backup {

	/**
	 * Generate a backup version from a file's modification time.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Path to the theme stylesheet or plugin header file.
	 *
	 * @return string Timestamp-based version for software without a version header.
	 *
	 * @throws \RuntimeException If the modification time cannot be read.
	 */
	protected static function version_from_file_timestamp( string $file ): string {
		$modified = filemtime( $file );
		if ( false === $modified ) {
			throw new \RuntimeException( 'Failed reading file modification time for backup: ' . $file );
		}
		return 'unversioned-' . $modified;
	}

	/**
	 * Generate a backup version from the latest modification time in a directory tree.
	 *
	 * Symbolic links are ignored because they are not included in directory backups.
	 *
	 * @since 1.0.0
	 *
	 * @param string $directory Directory whose contents are being backed up.
	 *
	 * @return string Timestamp-based version for the directory contents.
	 *
	 * @throws \RuntimeException If a modification time cannot be read.
	 */
	protected static function version_from_directory_timestamp( string $directory ): string {
		$latest_modified = filemtime( $directory );
		if ( false === $latest_modified ) {
			throw new \RuntimeException( 'Failed reading directory modification time for backup: ' . $directory );
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				continue;
			}
			$latest_modified = max( $latest_modified, $item->getMTime() );
		}

		return 'unversioned-' . $latest_modified;
	}

	/**
	 * Throw a timeout exception if the current time is later than the parameter.
	 *
	 * @param int $time_to_check The unix time to compare against.
	 *
	 * @since 1.0.0
	 *
	 * @throws \calmpress\calmpress\Timeout_Exception if current time is later than $time_to_check.
	 */
	static function throw_if_out_of_time( int $time_to_check ) {
		if ( time() > $time_to_check ) {
			throw new \calmpress\calmpress\Timeout_Exception();
		}
	}

	/**
	 * Backup recursively a directory from source directory into destination staging storage.
	 * 
	 * Symlinks are not copied as they might point anywhere and there is no clear backup and restore
	 * strategy to handle all cases.
	 * 
	 * @since 1.0.0
	 * 
	 * @param string                   $source      Full path of the source directory, the directory should exist.
	 * @param Temporary_Backup_Section $staging     The temporary section for the files.
	 * @param string                   $destination A path relative to the staging root in which files will be stored.
	 * 
	 * @throws \Exception When directory could not be created or file could not be copied.
	 */
	protected static function Backup_Directory( string $source, Temporary_Backup_Section $staging, string $destination ) {

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $source, \RecursiveDirectoryIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			if ( $item->isDir() ) {
				// skip as storage is responsible to create directories as needed.
				;
			} elseif ( $item->isLink() ) {
				// Skip, not handling symlinks.
				;
			} else {
				$file = $destination . '/' . $iterator->getSubPathName();
				$staging->copy_file( $item->getPathname(), $file );
			}
		}
	}

	/**
	 * Backup the core files in wp-admin, wp-includes, and the root directory.
	 *
	 * If a backup for the current version already exists, do nothing. Otherwise create a new section
	 * and copy into it all files from wp-includes, wp-admin, and the root directory, while preserving
	 * their relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage          $storage  The storage to backup to.
	 * @param Backup_Section_Identity $identity The identity of the core section.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Core( Backup_Storage $storage, Backup_Section_Identity $identity ): void {
		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the core files.
		 */
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, '' );

				// Copy wp-includes
				static::Backup_Directory( static::installation_paths()->wp_includes_directory(), $staging, 'wp-includes' );

				// Copy wp-admin.
				static::Backup_Directory( static::installation_paths()->wp_admin_directory(), $staging, 'wp-admin' );

				// Copy core code files located at root directory.
				foreach ( static::installation_paths()->core_root_file_names() as $file ) {
					$staging->copy_file( static::installation_paths()->root_directory() . $file, $file );
				}

				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

	}

	/**
	 * Backup the mu-plugins directory.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage          $storage  The storage to use for the MU-plugin files.
	 * @param string                  $source   Full path to the existing MU-plugins directory.
	 * @param Backup_Section_Identity $identity Identity of the MU-plugins section.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_MU_Plugins( Backup_Storage $storage, string $source, Backup_Section_Identity $identity ): void {
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, '' );
				static::Backup_Directory( $source, $staging, '' );
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

	}

	/**
	 * Backup the languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The backup storage.
	 * @param string         $source  Full path to the existing languages directory.
	 *
	 * @return Backup_Section_Identity The identity of the languages section.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Languages( Backup_Storage $storage, string $source ): Backup_Section_Identity {
		$version  = static::version_from_directory_timestamp( $source );
		$identity = new Backup_Section_Identity( 'languages', 'languages', $version );
		if ( null !== $storage->section( $identity ) ) {
			return $identity;
		}

		try {
			$staging = $storage->create_section( $identity, '' );

			static::Backup_Directory( $source, $staging, '' );
			$staging->commit();
		} catch ( Backup_Section_Already_Exists_Exception ) {
			// Another backup committed this version after the existence check.
		}

		return $identity;
	}

	/**
	 * Back up each drop-in file in the content directory as a separate section.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The backup storage.
	 * @param string         $source_dir The directory containing the drop-in files.
	 *
	 * @return Backup_Section_Identity[] The identities of the backed up drop-ins.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Dropins( Backup_Storage $storage, string $source_dir ): array {
		$sections = array();
		foreach ( static::installation_paths()->dropin_files_name() as $filename ) {
			$file = $source_dir . $filename;
			if ( ! is_file( $file ) || is_link( $file ) ) {
				continue;
			}

			$identity = new Backup_Section_Identity( 'dropin', $filename, static::version_from_file_timestamp( $file ) );
			if ( null === $storage->section( $identity ) ) {
				try {
					$staging = $storage->create_section( $identity, '' );
					$staging->copy_file( $file, $filename );
					$staging->commit();
				} catch ( Backup_Section_Already_Exists_Exception ) {
					// Another backup committed this version after the existence check.
				}
			}
			$sections[] = $identity;
		}

		return $sections;
	}

	/**
	 * Back up files in the installation root directory that are not part of core code.
	 *
	 * Core root files and wp-config.php have their own sections; this section contains the remaining
	 * regular root-level files, which may also be needed for site operation.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage       $storage The backup storage.
	 * @param array<string, string> $files  Root filenames mapped to their source paths.
	 *
	 * @return Backup_Section_Identity The section identity.
	 *
	 * @throws \RuntimeException If a file modification time cannot be read.
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root( Backup_Storage $storage, array $files ): Backup_Section_Identity {
		// Include filenames so adding or removing a file changes the version.
		ksort( $files, SORT_STRING );
		$version_hash = hash_init( 'sha256' );
		foreach ( $files as $filename => $source ) {
			$modified = filemtime( $source );
			if ( false === $modified ) {
				throw new \RuntimeException( 'Failed reading file modification time for backup: ' . $source );
			}
			hash_update( $version_hash, $filename . "\0" . $modified . "\0" );
		}

		$identity = new Backup_Section_Identity( 'root-files', 'root', 'filenames-timestamps-' . hash_final( $version_hash ) );
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, implode( ', ', array_keys( $files ) ) );
				foreach ( $files as $filename => $source ) {
					$staging->copy_file( $source, $filename );
				}
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

		return $identity;
	}

	/**
	 * Find root-level files that are not part of core code or the configuration section.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source_dir The installation root directory.
	 *
	 * @return array<string, string> Root filenames mapped to their source paths.
	 */
	protected static function root_files( string $source_dir ): array {
		$files      = array();
		$core_files = static::installation_paths()->core_root_file_names();

		foreach ( new \DirectoryIterator( $source_dir ) as $file ) {
			if ( $file->isDot() || $file->isLink() || ! $file->isFile() ) {
				continue;
			}

			// No need to backup core files.
			if ( 'wp-config.php' === $file->getFilename() || in_array( $file->getFilename(), $core_files, true ) ) {
				continue;
			}
			$files[ $file->getFilename() ] = $file->getPathname();
		}

		ksort( $files, SORT_STRING );
		return $files;
	}

	/**
	 * Back up wp-config.php and record its location relative to the installation root.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The backup storage.
	 * @param string         $source_dir The installation root directory.
	 *
	 * @return Backup_Section_Identity The configuration file section identity.
	 *
	 * @throws \RuntimeException If the configuration file cannot be found as a regular file.
	 * @throws \Exception When copying or committing the file fails.
	 */
	protected static function Backup_Config_File( Backup_Storage $storage, string $source_dir ): Backup_Section_Identity {
		$root_config   = trailingslashit( $source_dir ) . 'wp-config.php';
		$parent_config = dirname( rtrim( $source_dir, '/\\' ) ) . '/wp-config.php';
		if ( is_file( $root_config ) && ! is_link( $root_config ) ) {
			$source   = $root_config;
			$location = 'wp-config.php';
		} elseif ( ! file_exists( $root_config ) && is_file( $parent_config ) && ! is_link( $parent_config ) ) {
			$source   = $parent_config;
			$location = '../wp-config.php';
		} else {
			throw new \RuntimeException( 'Cannot find a regular wp-config.php file for backup.' );
		}

		$identity = new Backup_Section_Identity( 'config-file', $location, static::version_from_file_timestamp( $source ) );
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, '' );
				$staging->copy_file( $source, 'wp-config.php' );
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

		return $identity;
	}

	/**
	 * Backup the theme files.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the theme backups root / theme directory, and copy into it all
	 * files from the theme's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage           The storage to use for the themes backup directories.
	 * @param string         $themes_backup_dir The relative directory for the themes backup directories.
	 * @param \WP_Theme      $theme The object representing the theme properties.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Theme( Backup_Storage $storage, \WP_Theme $theme, Backup_Section_Identity $identity ): void {
		$source  = $theme->get_stylesheet_directory();

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the theme files.
		 */
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, $theme->get( 'Name' ) );

				// Copy the theme files to the root of the staging area.
				static::Backup_Directory( $source, $staging, '' );
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

	}

	/**
	 * Backup the theme files.
	 *
	 * Each returned identity identifies the section containing one valid theme.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage      The storage to use.
	 * @param int            $max_end_time The last second in which a theme backup can start.
	 *
	 * @return Backup_Section_Identity[] The identities of the backed up themes.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Themes( Backup_Storage $storage, int $max_end_time ) : array {
		global $wp_theme_directories;
		$directory_change = false;

		// Switch the current content of the registers theme root directories with
		// the directory we expect from the paths object.
		// This is done to be able to test the function while not needing to reinvent
		// wp_get_themes.
		$old_theme_directories = $wp_theme_directories;
		$wp_theme_directories  = rtrim( static::installation_paths()->themes_directory(), '/' );

		// Need to clear the theme cache if directories actually changed.
		if ( $old_theme_directories !== $wp_theme_directories ) {
			wp_clean_themes_cache();
			$directory_change = true;
		}

		try {
			$themes = wp_get_themes();
			$sections = [];
			foreach ( $themes as $theme ) {
				static::throw_if_out_of_time( $max_end_time );

				if ( ! $theme->errors() ) {
					$source   = $theme->get_stylesheet_directory();
					$version  = $theme->get( 'Version' ) ?: static::version_from_file_timestamp( $source . '/style.css' );
					$identity = new Backup_Section_Identity( 'theme', basename( $source ), $version );
					static::Backup_Theme( $storage, $theme, $identity );
					$sections[] = $identity;
				}
			}
		} finally {
			$wp_theme_directories = $old_theme_directories;
			if ( $directory_change ) {
				wp_clean_themes_cache();
			}
		}
		

		return $sections;
	}

	 /**
	 * Backup a plugin directory.
	 *
	 * Store the complete directory as one section. Calculate its version from every plugin main file
	 * in the directory and its display name from their plugin names.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The backup storage.
	 * @param string         $source  The directory in which the plugin is located.
	 * @param array          $plugins Plugin header data for the directory.
	 *
	 * @return Backup_Section_Identity The identity of the plugin directory section.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Plugin_Directory( Backup_Storage $storage, string $source, array $plugins ): Backup_Section_Identity {

		/*
		 * A plugin directory may contain more than one plugin main file. Combine their versions
		 * with underscores for the section version and their names with commas for the display name.
		 */
		$versions     = array();
		$plugin_names = array();
		foreach ( $plugins as $plugin ) {
			$versions[] = $plugin['Version'] ?: static::version_from_file_timestamp( WP_PLUGIN_DIR . '/' . $plugin['filename'] );
			$plugin_names[] = $plugin['Name'];
		}
		$version      = implode( '_', $versions );
		$display_name = implode( ', ', $plugin_names );

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the plugin files.
		 */
		$identity = new Backup_Section_Identity( 'plugin', basename( $source ), $version );
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, $display_name );

				// Copy the directory files.
				static::Backup_Directory( $source, $staging, '' );
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

		return $identity;
	}

	 /**
	 * Back up a single-file plugin directly inside the plugins directory.
	 *
	 * Store the plugin file as one section.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage      The backup storage.
	 * @param string         $source       The file to backup.
	 * @param string         $version      The version of the plugin.
	 * @param string         $display_name Human-readable plugin name.
	 *
	 * @return Backup_Section_Identity The identity of the plugin file section.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Single_File_Plugin( Backup_Storage $storage, string $source, string $version, string $display_name ): Backup_Section_Identity {
		$version = '' !== $version ? $version : static::version_from_file_timestamp( $source );

		/*
		 * If the backup section exists it means we already have a backup of the version,
		 * If not we need to create it and copy into it the plugin files.
		 */
		$identity = new Backup_Section_Identity( 'plugin-file', basename( $source ), $version );
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, $display_name );

				// Copy the file to the staging area.
				$staging->copy_file( $source, basename( $source ) );

				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

		return $identity;
	}

	 /**
	 * Backup the plugin files and directories.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage      The storage of the backup.
	 * @param int            $max_end_time The last second in which a plugin backup can start.
	 *
	 * @return Backup_Section_Identity[] The identities of the backed up plugin sections.
	 * 
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Plugins( Backup_Storage $storage, int $max_end_time ) : array {

		// Plugins are in principal just a file with a plugin header and you can have
		// a one file plugin in the plugins directory, or two plugin files in one directory
		// in addition to the standard format of one file in its own directory,
		// therefore copying directorie like it is done in themes is just not enough if version
		// information is needed.

		$plugindirs = [];

		foreach ( \get_plugins() as $filename => $plugin_data ) {
			$plugin_data['filename']              = $filename; // we need this later.

			$plugindirs[ dirname( $filename ) ][] = $plugin_data;
		}

		$sections = array();

		// Handle plugins stored directly in the plugins directory.
		if ( isset( $plugindirs['.'] ) ) {
			foreach ( $plugindirs['.'] as $plugin_data ) {
				$sections[] = static::Backup_Single_File_Plugin(
					$storage,
					WP_PLUGIN_DIR . '/' . $plugin_data['filename'],
					$plugin_data['Version'],
					$plugin_data['Name']
				);

			}
			unset( $plugindirs['.'] );
		}

		foreach ( $plugindirs as $dirname => $dir_data ) {
			static::throw_if_out_of_time( $max_end_time );

			// For directories with two plugins make the version a combination
			// of the versions of both plugins.
			// The version generation code relies on get_plugins and the code processing the data
			// to generate the plugin data in consistant order otherwise there might be more than
			// one backup for the same identical versions for multiple plugins in a directory.
			$sections[] = static::Backup_Plugin_Directory(
				$storage,
				WP_PLUGIN_DIR . '/' . $dirname,
				$dir_data
			);
		}

		return $sections;
	}

	/**
	 * Backup the options to a json file for a the current site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The backup storage.
	 * @param int            $site_id The ID of the site being backed up.
	 *
	 * @return Backup_Section_Identity The identity of the site options section.
	 *
	 * @throws \RuntimeException If the options table cannot be read.
	 * @throws \Exception When file creation error occurs.
	 */
	protected static function Backup_Site_Options( Backup_Storage $storage, int $site_id ): Backup_Section_Identity {
		global $wpdb;

		if ( is_multisite() ) {
			\switch_to_blog( $site_id );
		}
		try {
			$options = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value, autoload FROM $wpdb->options WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s ORDER BY option_name",
					$wpdb->esc_like( '_transient_' ) . '%',
					$wpdb->esc_like( '_site_transient_' ) . '%'
				)
			);
			if ( $wpdb->last_error ) {
				throw new \RuntimeException( 'Failed reading options for backup.' );
			}
		} finally {
			if ( is_multisite() ) {
				\restore_current_blog();
			}
		}

		// Remove widgets, sidebar and role capabilities.
		$options = array_filter(
			$options,
			function ( $option ) : bool {
				$option_name = $option->option_name;
				if ( 'sidebars_widgets' === $option_name ) {
					return false;
				}
				if ( 0 === strncmp( $option_name, 'widget_', 7 ) ) {
					return false;
				}
				if ( 'user_roles' === substr( $option_name, -10 ) ) {
					return false;
				}
				return true;
			}
		);

		// reduce verbosness of the generated json
		$options = array_map(
			function ( $option ) {
				return [
					'n' => $option->option_name,
					'v' => $option->option_value,
					'a' => $option->autoload,
				];
			},
			$options
		);

		$json = json_encode( $options );
		$identity = new Backup_Section_Identity( 'options', (string) $site_id, 'sha256-' . hash( 'sha256', $json ) );
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, '' );
				$staging->file_put_contents( 'options.json', $json );
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

		return $identity;
	}

	/**
	 * Backup the options for a multisite network.
	 *
	 * Network transients are excluded from the backup.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The backup storage.
	 * @param int            $network_id The ID of the network being backed up.
	 *
	 * @return Backup_Section_Identity The identity of the network options section.
	 *
	 * @throws \RuntimeException If the network options table cannot be read.
	 * @throws \Exception When file creation fails.
	 */
	protected static function Backup_Network_Options( Backup_Storage $storage, int $network_id ): Backup_Section_Identity {
		global $wpdb;

		$options = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM $wpdb->sitemeta WHERE site_id = %d AND meta_key NOT LIKE %s ORDER BY meta_key",
				$network_id,
				$wpdb->esc_like( '_site_transient_' ) . '%'
			)
		);
		if ( $wpdb->last_error ) {
			throw new \RuntimeException( 'Failed reading network options for backup.' );
		}

		$options = array_map(
			function ( $option ) {
				return array(
					'n' => $option->meta_key,
					'v' => $option->meta_value,
				);
			},
			$options
		);
		$json     = json_encode( $options );
		$identity = new Backup_Section_Identity( 'network-options', (string) $network_id, 'sha256-' . hash( 'sha256', $json ) );
		if ( null === $storage->section( $identity ) ) {
			try {
				$staging = $storage->create_section( $identity, '' );
				$staging->file_put_contents( 'options.json', $json );
				$staging->commit();
			} catch ( Backup_Section_Already_Exists_Exception ) {
				// Another backup committed this version after the existence check.
			}
		}

		return $identity;
	}

	/**
	 * Backup the options to a json file. In case it is a multisite there would be
	 * a file per site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage of the backup.
	 *
	 * @return Backup_Section_Identity[] The identities of the site options sections.
	 *
	 * @throws \Exception When directory creation or file creation error occurs.
	 */
	protected static function Backup_Options( Backup_Storage $storage ): array {
		$sections = array();

		// loop over all sites, store options for each site in different file.
		if ( is_multisite() ) {
			foreach ( \get_sites() as $site ) {
				$sections[] = static::Backup_Site_Options( $storage, (int) $site->blog_id );
			}
			foreach ( \get_networks() as $network ) {
				$sections[] = static::Backup_Network_Options( $storage, (int) $network->id );
			}
		} else {
			$sections[] = static::Backup_Site_Options( $storage, 1 );
		}

		return $sections;
	}

	/**
	 * Utility function to provide access to the information about the paths in which
	 * the calmPress being backuped is installed.
	 * 
	 * Helps to avoid dependency on global state to make testing easier while reducing
	 * the amount of parameters that would have needed to be passed around.
	 * 
	 * @return \calmpress\calpress\Paths An object with various path related information.
	 */
	protected static function installation_paths() : \calmpress\calmpress\Paths {
		static $cache;

		if ( ! isset ( $cache ) ) {
			$cache = new \calmpress\calmpress\Paths();
		}
		return $cache;
	}

	/**
	 * Create a backup.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage  The storage to which to write files.
	 * @param int            $max_time The maximum amount of time in seconds the backup
	 *                                 should run before terminating.
	 *                                 In practice the amount of time after which no new atomic
	 *                                 type of backup should start.
	 *
	 * @return Backup_Section_Identity[] The backed-up section identities.
	 *
	 * @throws \Exception if the backup creation fails.
	 * @throws \calmpress\calmpress\Timeout_Exception If the backup timeed out and need more "time slices" to complete.
	 */
	public static function backup( Backup_Storage $storage, int $max_time ): array {
		$max_end_time = time() + $max_time; // After this time backup process should end, completed or not.
		$sections     = array();

		$core_section    = new Backup_Section_Identity( 'core', 'calmPress', calmpress_version() );
		static::Backup_Core( $storage, $core_section );
		$sections[] = $core_section;
		static::throw_if_out_of_time( $max_end_time );

		// Backup all themes that are in standard theme location, which can be activated (no errors).
		// Ignore everything else in the themes directories.
		$sections = array_merge( $sections, static::Backup_Themes( $storage, $max_end_time ) );
		static::throw_if_out_of_time( $max_end_time );
		
		$sections = array_merge( $sections, static::Backup_Plugins( $storage, $max_end_time ) );
		static::throw_if_out_of_time( $max_end_time );
		
		$mu_plugins_directory = static::installation_paths()->mu_plugins_directory();
		if ( is_dir( $mu_plugins_directory ) ) {
			$mu_plugins_section = new Backup_Section_Identity( 'mu-plugins', 'mu-plugins', static::version_from_directory_timestamp( $mu_plugins_directory ) );
			static::Backup_MU_Plugins( $storage, $mu_plugins_directory, $mu_plugins_section );
			$sections[]         = $mu_plugins_section;
		}

		static::throw_if_out_of_time( $max_end_time );
		
		$languages_directory = static::installation_paths()->languages_directory();
		if ( is_dir( $languages_directory ) ) {
			$sections[] = static::Backup_Languages( $storage, $languages_directory );
		}

		$dropin_sections = static::Backup_Dropins( $storage, static::installation_paths()->wp_content_directory() );
		$sections = array_merge( $sections, $dropin_sections );

		$root_directory = static::installation_paths()->root_directory();
		$root_files = static::root_files( $root_directory );
		if ( ! empty( $root_files ) ) {
			$sections[] = static::Backup_Root( $storage, $root_files );
		}
		$config_section = static::Backup_Config_File( $storage, $root_directory );
		$sections[] = $config_section;

		$sections = array_merge( $sections, static::Backup_Options( $storage ) );

		return $sections;
	}

	/**
	 * Describe the sections created by the core backup engine.
	 *
	 * @since 1.0.0
	 *
	 * @param iterable<Backup_Section> $sections Sections created by this engine.
	 *
	 * @return string HTML describing the backed-up sections.
	 */
	public static function data_description( iterable $sections ): string {
		$grouped = array();
		foreach ( $sections as $section ) {
			$grouped[ $section->identity->type ][] = $section;
		}

		$labels = array(
			'core'       => __( 'Core version' ),
			'plugin'     => __( 'Plugins' ),
			'theme'      => __( 'Themes' ),
			'mu-plugins' => __( 'MU plugins' ),
			'dropin'     => __( 'Drop-in plugins' ),
			'root-files' => __( 'Root files' ),
		);
		$ret = '';
		foreach ( $labels as $type => $label ) {
			$display_sections = $grouped[ $type ] ?? array();
			if ( 'plugin' === $type ) {
				$display_sections = array_merge( $display_sections, $grouped['plugin-file'] ?? array() );
			}
			if ( empty( $display_sections ) && 'plugin' !== $type ) {
				continue;
			}

			$ret .= '<h4>' . esc_html( $label ) . '</h4>';
			if ( empty( $display_sections ) ) {
				$ret .= '<p>' . esc_html__( 'None' ) . '</p>';
				continue;
			}
			foreach ( $display_sections as $section ) {
				$identity = $section->identity;
				$name = $section->display_name() ?: $identity->location;

				// A plugin directory joins its main-file names and versions separately.
				if ( 'plugin' === $type && 'plugin' === $identity->type ) {
					$plugin_names = explode( ', ', $name );
					$versions = explode( '_', $identity->version );
					if ( count( $plugin_names ) > 1 && count( $plugin_names ) === count( $versions ) ) {
						foreach ( $plugin_names as $index => $plugin_name ) {

							/* translators: 1: Plugin name, 2: Plugin version. */
							$ret .= '<p>' . esc_html( sprintf( __( '%1$s - version %2$s' ), $plugin_name, $versions[ $index ] ) ) . '</p>';
						}
						continue;
					}
				}
				$description = $name;
				if ( in_array( $type, array( 'core', 'plugin', 'plugin-file', 'theme' ), true ) ) {
					/* translators: 1: Section name, 2: Section version. */
					$description = sprintf( __( '%1$s - version %2$s' ), $name, $identity->version );
				}
				$ret .= '<p>' . esc_html( $description ) . '</p>';
			}
		}

		return $ret;
	}

	/**
	 * Verify the data fields relating to directory information is valid. Throws if it is not.
	 *
	 * @param array  $data            The data as an array.
	 * @param string $directory_field The field in $data to veify.
	 *
	 * @throws \Restore_Exception If content $data[$directory_field] do not exist or do not contain expected
	 *                            information in the expected format.
	 */
	protected static function verify_directory_info_for_field( array $data, string $directory_field, int $expected_num_fields ) {
		if ( ! array_key_exists( $directory_field, $data ) ) {
			throw new Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'no ' . $directory_field . ' data is given' );
		}

		if ( ! is_array( $data[ $directory_field ] ) ) {
			throw new Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'curropted ' . $directory_field . ' data is given' );
		}
		
		if ( ! isset( $data[ $directory_field ]['directory'] ) ) {
			throw new Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'directory name do not exists for ' . $directory_field );
		}

		if ( ! is_scalar( $data[ $directory_field ]['directory'] ) ) {
			throw new Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'curropted directory name for ' . $directory_field );
		}

		if ( $expected_num_fields !== array_keys( $data[ $directory_field ] ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::MISMATCHED_DATA_VERSION, 'Data contains unexpected fields for ' . $directory_field);
		}
	}

	/**
	 * Verify the data fields relating to directory information and version information
	 * are valid. Throws if it is not.
	 *
	 * @param array  $data            The data as an array.
	 * @param string $field The field in $data to veify.
	 *
	 * @throws \Restore_Exception If content $data[$directory_field] do not exist or do not contain expected
	 *                            information in the expected format.
	 */
	protected static function verify_versioned_directory_info_for_field( array $data, string $field ) {

		static::verify_directory_info_for_field( $data, $field, 2 );

		if ( ! isset( $data[$field]['version'] ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'missing version name for ' . $field );
		}

		if ( ! is_scalar( $data[$field]['version'] ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'curropted version name for ' . $field );
		}
	}

	/**
	 * An helpre function to validate that a data passes to the related restore functions is in
	 * the expected format. I fit is not, throws an exception.
	 *
	 * @param array $data An unstructured data to validate that it matches what the engine expects
	 *                    for restoring a backup.
	 *
	 * @throws Restore_Exception If restore process fails.
	 */
	protected static function validate_data( array $data ) {
		if ( empty( $data ) ) {
			throw new Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'no data is given' );
		}

		foreach ( [
			'mu_plugins',
			'languages',
			'dropins',
			'root_directory',
			'options',
		] as $directory_field ) {
			static::verify_directory_info_for_field( $data, $directory_field, 1 );
		}

		static::verify_directory_info_for_field( 'core' );

		// Validate theme related info.
		if ( ! array_key_exists( 'themes', $data ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'Themes field not found' );
		}

		if ( ! is_array( $data['themes'] ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'curropted themes field' );
		}

		foreach ( $data['themes'] as $name => $theme_data ) {
			static::versioned_data_for_field( $data['themes'], $name );
		}

		// Validate plugin relatred info.
		if ( ! array_key_exists( 'plugins', $data ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'Plugins field not found' );
		}

		if ( ! is_array( $data['plugins'] ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::CURROPTED_DATA, 'curropted plugins field' );
		}

		foreach ( $data['plugins'] as $name => $plugin_data ) {
			static::versioned_data_for_field( $data['plugins'], $name );
		}

		// Check if there is unexpected data. There should be only 9 fields which validity was checked before.
		if ( 9 !== array_keys( $data ) ) {
			throw new \Restore_Exception( static::identifier(), Restore_Exception::MISMATCHED_DATA_VERSION, 'Data contains unexpected fields' );
		}
	}

	/**
	 * Prepare to restore data by doing data validation, permission checks, and whatever
	 * else can be done before the restore is run to have a better chance that the restore itself will succeed
	 * and complete faster.
	 *
	 * @since 1.0.0
	 *
	 * \calmpress\credentials\Credentials $write_credentials The credentials with which it should be possible
	 *                                    to write into code directories.
	 * @param Backup_Storage $storage     The storage from which to retrieve the backuped files.
	 * @param array          $data        An unstructured data that the engine need for restoring the backup.
	 * @param int            $max_time    The maximum amount of time in seconds the function
	 *                                    should run before terminating.
	 *                                    In practice the amount of time after which no new atomic
	 *                                    type of preperations should start.
	 * 
	 * @throws Restore_Exception If restore process fails.
	 * @throws \calmpress\calmpress\Timeout_Exception If the backup timeed out and need more "time slices" to complete.
	 */
	public static function prepare_restore( \calmpress\credentials\Credentials $write_credentials,
	                                        Backup_Storage $storage,
											array $data,
											int $max_time ) {
		
		static::validate_data( $data );
	}

	/**
	 * Restore from a backup based on the engine specific data.
	 *
	 * @since 1.0.0
	 *
	 * \calmpress\credentials\Credentials $write_credentials The credentials with which it should be possible
	 *                                    to write into code directories.
	 * @param Backup_Storage $storage     The storage from which to retrieve the backuped files.
	 * @param array          $data        An unstructured data that the engine need for restoring the backup.
	 * 
	 * @throws Restore_Exception If restore process fails.
	 */
	public static function restore( \calmpress\credentials\Credentials $write_credentials,
	                                Backup_Storage $storage,
									array $data ) {

	}

	/**
	 * Human redable description of the engine. Should not contain HTML (it will be escaped),
	 * and be translated where appropriate.
	 *
	 * @since 1.0.0
	 *
	 * @return string The description text.
	 */
	public static function description() : string {
		return __( 'Essential code and settings' );
	}

	/**
	 * A unique identiier of the engine. It mey be used in the backup meta files, therefor
	 * best to have it semantically meaningful.
	 *
	 * @since 1.0.0
	 *
	 * @return string The identifier.
	 */
	public static function identifier(): string {
		return 'core';
	}
}
