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
	 * The directory in which core backup files are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_CORE_BACKUP_PATH = 'core/';

	/**
	 * The directory in which themes' backup directories are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_THEMES_BACKUP_PATH = 'themes/';

	/**
	 * The directory in which plugins' backup directories are located relative to the
	 * backup root directory.
	 */
	const RELATIVE_PLUGINS_BACKUP_PATH = 'plugins/';

	/**
	 * The directory in which mu-plugin backup directory is located relative to the
	 * backup root directory.
	 */
	const RELATIVE_MU_PLUGINS_BACKUP_PATH = 'mu-plugins/';

	/**
	 * The directory in which languages backup directory is located relative to the
	 * backup root directory.
	 */
	const RELATIVE_LANGUAGES_BACKUP_PATH = 'languages/';

	/**
	 * The directory in which dropins backup files are located relative to backup root.
	 */
	const RELATIVE_DROPINS_BACKUP_PATH = 'dropins/';

	/**
	 * The directory in which root directory backup files are located relative to backup root.
	 */
	const RELATIVE_ROOTDIR_BACKUP_PATH = 'root_directory/';

	/**
	 * The directory in which the backup file with option table data is located.
	 */
	const RELATIVE_OPTIONS_BACKUP_PATH = 'db/options/';

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
	 * @param Temporary_Backup_Storage $staging     The staging storage for the files.
	 * @param string                   $destination A path relative to the staging root in which files will be stored.
	 * 
	 * @throws Exception When directory could not be created or file could not be copied.
	 */
	protected static function Backup_Directory( string $source, Temporary_Backup_Storage $staging, string $destination ) {

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
	 * Backup the core files (wp-admin, wp-include and some core file or root directory).
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the core backups root, and copy into it all files from
	 * wp-include, wp-admin and core files on root, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to backup to.
	 *
	 * @return string The directory at which the core backup resides in the storage.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Core( Backup_Storage $storage ) : string {
		$core_dir   = static::RELATIVE_CORE_BACKUP_PATH;
		$version    = calmpress_version();
		$backup_dir = $core_dir . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the core files.
		 */
		if ( ! $storage->section_exists( $backup_dir ) ) {

			$staging = $storage->section_working_area_storage( $backup_dir );

			// Copy wp-includes
			static::Backup_Directory( static::installation_paths()->wp_includes_directory(), $staging, 'wp-includes' );


			// Copy wp-admin.
			static::Backup_Directory( static::installation_paths()->wp_admin_directory(), $staging, 'wp-admin' );

			// Copy core code files located at root directory.
			foreach ( static::installation_paths()->core_root_file_names() as $file ) {
				$staging->copy_file( static::installation_paths()->root_directory() . $file, $file );
			}

			$staging->store();
		}

		return $backup_dir;
	}

	/**
	 * Backup the mu-plugins directory.
	 *
	 * @since 1.0.0
	 *
	 * @param string $source     The full path to mu-plugins directory which might not exist.
	 * @param string $backup_dir The directory to backup to.
	 *
	 * @return string $backup_dir If backup happend into it, otherwise ''.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_MU_Plugins( Backup_Storage $storage, string $source, string $backup_dir ): string {

		// If the mu-plugins directory does not exist there is nothing to backup and
		// Backup_Directory requires an existing directory.
		if ( is_dir( $source ) ) {
			$staging = $storage->section_working_area_storage( $backup_dir );
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
			return $backup_dir;
		}

		return '';
	}

	/**
	 * Backup the languages directory.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to use for the root directory files.
	 * @param string $source          The full path to languages directory which might not exist.
	 * @param string $backup_dir      The directory to backup to.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Languages(  Backup_Storage $storage, string $source, string $backup_dir ) {

		// If the languages directory does not exist there is nothing to backup and
		// Backup_Directory requires an existing directory.
		if ( is_dir( $source ) ) {
			$staging = $storage->section_working_area_storage( $backup_dir );
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
		}
	}

	/**
	 * Backup the dropins plugins files.
	 *
	 * Dropins are located at the root of the wp_content directory. as they don't contain
	 * version information just backup all of them together.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage The storage to use for the root directory files.
	 * @param string $source_dir      The directory of the dropins files.
	 * @param string $backup_dir      The root directory for the dropins backup files.
	 *
	 * @return string[] The names of the backed plugins.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Dropins( Backup_Storage $storage, string $source_dir, string $backup_dir ): array {

		$ret = [];

		$staging    = $storage->section_working_area_storage( $backup_dir );
		foreach ( static::installation_paths()->dropin_files_name() as $filename ) {
			$file = $source_dir . $filename;
			if ( file_exists( $file ) ) {
				if ( ! is_link( $file ) ) {
					$staging->copy_file( $file, $filename );
					$ret[] = $filename;
				}
			}
		}

		$staging->store();

		return $ret;
	}

	/**
	 * Backup the files on the root dir.
	 *
	 * The root directory can contain all kind of files that might not be code but are needed
	 * for the proper functioning of the site.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The storage to use for the root directory files.
	 * @param string         $backup_dir The root directory for the root backup files.
	 *
	 * @return string[] The names of the backed files.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root( Backup_Storage $storage, string $source_dir, string $backup_dir ): array {

		$ret = [];

		$staging    = $storage->section_working_area_storage( $backup_dir );
		$core_files = static::installation_paths()->core_root_file_names();

		foreach ( new \DirectoryIterator( $source_dir ) as $file ) {
			if ( $file->isDot() || $file->isLink() || ! $file->isFile() ) {
				continue;
			}

			// No need to backup core files.
			if ( in_array( $file->getFilename(), $core_files, true ) ) {
				continue;
			}
			$staging->copy_file( $file->getPathname(), $file->getFilename() );
			$ret[] = $file->getFilename();
		}

		$staging->store();

		return $ret;
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
	protected static function Backup_Theme( Backup_Storage $storage, string $themes_backup_dir, \WP_Theme $theme ) : string {
		$version      = $theme->get( 'Version' );
		$source       = $theme->get_stylesheet_directory();
		$theme_dir    = $themes_backup_dir . '/' . basename( $source ) . '/' . $version;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the theme files.
		 */
		if ( ! $storage->section_exists( $theme_dir ) ) {

			$staging = $storage->section_working_area_storage( $theme_dir );

			// Copy the theme files to the root of the staging area.
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
		}

		return basename( $source ) . '/' . $version;
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
	 * @param Backup_Storage $themes_backup_dir The storage to use.
	 * @param int            $max_end_time      The last second in which a theme backup can start.
	 *
	 * @return array Array of arrays containing meta information about the themes' backups
	 *               Each sub array is indexed by the relevant theme's directory and has the
	 *               following values:
	 *               'version'   The version of the backuped theme.
	 *               'directory' The directory in which the backup is located relative to the root
	 *                           of the backup directory.
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

		$themes = wp_get_themes();
		$meta   = []; 
		foreach ( $themes as $theme ) {

			static::throw_if_out_of_time( $max_end_time );
					
			// skip themes without a version.
			if ( $theme->get( 'Version' ) ) {
				$theme_dir = static::Backup_Theme( $storage, static::RELATIVE_THEMES_BACKUP_PATH, $theme );
				$meta[ $theme->get_stylesheet() ] = [
					'version'        => $theme->get( 'Version' ),
					'directory'      => static::RELATIVE_THEMES_BACKUP_PATH . $theme_dir . '/',
					'name'           => $theme->get( 'Name' ),
					'directory_name' => basename( $theme->get_stylesheet_directory() ),
				];
			}
		}

		// Return the theme directories global to its original state.
		$wp_theme_directories = $old_theme_directories;
		// Need to clear the theme cache if directories actually changed.
		if ( $directory_change ) {
			wp_clean_themes_cache();
		}
		

		return $meta;
	}

	 /**
	 * Backup the plugin files.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the plugin backups root / plugin directory, and copy into it all
	 * files from the plugin's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage            The backup storage.
	 * @param string         $plugins_backup_dir The root directory for the plugins backup files.
	 * @param string         $source             The directory in which the plugin is located.
	 * @param string         $version            The version to associate with the directory.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Plugin_Directory( Backup_Storage $storage, string $plugins_backup_dir, string $source, string $version ) : string {
		$relative_dir = basename( $source ) . '/' . $version;
		$backup_dir   = $plugins_backup_dir . $relative_dir;

		/*
		 * If the backup directory exists it means we already have a backup of the version,
		 * If not we need to create a directory and copy into it the plugin files.
		 */
		if ( ! $storage->section_exists( $backup_dir ) ) {

			$staging = $storage->section_working_area_storage( $backup_dir );

			// Copy the directory files.
			static::Backup_Directory( $source, $staging, '' );
			$staging->store();
		}

		return $relative_dir;
	}

	 /**
	 * Backup a plugin which is a single file plugin on plugins root directory.
	 *
	 * If a backup for the current version already exists, just return the directory in which it located,
	 * otherwise create a new directory under the plugin backups root / plugin directory, and copy into it all
	 * files from the plugin's directory, while preserving the relative directory structure.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage            The backup storage.
	 * @param string         $plugins_backup_dir The root directory for the plugins backup files.
	 * @param string         $source             The file to backup.
	 * @param string         $version            The version of the plugin.
	 *
	 * @return string The path to the backup directory relative to the backup
	 *                root directory.
	 *
	 * @throws \Exception When directory creation or copy error occurs.
	 */
	protected static function Backup_Root_Single_File_Plugin( Backup_Storage $storage, string $plugins_backup_dir, string $source, string $version ) : string {
		$relative_dir = $plugins_backup_dir . '/' . basename( $source ) . '/' . $version;

		/*
		 * If the backup section exists it means we already have a backup of the version,
		 * If not we need to create it and copy into it the plugin files.
		 */
		if ( ! $storage->section_exists( $relative_dir ) ) {

			$staging = $storage->section_working_area_storage( $relative_dir );

			// Copy the file to the staging area.
			$staging->copy_file( $source, basename( $source ) );

			$staging->store();
		}

		return basename( $source ) . '/' . $version;
	}

	 /**
	 * Backup the plugin files and directories.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage      The storage of the backup.
	 * @param int            $max_end_time The last second in which a plugin backup can start.
	 *
	 * @return array Array of arrays containing meta information about the plugins' backups
	 *               Each sub array is indexed by the relevant plugin's directory and has the
	 *               following values:
	 *               'version'   The version of the backuped theme.
	 *               'directory' The directory in which the backup is located relative to the root
	 *                           of the backup directory.
	 *               'type'      The type of the plugin backedup, 'root_file' for plugins
	 *                           located as single files on the plugin root directory or 'directory'
	 *                           for plugins located in a directory.
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

			// Skip plugins without versions.
			if ( ! empty( $plugin_data['Version'] ) ) {
				$plugindirs[ dirname( $filename ) ][] = $plugin_data;
			}
		}

		$meta = [];

		// Special case are plugins locate at the plugins root directory.
		if ( isset( $plugindirs['.'] ) ) {
			foreach ( $plugindirs['.'] as $plugin_data ) {
				$plugin_dir = static::Backup_Root_Single_File_Plugin(
					$storage,
					static::RELATIVE_PLUGINS_BACKUP_PATH,
					WP_PLUGIN_DIR . '/' . $plugin_data['filename'],
					$plugin_data['Version']
				);

				$meta[ $plugin_data['filename'] ] = [
					'version'     => $plugin_data['Version'],
					'directory'   => static::RELATIVE_PLUGINS_BACKUP_PATH . $plugin_dir,
					'type'        => 'root_file',
					'data'        => [
						'name'      => $plugin_data['Name'],
						'version'   => $plugin_data['Version'],
						'directory' => '',
					]
				];
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
			$versions = [];
			$data     = [];
			foreach ( $dir_data as $plugin_data ) {
				$versions[] = $plugin_data['Version'];
				$data[]     = [
					'name'      => $plugin_data['Name'],
					'version'   => $plugin_data['Version'],
					'directory' => $dirname,
				];
			}
			$version = join( '-', $versions );
			
			$plugin_dir = static::Backup_Plugin_Directory(
				$storage,
				static::RELATIVE_PLUGINS_BACKUP_PATH,
				WP_PLUGIN_DIR . '/' . $dirname,
				$version
			);

			$meta[ $dirname ] = [
				'version'   => $version,
				'directory' => static::RELATIVE_PLUGINS_BACKUP_PATH . $plugin_dir .'/',
				'type'      => 'directory',
				'data'      => $data,
			];
		}

		return $meta;
	}

	/**
	 * Backup the options to a json file for a the current site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param Temporary_Backup_Storage $staging The staging storage for the files.
	 * @param int                      $site_id The id of the specific site being backed up.
	 *
	 * @throws \Exception When file creation error occurs.
	 */
	protected static function Backup_Site_Options( Temporary_Backup_Storage $staging, int $site_id ) {
		global $wpdb;

		if ( is_multisite() ) {
			\switch_to_blog( $site_id );
		}
		$options = $wpdb->get_results( "SELECT option_name, option_value, autoload FROM $wpdb->options WHERE option_name NOT LIKE '_%transient_%'" );
		if ( is_multisite() ) {
			\restore_current_blog();
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
		$file = $site_id . '-options.json';
		if ( false === $staging->file_put_contents( $file, $json ) ) {
			throw new \Exception( 'Failed writing to ' . $file );
		}
	}

	/**
	 * Backup the options to a json file. In case it is a multisite there would be
	 * a file per site.
	 *
	 * The backup ignores transients, widgets and user roles options.
	 *
	 * @since 1.0.0
	 *
	 * @param Backup_Storage $storage    The storage of the backup.
	 * @param string         $backup_dir The directory for the options backup files.
	 *
	 * @throws \Exception When directory creation or file creation error occurs.
	 */
	protected static function Backup_Options( Backup_Storage $storage, string $backup_dir ) {
		global $wpdb;

		$staging = $storage->section_working_area_storage( $backup_dir );

		// loop over all sites, store options for each site in different file.
		if ( is_multisite() ) {
			foreach ( \get_sites() as $site ) {
				static::Backup_Site_Options( $staging, $site->blog_id );
			}
		} else {
			static::Backup_Site_Options( $staging, \get_current_blog_id() );
		}

		$staging->store();
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
	 * @param Backup_Storage $backup_root The storage to which to write files.
	 * @param int            $max_time    The maximum amount of time in seconds the backup
	 *                                    should run before terminating.
	 *                                    In practice the amount of time after which no new atomic
	 *                                    type of backup should start.
	 *
	 * @return array An unstructured data that the engine need for restoring the backup.
	 *
	 * @throws \Exception if the backup creation fails.
	 * @throws Timeout_Exception If the backup timeed out and need more "time slices" to complete.
	 */
	public static function backup( Backup_Storage $storage, int $max_time ): array {
		$max_end_time = time() + $max_time; // After this time backup process should end, completed or not.

		$meta['version'] = calmpress_version();
		static::Backup_Core( $storage, static::RELATIVE_CORE_BACKUP_PATH );
		static::throw_if_out_of_time( $max_end_time );

		// Backup all themes that are in standard theme location, which can be activated (no errors).
		// Ignore everything else in the themes directories.
		$meta['themes'] = static::Backup_Themes( $storage, $max_end_time );
		static::throw_if_out_of_time( $max_end_time );
		
		$meta['plugins'] = static::Backup_Plugins( $storage, $max_end_time );
		static::throw_if_out_of_time( $max_end_time );
		
		$mu_rel_dir = static::RELATIVE_MU_PLUGINS_BACKUP_PATH . time() . '/';
		$mu_dir     = $backup_root . $mu_rel_dir;
		$dir = static::Backup_MU_Plugins( $storage, static::installation_paths()->mu_plugins_directory(), $mu_dir );
		if ( '' !== $dir ) {
			$meta['mu_plugins']['directory'] = $dir;
		}

		static::throw_if_out_of_time( $max_end_time );
		
		$lang_rel_dir = static::RELATIVE_LANGUAGES_BACKUP_PATH . time() . '/';
		$meta['languages'] = static::Backup_Languages( $storage, static::installation_paths()->languages_directory(), $lang_rel_dir );
		$meta['languages']['directory'] = $lang_rel_dir;

		$dropins_rel_dir = static::RELATIVE_DROPINS_BACKUP_PATH . time();
		$files           = static::Backup_Dropins( $storage, static::installation_paths()->wp_content_directory(), $dropins_rel_dir );
		$meta['dropins']['directory'] = $dropins_rel_dir;
		$meta['dropins']['files']     = $files;

		$root_dir_rel_dir = static::RELATIVE_ROOTDIR_BACKUP_PATH . time();
		$files            = static::Backup_Root( $storage, static::installation_paths()->root_directory(), $root_dir_rel_dir );
		$meta['root_directory']['directory'] = $root_dir_rel_dir;
		$meta['root_directory']['files']     = $files;

		$options_rel_dir = static::RELATIVE_OPTIONS_BACKUP_PATH . time();
		static::Backup_Options( $storage, $options_rel_dir );
		$meta['options']['directory'] = $options_rel_dir ;

		return $meta;
	}

	/**
	 * Generate a human freindly description of a backup data relating to the engine.
	 * 
	 * @see Engine_Specific_Backup:data_description 
	 * @since 1.0.0
	 *
	 * @param array $data The data related to the engine which was generated at the time of backup.
	 *
	 * @return string An HTML contaning details about the core version, plugins and themes, dropins
	 *                and root files included in the backup.
	 */
	public static function data_description( array $data ): string {
		$ret = '<p>' . esc_html__( 'Core version: ' ) . esc_html( $data['version'] ) . '</p>';
		$ret .= '<h3>' . esc_html__( 'Plugins' ) . '</h3>';
		foreach ( $data['plugins'] as $plugin_data ) {
			switch ( $plugin_data['type'] ) {
				case 'root_file':
					$ret .= '<p>' . esc_html(
						sprintf( __( '%1s version %2s at plugins root directory' ),
							$plugin_data['data']['name'],
							$plugin_data['data']['version']
						)
					) . '</p>';
					break;
				case 'directory':
					foreach ( $plugin_data['data'] as $pdata ) {
						$ret .= '<p>' . esc_html(
							sprintf( __( '%1s version %2s at the %3s directory' ),
								$pdata['name'],
								$pdata['version'],
								$pdata['directory']
							)
						) . '</p>';
					}
					break;
				default:
					trigger_error( 'Unknown plugin type ' . $plugin_data['type'] );
					break;
			}
		}

		
		$ret .= '<h3>' . esc_html__( 'MU Plugins' ) . '</h3>';
		$ret .= '<p>';
		if ( isset( $data['mu_plugins'] ) ) {
			$ret .= __( 'Exists' );
		} else {
			$ret .= __( 'Do not exists' );
		}
		$ret .= '</p>';

		$ret .= '<h3>' . esc_html__( 'Drop in Plugins' ) . '</h3>';
		if ( empty( $data['dropins']['files'] ) ) {
			$ret .= __( 'None' );
		} else {
			foreach ( $data['dropins']['files'] as $filename ) {
				$ret .= '<p>' . $filename . '</p>';
			}
		}

		$ret .= '<h3>' . esc_html__( 'Files at root directory' ) . '</h3>';
		if ( empty( $data['root_directory']['files'] ) ) {
			$ret .= __( 'None' );
		} else {
			foreach ( $data['root_directory']['files'] as $filename ) {
				$ret .= '<p>' . $filename . '</p>';
			}
		}

		$ret .= '<h3>' . esc_html__( 'Themes' ) . '</h3>';
		foreach ( $data['themes'] as $theme_data ) {
			$ret .= '<p>' . esc_html(
				sprintf( __( '%1s version %2s at the %3s directory' ),
					$theme_data['name'],
					$theme_data['version'],
					$theme_data['directory_name']
				)
			) . '</p>';
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
	 * @throws Timeout_Exception If the backup timeed out and need more "time slices" to complete.
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